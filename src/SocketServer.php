<?php

namespace lx\socket;

use lx\Math;
use lx\process\ProcessApplication;
use lx\socket\serverTools\ChannelRepository;
use lx\socket\serverTools\ConnectionRepository;
use lx\socket\serverTools\OriginValidator;
use Exception;
use RuntimeException;

//TODO
use lx\socket\Channel\DemoChannel;

/**
 * Class SocketServer
 * @package lx\socket
 *
 * @property-read ChannelRepository $channels
 * @property-read ConnectionRepository $connections
 * @property-read OriginValidator $originValidator
 */
class SocketServer extends ProcessApplication
{
    /** @var Socket */
    private $masterSocket;

    /** @var array */
    private $allSocketResources = [];

    /** @var ChannelRepository */
    private $_channelRepo;

    /** @var ConnectionRepository */
    private $_connectionRepo;

    /** @var OriginValidator */
    private $_originValidator;

    /** @var string */
    private $sessionSecret;

    /**
     * SocketServer constructor.
     * @param array $config
     *  - host
     *  - port
     *  - checkOrigin
     *  - allowedOrigins
     *  - maxClients
     *  - maxConnectionsPerIp
     *  - maxRequestsPerMinute
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->sessionSecret = Math::randHash();
        ob_implicit_flush(1);
        $this->createMasterSocket(
            $config['host'] ?? 'localhost',
            $config['port'] ?? Constants::DEFAULT_MASTER_PORT
        );

        $this->_channelRepo = new ChannelRepository();
        $this->_connectionRepo = new ConnectionRepository($this, $config);
        $this->_originValidator = new OriginValidator($config);

        $this->log('Server created');
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        switch ($name) {
            case 'channels': return $this->_channelRepo;
            case 'connections': return $this->_connectionRepo;
            case 'originValidator': return $this->_originValidator;
        }

        return parent::__get($name);
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->sessionSecret;
    }

    /**
     * @param Socket $socket
     */
    public function removeSocket(Socket $socket) : void
    {
        $index = array_search($socket->getResource(), $this->allSocketResources);
        if ($index !== false) {
            unset($this->allSocketResources[$index]);
        }
    }

    /**
     * @param string $message
     * @param string $type
     */
    public function log($message, $type = null)
    {
//        echo date('Y-m-d H:i:s') . ' [' . ($type ? $type : 'error') . '] ' . $message . PHP_EOL;
    }

    protected function processMessage($message)
    {
        if (is_string($message)) {
            foreach ($this->allSocketResources as $resource) {
                if ($resource == $this->masterSocket->getResource()) continue;

                $c = $this->connections->get($resource);
                $c->send('FROM processMessage:' . $message);
            }


            return;
        }

        switch($message['key']) {
            case 'addConnections' :
                $this->channels->set('demo', DemoChannel::getInstance());
                break;
        }
    }

    final protected function process()
    {
        try {
            $this->iteration();
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
    }


    /*******************************************************************************************************************
     * PRIVATE
     ******************************************************************************************************************/

    private function iteration()
    {
        $changedSocketResources = $this->allSocketResources;
        @stream_select(
            $changedSocketResources,
            $write = null,
            $except = null,
            0,
            0
        );
        foreach ($changedSocketResources as $socketResource) {
            if ($this->masterSocket->matchResource($socketResource)) {
                $this->onChangeMasterSocket();
                continue;
            }

            $connection = $this->connections->get($socketResource);
            if (!$connection) {
                continue;
            }

            $connection->reactToMessage();
        }
    }

    /**
     * @param string $host
     * @param int $port
     * @throws RuntimeException
     */
    private function createMasterSocket(string $host, int $port) : void
    {
        $socket = Socket::createMasterSocket($host, $port);
        if ($socket->hasError()) {
            throw new RuntimeException('Error creating socket: ' . $socket->getErrorString());
        }

        $this->masterSocket = $socket;
        $this->allSocketResources[] = $socket->getResource();
    }

    private function onChangeMasterSocket() : void
    {
        $socket = Socket::createClientSocket($this->masterSocket);
        if ($socket->hasError()) {
            $this->log('Socket error: ' . $socket->getErrorString());
            return;
        }

        if ($this->connections->create($socket)) {
            $this->allSocketResources[] = $socket->getResource();
        }
    }
}
