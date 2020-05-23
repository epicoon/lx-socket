<?php

namespace lx\socket\Channel;

use InvalidArgumentException;
use lx\socket\Connection;
use RuntimeException;

abstract class Channel implements ChannelInterface
{
    /** @var array&Connection[] */
    protected $connections = [];

    /** @var array */
    protected $metaData = [];

    /**
     * Channel constructor.
     * @param array $metaData
     */
    public function __construct($metaData = [])
    {
        $this->metaData = $metaData;
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    /**
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * @return array
     */
    public function getConnectionIds()
    {
        return array_keys($this->connections);
    }

    /**
     * @return int
     */
    public function getConnectionsCount()
    {
        return count($this->connections);
    }

    /**
     * @param Connection $connection
     */
    public function onConnect(Connection $connection): void
    {
        $id = $connection->getId();
        $this->connections[$id] = $connection;
    }

    /**
     * @param Connection $connection
     */
    public function onDisconnect(Connection $connection): void
    {
        $id = $connection->getId();
        unset($this->connections[$id]);
    }










    //TODO!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    /**
     * @param string $data
     * @throws RuntimeException
     * @return array
     */
    protected function decodeData(string $data): array
    {
        $decodedData = json_decode($data, true);
        if (empty($decodedData)) {
            throw new RuntimeException('Could not decode data.');
        }

//        if (isset($decodedData['action'], $decodedData['data']) === false) {
//            throw new RuntimeException('Decoded data is invalid.');
//        }

        return $decodedData;
    }

    /**
     * @param string $action
     * @param mixed $data
     * @throws InvalidArgumentException
     * @return string
     */
    protected function encodeData(string $action, $data): string
    {
        if (empty($action)) {
            throw new InvalidArgumentException('Action can not be empty.');
        };

        return json_encode([
            'action' => $action,
            'data' => $data
        ]);
    }









}
