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
     * @param mixed $data
     * @param Connection $client
     */
    public function onMessage($data, Connection $client): void;

    /**
     * @param string $password
     */
    public function setPassword($password);

    /**
     * @return bool
     */
    public function requirePassword();

    /**
     * @param string $password
     * @return bool
     */
    public function checkPassword($password);

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
     * @return array
     */
    public function getConnectionsData();

    /**
     * @return int
     */
    public function getConnectionsCount();
}
