<?php

namespace lx\socket;

use lx;
use lx\socket\Channel\ChannelInterface;
use lx\socket\Channel\ChannelMessage;
use lx\socket\Channel\ChannelEvent;
use lx\socket\Channel\ChannelQuestion;
use RuntimeException;

class Connection
{
    /** @var SocketServer */
    private $server;

    /** @var Socket */
    private $socket;

    /** @var ChannelInterface $channel */
    private $channel = null;

    /** @var string $ip */
    private $ip;

    /** @var array|bool */
    private $channelOpenData;

    /** @var int $port */
    private $port;

    /** @var string $id */
    private $id = '';

    /** @var string $dataBuffer */
    private $dataBuffer = '';

    /** @var bool */
    private $isHandshakeDone = false;

    /** @var bool */
    private $isWaitingForData = false;

    /**
     * @param SocketServer $server
     * @param Socket $socket
     */
    public function __construct(SocketServer $server, Socket $socket)
    {
        $this->server = $server;
        $this->socket = $socket;

        $this->channelOpenData = false;

        // set some client-information:
        $socketName = $socket->getName();
        $tmp = explode(':', $socketName);
        $this->ip = $tmp[0];
        $this->port = (int)$tmp[1];
        $this->id = sha1($this->ip . $this->port . spl_object_hash($this) . $this->server->getSecret());

        $this->log('Connected');
    }

    /**
     * @return array
     */
    public function getChannelOpenData(): array
    {
        $result = (is_bool($this->channelOpenData)) ? [] : $this->channelOpenData;
        $result['id'] = $this->getId();
        return $result;
    }

    /**
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->ip;
    }

    /**
     * @return int
     */
    public function getClientPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return Socket
     */
    public function getClientSocket()
    {
        return $this->socket;
    }

    /**
     * @return ChannelInterface|null
     */
    public function getClientChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    /**
     * @param string|array $payload
     * @param string $type
     * @param bool $masked
     * @return bool
     */
    public function send($payload, string $type = 'text', bool $masked = false): bool
    {
        try {
            $payload = json_encode($payload);
            $encodedData = $this->hybi10Encode($payload, $type, $masked);
            $this->socket->writeBuffer($encodedData);
        } catch (RuntimeException $e) {
            $this->server->connections->remove($this, false);
            return false;
        }

        return true;
    }

    /**
     * @param int $statusCode
     */
    public function close(int $statusCode = 1000): void
    {
        $payload = str_split(sprintf('%016b', $statusCode), 8);
        $payload[0] = chr(bindec($payload[0]));
        $payload[1] = chr(bindec($payload[1]));
        $payload = implode('', $payload);

        switch ($statusCode) {
            case 1000:
                $payload .= 'normal closure';
                break;
            case 1001:
                $payload .= 'going away';
                break;
            case 1002:
                $payload .= 'protocol error';
                break;
            case 1003:
                $payload .= 'unknown data (opcode)';
                break;
            case 1004:
                $payload .= 'frame too large';
                break;
            case 1007:
                $payload .= 'utf8 expected';
                break;
            case 1008:
                $payload .= 'message violates server policy';
                break;
        }

        if ($this->send($payload, 'close', false) === false) {
            return;
        }

        if ($this->channel) {
            $this->channel->onDisconnect($this);
        }
        $this->server->connections->remove($this);
        $this->socket->shutdown();
    }

    /**
     * @param string $message
     * @param string $type
     */
    public function log(string $message, string $type = 'lifecycle'): void
    {
        $this->server->log('[client ' . $this->ip . ':' . $this->port . '] ' . $message, $type);
    }

    public function onDisconnect(): void
    {
        $this->log('Disconnected');
        $this->close(1000);
    }

    public function reactToMessage(): void
    {
        try {
            $data = $this->socket->readBuffer();
        } catch (RuntimeException $e) {
            $this->server->connections->remove($this, false);
            return;
        }

        $bytes = strlen($data);
        if ($bytes === 0) {
            $this->onDisconnect();
            return;
        }

        if (!$this->isWaitingForData && !$this->server->connections->checkRequestLimit($this)) {
            $this->onDisconnect();
        } else {
            $this->onData($data);
        }
    }

    /**
     * @param int $httpStatusCode
     * @throws RuntimeException
     */
    public function sendHttpResponse(int $httpStatusCode = 400): void
    {
        $httpHeader = 'HTTP/1.1 ';
        switch ($httpStatusCode) {
            case 400:
                $httpHeader .= '400 Bad Request';
                break;
            case 401:
                $httpHeader .= '401 Unauthorized';
                break;
            case 403:
                $httpHeader .= '403 Forbidden';
                break;
            case 404:
                $httpHeader .= '404 Not Found';
                break;
            case 501:
                $httpHeader .= '501 Not Implemented';
                break;
        }
        $httpHeader .= "\r\n";
        try {
            $this->socket->writeBuffer($httpHeader);
        } catch (RuntimeException $e) {
            // @todo Handle write to socket error
        }
    }


    /*******************************************************************************************************************
     * PRIVATE
     ******************************************************************************************************************/

    /**
     * @param string $message
     */
    private function processMessage($message)
    {
        $message = json_decode($message, true);

        $action = $message['__lxws_action__'] ?? null;
        if ($action) {
            switch ($action) {
                case 'connection':
                    if ($this->channelOpenData === false) {
                        if (!$this->channel->checkAuthData($this, $message['auth'] ?? null)) {
                            $this->server->connections->remove($this, false);
                            return;
                        }

                        $this->channelOpenData = $message['channelOpenData'] ?? true;
                        if (!$this->channelOpenData) {
                            $this->channelOpenData = true;
                        }

                        $this->channel->onConnect($this);
                    }
                    break;
            }

            return;
        }

        $eventName = $message['__metaData__']['__event__'] ?? null;
        if ($eventName) {
            $event = new ChannelEvent($eventName, $message, $this->getClientChannel(), $this);
            $this->channel->onEvent($event);
            return;
        }

        $isQuestion = $message['__metaData__']['__question__'] ?? null;
        if ($isQuestion) {
            $question = new ChannelQuestion($message, $this->getClientChannel(), $this);
            $this->channel->onQuestion($question);
            return;
        }

        if (lx::$app->isMode('dev')) {
            $requestForDump = $message['__metaData__']['__dump__'] ?? null;
            if ($requestForDump) {
                $result = $this->channel->onDump($requestForDump);
                $this->send([
                    '__dump__' => $result,
                ]);
            }
            return;
        }

        $this->channel->onMessage(new ChannelMessage($message, $this->getClientChannel(), $this));
    }

    /**
     * @param string $data
     */
    private function onData(string $data): void
    {
        if ($this->isHandshakeDone) {
            $this->handle($data);
        } else {
            $this->handshake($data);

            $this->send([
                'id' => $this->id,
                'channelData' => $this->channel->getData(),
                'connections' => $this->channel->getConnectionsData(),
            ]);
        }
    }

    /**
     * @param string $data
     * @return bool
     */
    private function handle(string $data): bool
    {
        if ($this->isWaitingForData === true) {
            $data = $this->dataBuffer . $data;
            $this->dataBuffer = '';
            $this->isWaitingForData = false;
        }

        $decodedData = $this->hybi10Decode($data);

        if (empty($decodedData)) {
            $this->isWaitingForData = true;
            $this->dataBuffer .= $data;
            return false;
        } else {
            $this->dataBuffer = '';
            $this->isWaitingForData = false;
        }

        if (!isset($decodedData['type'])) {
            $this->sendHttpResponse(401);
            $this->socket->shutdown();
            $this->server->connections->remove($this, false);
            return false;
        }

        switch ($decodedData['type']) {
            case 'text':
                $this->processMessage($decodedData['payload']);
                break;
            case 'binary':
                $this->close(1003);
                break;
            case 'ping':
                $this->send($decodedData['payload'], 'pong', false);
                $this->log('Ping? Pong!');
                break;
            case 'pong':
                // server currently not sending pings, so no pong should be received.
                break;
            case 'close':
                $this->close();
                $this->log('Disconnected');
                break;
        }

        return true;
    }

    /**
     * @param string $data
     * @return bool
     * @throws RuntimeException
     */
    private function handshake(string $data): bool
    {
        $this->log('Performing handshake');
        $lines = preg_split("/\r\n/", $data);

        // check for valid http-header:
        if (!preg_match('/\AGET (\S+) HTTP\/1.1\z/', $lines[0], $matches)) {
            $this->log('Invalid request: ' . $lines[0]);
            $this->sendHttpResponse(400);
            $this->socket->shutdown();
            return false;
        }

        // check for valid channel:
        $path = $matches[1];
        $channelKey = substr($path, 1);

        if ($this->server->channels->has($channelKey) === false) {
            $this->log('Invalid channel: ' . $path);
            $this->sendHttpResponse(404);
            $this->socket->shutdown();
            $this->server->connections->remove($this, false);
            return false;
        }

        $this->channel = $this->server->channels->get($channelKey);
        if ($this->channel->isClosed()) {
            $this->log('Channel is closed.');
            $this->sendHttpResponse(403);
            $this->socket->shutdown();
            $this->server->connections->remove($this, false);
            return false;
        }

        // generate headers array:
        $headers = [];
        foreach ($lines as $line) {
            $line = chop($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        // check for supported websocket version:
        if (!isset($headers['Sec-WebSocket-Version']) || $headers['Sec-WebSocket-Version'] < 6) {
            $this->log('Unsupported websocket version.');
            $this->sendHttpResponse(501);
            $this->socket->shutdown();
            $this->server->connections->remove($this, false);
            return false;
        }

        // check origin:
        if ($this->server->originValidator->needValidate()) {
            $origin = (isset($headers['Sec-WebSocket-Origin'])) ? $headers['Sec-WebSocket-Origin'] : '';
            $origin = (isset($headers['Origin'])) ? $headers['Origin'] : $origin;
            if (empty($origin)) {
                $this->log('No origin provided.');
                $this->sendHttpResponse(401);
                $this->socket->shutdown();
                $this->server->connections->remove($this, false);
                return false;
            }

            if (!$this->server->originValidator->validate($origin)) {
                $this->log('Invalid origin provided.');
                $this->sendHttpResponse(401);
                $this->socket->shutdown();
                $this->server->connections->remove($this, false);
                return false;
            }
        }

        // do handyshake: (hybi-10)
        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: " . $secAccept . "\r\n";
        if (isset($headers['Sec-WebSocket-Protocol']) && !empty($headers['Sec-WebSocket-Protocol'])) {
            $response .= "Sec-WebSocket-Protocol: " . substr($path, 1) . "\r\n";
        }
        $response .= "\r\n";
        try {
            $this->socket->writeBuffer($response);
        } catch (RuntimeException $e) {
            return false;
        }

        $this->isHandshakeDone = true;
        $this->log('Handshake sent');

        return true;
    }

    /**
     * @param string $payload
     * @param string $type
     * @param bool $masked
     * @return string
     * @throws RuntimeException
     */
    private function hybi10Encode(string $payload, string $type = 'text', bool $masked = true): string
    {
        $frameHead = [];
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++) {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2] > 127) {
                $this->close(1004);
                throw new RuntimeException('Invalid payload. Could not encode frame.');
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i) {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        $mask = [];
        if ($masked === true) {
            for ($i = 0; $i < 4; $i++) {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }

        return $frame;
    }

    /**
     * @param string $data
     * @return array
     */
    private function hybi10Decode(string $data): array
    {
        $unmaskedPayload = '';
        $decodedData = [];

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

        // close connection if unmasked frame is received:
        if ($isMasked === false) {
            $this->close(1002);
        }

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;
            case 2:
                $decodedData['type'] = 'binary';
                break;
            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;
            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;
            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;
            default:
                // Close connection on unknown opcode:
                $this->close(1003);
                break;
        }

        if ($payloadLength === 126) {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } elseif ($payloadLength === 127) {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for ($i = 0; $i < 8; $i++) {
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        } else {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if (strlen($data) < $dataLength) {
            return [];
        }

        if ($isMasked === true) {
            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i])) {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }
}
