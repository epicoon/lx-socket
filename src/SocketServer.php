<?php

namespace lx\socket;

use lx\Math;
use lx\process\ProcessApplication;
use lx\socket\serverTools\ChannelRepository;
use lx\socket\serverTools\ConnectionRepository;
use lx\socket\serverTools\OriginValidator;
use Exception;
use RuntimeException;

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
    private SocketKeeper $masterSocket;
    private array $allSocketResources = [];
    private ChannelRepository $_channelRepo;
    private ConnectionRepository $_connectionRepo;
    private OriginValidator $_originValidator;
    private string $sessionSecret;

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
    public function __construct(iterable $config = [])
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

        $this->log('Server created', 'lifecycle');
    }

    public function __destruct()
    {
        $this->log('Server closed', 'lifecycle');
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'channels': return $this->_channelRepo;
            case 'connections': return $this->_connectionRepo;
            case 'originValidator': return $this->_originValidator;
        }

        return parent::__get($name);
    }

    public function getSecret(): string
    {
        return $this->sessionSecret;
    }

    public function getMasterSocket(): SocketKeeper
    {
        return $this->masterSocket;
    }

    public function getClientConnections(): array
    {
        $resources = $this->getClientSocketResources();
        $result = [];
        foreach ($resources as $resource) {
            $result[] = $this->connections->get($resource);
        }

        return $result;        
    }

    public function getClientSocketResources(): array
    {
        $result = [];
        foreach ($this->allSocketResources as $resource) {
            if ($resource == $this->masterSocket->getResource()) continue;
            $result[] = $resource;
        }

        return $result;
    }

    public function removeSocket(SocketKeeper $socket): void
    {
        $index = array_search($socket->getResource(), $this->allSocketResources);
        if ($index !== false) {
            unset($this->allSocketResources[$index]);
        }
    }

    final protected function process(): void
    {
        try {
            $this->iteration();
        } catch (Exception $e) {
            $this->log($e->getMessage(), 'error');
        }
    }


    /*******************************************************************************************************************
     * PRIVATE
     ******************************************************************************************************************/

    private function iteration(): void
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

        $list = $this->channels->getOnTimer();
        foreach ($list as $channel) {
            $channel->onIteration();
        }
    }

    /**
     * @throws RuntimeException
     */
    private function createMasterSocket(string $host, int $port): void
    {
        $socket = SocketKeeper::createMasterSocket($host, $port);
        if ($socket->hasError()) {
            throw new RuntimeException('Error creating socket: ' . $socket->getErrorString());
        }

        $this->masterSocket = $socket;
        $this->allSocketResources[] = $socket->getResource();
    }

    private function onChangeMasterSocket(): void
    {
        $socket = SocketKeeper::createClientSocket($this->masterSocket);
        if ($socket->hasError()) {
            $this->log('Socket error: ' . $socket->getErrorString(), 'error');
            return;
        }

        if ($this->connections->create($socket)) {
            $this->allSocketResources[] = $socket->getResource();
        }
    }
}
