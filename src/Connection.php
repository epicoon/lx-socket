<?php

namespace lx\socket;

use lx;
use lx\socket\Channel\ChannelInterface;
use lx\socket\Channel\ChannelMessage;
use lx\socket\Channel\ChannelEvent;
use lx\socket\Channel\ChannelRequest;
use RuntimeException;

class Connection
{
    const CLOSE_CODE_NORMAL = 1000;
    const CLOSE_CODE_LEAVE = 1001;
    const CLOSE_CODE_ACCESS_ERROR = 1002;
    const CLOSE_CODE_PROTOCOL_ERROR = 1003;
    const CLOSE_CODE_UNKNOWN_DATA = 1004;
    const CLOSE_CODE_LARGE_FRAME = 1005;
    const CLOSE_CODE_SOCKET_ERROR = 1006;
    const CLOSE_CODE_WRONG_ENCODING = 1007;
    const CLOSE_CODE_POLICY_VIOLATION = 1008;
    const CLOSE_CODE_REQUEST_LIMIT_EXCEEDED = 1009;

    private SocketServer $server;
    private SocketKeeper $socket;
    private ?ChannelInterface $channel = null;
    private string $ip;
    /** @var array|bool */
    private $channelOpenData;
    private int $port;
    private string $id = '';
    private ?string $oldId = null;
    private string $dataBuffer = '';
    private bool $isHandshakeDone = false;
    private bool $isWaitingForData = false;
    private bool $isReadyForClose = false;

    public function __construct(SocketServer $server, SocketKeeper $socket)
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

    public function getChannelOpenData(): array
    {
        $result = (is_bool($this->channelOpenData)) ? [] : $this->channelOpenData;
        $result['id'] = $this->getId();
        return $result;
    }

    public function getClientIp(): string
    {
        return $this->ip;
    }

    public function getClientPort(): int
    {
        return $this->port;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOldId(): ?string
    {
        return $this->oldId;
    }

    public function isReconnected(): bool
    {
        return $this->oldId !== null;
    }

    public function getClientSocket(): SocketKeeper
    {
        return $this->socket;
    }

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
            $this->channelOnDisconnect();
            $this->destruct(self::CLOSE_CODE_SOCKET_ERROR);
            return false;
        }

        return true;
    }

    public function close(int $statusCode = self::CLOSE_CODE_NORMAL): void
    {
        $payload = str_split(sprintf('%016b', $statusCode), 8);
        $payload[0] = chr(bindec($payload[0]));
        $payload[1] = chr(bindec($payload[1]));
        $payload = implode('', $payload);

        switch ($statusCode) {
            case self::CLOSE_CODE_NORMAL:
                $payload .= 'normal closure';
                break;
            case self::CLOSE_CODE_LEAVE:
                $payload .= 'going away';
                break;
            case self::CLOSE_CODE_PROTOCOL_ERROR:
                $payload .= 'protocol error';
                break;
            case self::CLOSE_CODE_UNKNOWN_DATA:
                $payload .= 'unknown data (opcode)';
                break;
            case self::CLOSE_CODE_LARGE_FRAME:
                $payload .= 'frame too large';
                break;
            case self::CLOSE_CODE_WRONG_ENCODING:
                $payload .= 'utf8 expected';
                break;
            case self::CLOSE_CODE_POLICY_VIOLATION:
                $payload .= 'message violates server policy';
                break;
            case self::CLOSE_CODE_REQUEST_LIMIT_EXCEEDED:
                $payload .= 'request limit exceeded';
                break;
        }

        if ($this->send($payload, 'close', false) === false) {
            return;
        }

        $this->destruct($statusCode);
    }

    public function log(string $message, string $type = 'lifecycle'): void
    {
        $this->server->log('[client ' . $this->ip . ':' . $this->port . '] ' . $message, $type);
    }

    public function isWaitingForData(): bool
    {
        return $this->isWaitingForData;
    }

    public function reactToMessage(): void
    {
        try {
            $data = $this->socket->readBuffer();
        } catch (RuntimeException $e) {
            $this->channelOnDisconnect();
            $this->destruct(self::CLOSE_CODE_SOCKET_ERROR);
            return;
        }

        if (strlen($data) === 0) {
            $this->log('Read buffer is empty');
            $this->channelOnDisconnect();
            $this->close(/*TODO code???*/);
            return;
        }

        if (!$this->server->connections->checkRequestLimit($this)) {
            $this->channelOnDisconnect();
            $this->close(self::CLOSE_CODE_REQUEST_LIMIT_EXCEEDED);
        }

        $this->onData($data);
    }

    /**
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

    private function processMessage(string $message)
    {
        $message = json_decode($message, true);

        $action = $message['__lxws_action__'] ?? null;
        if ($action) {
            switch ($action) {
                case 'connect':
                    if ($this->channelOpenData === false) {
                        if (!$this->channel->checkOnConnect($this, $message['auth'] ?? [])) {
                            $this->log('Conncetion validation failed');
                            $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
                            return;
                        }

                        $this->channelOpenData = $message['channelOpenData'] ?? true;
                        if (!$this->channelOpenData) {
                            $this->channelOpenData = true;
                        }

                        $this->channel->onConnect($this);
                    }
                    break;

                case 'reconnect':
                    if (!$this->channel->checkOnReconnect(
                        $this,
                        $message['oldConnectionId'],
                        $message['auth'] ?? []
                    )) {
                        $this->log('Reconncetion validation failed');
                        $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
                        return;
                    }

                    $this->oldId = $message['oldConnectionId'];
                    $this->channelOpenData = $message['channelOpenData'] ?? true;
                    if (!$this->channelOpenData) {
                        $this->channelOpenData = true;
                    }

                    $this->channel->onReconnect($this);
                    break;

                case 'close':
                    $this->isReadyForClose = true;
                    $this->send([
                        '__lxws_event__' => 'close',
                    ]);
                    break;

                case 'break':
                    $this->send([
                        '__lxws_event__' => 'break',
                    ]);
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

        $isRequest = $message['__metaData__']['__request__'] ?? null;
        if ($isRequest) {
            $request = new ChannelRequest($message, $this->getClientChannel(), $this);
            $this->channel->onRequest($request);
            return;
        }

        $this->channel->onMessage(new ChannelMessage($message, $this->getClientChannel(), $this));
    }

    private function onData(string $data): void
    {
        if ($this->isHandshakeDone) {
            $this->handle($data);
        } else {
            if ($this->handshake($data)) {
                $response = [
                    'id' => $this->id,
                    'channelData' => $this->channel->getChannelData($this),
                    'connections' => $this->channel->getConnectionsData($this),
                ];
                if ($this->channel->isReconnectionAllowed()) {
                    $response['reconnectionAllowed'] = true;
                }
                $this->send($response);
            }
        }
    }

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
            $this->log('Message type is empty');
            $this->sendHttpResponse(401);
            $this->channelOnDisconnect();
            $this->destruct(self::CLOSE_CODE_PROTOCOL_ERROR);
            return false;
        }

        switch ($decodedData['type']) {
            case 'text':
                $this->processMessage($decodedData['payload']);
                break;
            case 'binary':
                $this->channelOnDisconnect();
                $this->close(self::CLOSE_CODE_UNKNOWN_DATA);
                break;
            case 'ping':
                $this->send($decodedData['payload'], 'pong', false);
                $this->log('Ping? Pong!');
                break;
            case 'pong':
                // server currently not sending pings, so no pong should be received.
                break;
            case 'close':
                if ($this->isReadyForClose) {
                    $this->channelOnLeave();
                } else {
                    $this->channelOnDisconnect();
                }
                $this->close();
                $this->log('Disconnected');
                break;
        }

        return true;
    }

    /**
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
            $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
            return false;
        }

        $channel = $this->server->channels->get($channelKey);
        if ($channel->isClosed()) {
            $this->log('Channel is closed.');
            $this->sendHttpResponse(403);
            $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
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
            $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
            return false;
        }

        // check origin:
        if ($this->server->originValidator->needValidate()) {
            $origin = (isset($headers['Sec-WebSocket-Origin'])) ? $headers['Sec-WebSocket-Origin'] : '';
            $origin = (isset($headers['Origin'])) ? $headers['Origin'] : $origin;
            if (empty($origin)) {
                $this->log('No origin provided.');
                $this->sendHttpResponse(401);
                $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
                return false;
            }

            if (!$this->server->originValidator->validate($origin)) {
                $this->log('Invalid origin provided.');
                $this->sendHttpResponse(401);
                $this->destruct(self::CLOSE_CODE_ACCESS_ERROR);
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

        $this->channel = $channel;
        $this->isHandshakeDone = true;
        $this->log('Handshake sent');

        return true;
    }

    /**
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
                $this->channelOnDisconnect();
                $this->close(self::CLOSE_CODE_LARGE_FRAME);
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
            $this->channelOnDisconnect();
            $this->close(self::CLOSE_CODE_PROTOCOL_ERROR);
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
                $this->channelOnDisconnect();
                $this->close(self::CLOSE_CODE_UNKNOWN_DATA);
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

    private function destruct(int $statusCode): void
    {
        $this->server->connections->remove($this);
        $this->socket->shutdown();
        $this->isReadyForClose = false;
    }

    private function channelOnDisconnect(): void
    {
        if ($this->channel) {
            $this->channel->onDisconnect($this);
        }
    }

    private function channelOnLeave(): void
    {
        if ($this->channel) {
            $this->channel->onLeave($this);
        }
    }
}
