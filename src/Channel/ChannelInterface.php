<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

interface ChannelInterface
{
    /**
     * This method is tirggered when a new client connects to server/channel.
     *
     * @param Connection $connection
     */
    public function onConnect(Connection $connection): void;

    /**
     * This methods is triggered when a client disconnects from server/channel.
     *
     * @param Connection $connection
     */
    public function onDisconnect(Connection $connection): void;

    /**
     * This method is triggered when the server receives new data.
     *
     * @param string $data
     * @param Connection $client
     */
    public function onData(string $data, Connection $client): void;

    /**
     * Creates and returns a new instance of the channel.
     *
     * @return ChannelInterface
     */
    public static function getInstance(): ChannelInterface;
}
