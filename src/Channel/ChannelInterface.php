<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

/**
 * Interface ChannelInterface
 * @package lx\socket\Channel
 */
interface ChannelInterface
{
    /**
     * @param Connection $connection
     */
    public function onConnect(Connection $connection): void;

    /**
     * @param Connection $connection
     */
    public function onDisconnect(Connection $connection): void;

    /**
     * @param string $data
     * @param Connection $client
     */
    public function onMessage(string $data, Connection $client): void;

    /**
     * @return array
     */
    public function getMetaData();

    /**
     * @return array
     */
    public function getConnections();

    /**
     * @return array
     */
    public function getConnectionIds();

    /**
     * @return int
     */
    public function getConnectionsCount();
}
