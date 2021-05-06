<?php

namespace lx\socket\serverTools;

use lx\socket\Connection;
use lx\socket\Constants;
use lx\socket\SocketKeeper;
use lx\socket\SocketServer;

class ConnectionRepository
{
    private SocketServer $server;
    private array $connections = [];
    private array $ipStorage = [];
    private array $requestStorage = [];
    private int $maxConnections = Constants::MAX_CONNECTIONS;
    private int $maxConnectionsPerIp = Constants::MAX_CONNECTION_PER_IP;
    private int $maxRequestsPerMinute = Constants::MAX_REQUESTS_PER_MINUTE;

    public function __construct(SocketServer $server, array $config = [])
    {
        $this->server = $server;

        if (array_key_exists('maxConnections', $config)) {
            $this->maxConnections = $config['maxConnections'];
        }

        if (array_key_exists('maxConnectionsPerIp', $config)) {
            $this->maxConnectionsPerIp = $config['maxConnectionsPerIp'];
        }

        if (array_key_exists('maxRequestsPerMinute', $config)) {
            $this->maxRequestsPerMinute = $config['maxRequestsPerMinute'];
        }
    }

    public function create(SocketKeeper $socket): ?Connection
    {
        if (count($this->connections) >= $this->maxConnections) {
            return null;
        }

        $connection = $this->createConnection($socket);
        if (!$this->checkMaxConnectionsPerIp($connection->getClientIp())) {
            return null;
        }

        $this->connections[$socket->getId()] = $connection;
        $this->addIpToStorage($connection->getClientIp());
        return $connection;
    }

    /**
     * @param resource $resource
     * @return bool
     */
    public function has($resource): bool
    {
        return array_key_exists(SocketKeeper::getResourceId($resource), $this->connections);
    }

    /**
     * @param resource $resource
     * @return Connection|null
     */
    public function get($resource): ?Connection
    {
        if ($this->has($resource)) {
            $connection = $this->connections[SocketKeeper::getResourceId($resource)];
            if (!is_object($connection)) {
                unset($this->connections[SocketKeeper::getResourceId($resource)]);
                return null;
            }

            return $connection;
        }

        return null;
    }

    public function remove(Connection $connection): void
    {
        $connectionId = $connection->getId();
        $socket = $connection->getClientSocket();
        $clientIp = $connection->getClientIp();
        $clientPort = $connection->getClientPort();

        $this->removeIpFromStorage($clientIp);
        if (isset($this->requestStorage[$connectionId])) {
            unset($this->requestStorage[$connectionId]);
        }
        unset($this->connections[$socket->getId()]);

        $this->server->removeSocket($socket);
        unset($connection, $socket, $connectionId, $clientIp, $clientPort);
    }

    public function checkRequestLimit(Connection $connection): bool
    {
        if ($connection->isWaitingForData()) {
            return true;
        }
        
        $connectionId = $connection->getId();

        if (!array_key_exists($connectionId, $this->requestStorage)) {
            $this->requestStorage[$connectionId] = array(
                'lastRequest' => time(),
                'totalRequests' => 1
            );
            return true;
        }

        if (time() - $this->requestStorage[$connectionId]['lastRequest'] > 60) {
            $this->requestStorage[$connectionId] = array(
                'lastRequest' => time(),
                'totalRequests' => 1
            );
            return true;
        }

        if ($this->requestStorage[$connectionId]['totalRequests'] >= $this->maxRequestsPerMinute) {
            return false;
        }

        $this->requestStorage[$connectionId]['totalRequests']++;
        return true;
    }


    /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
     * PRIVATE
     * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

    private function createConnection(SocketKeeper $socket): Connection
    {
        return new Connection($this->server, $socket);
    }

    private function checkMaxConnectionsPerIp(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }
        if (!array_key_exists($ip, $this->ipStorage)) {
            return true;
        }
        return ($this->ipStorage[$ip] >= $this->maxConnectionsPerIp) ? false : true;
    }

    private function addIpToStorage(string $ip): void
    {
        if (array_key_exists($ip, $this->ipStorage)) {
            $this->ipStorage[$ip]++;
        } else {
            $this->ipStorage[$ip] = 1;
        }
    }

    private function removeIpFromStorage(string $ip): bool
    {
        if (!array_key_exists($ip, $this->ipStorage)) {
            return false;
        }
        if ($this->ipStorage[$ip] === 1) {
            unset($this->ipStorage[$ip]);
            return true;
        }
        $this->ipStorage[$ip]--;

        return true;
    }
}
