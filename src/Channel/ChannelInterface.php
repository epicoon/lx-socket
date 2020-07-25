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
     * @return string
     */
    public function getName();

    /**
     * @param Connection $connection
     */
    public function onConnect(Connection $connection): void;

    /**
     * @param Connection $connection
     */
    public function onDisconnect(Connection $connection): void;

    /**
     * @param ChannelMessage $message
     */
    public function onMessage($message): void;

    /**
     * @param ChannelMessage $message
     */
    public function sendMessage($message);

    /**
     * @param ChannelEvent $event
     */
    public function onEvent($event): void;

    /**
     * @param ChannelEvent $event
     */
    public function sendEvent($event);

    /**
     * @param string $eventName
     * @param array $eventData
     */
    public function createEvent($eventName, $eventData);

    /**
     * @param string $eventName
     * @param array $eventData
     */
    public function trigger($eventName, $eventData = []);

    /**
     * @param ChannelQuestion $question
     */
    public function onQuestion($question);

    /**
     * @param Connection $connection
     * @param mixed $authData
     * @return bool;
     */
    public function checkAuthData($connection, $authData);

    /**
     * @return bool
     */
    public function isClosed();

    public function close();

    public function open();

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
    public function getData();

    /**
     * @return array
     */
    public function getConnectionsData();

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

    /**
     * @param string $id
     * @return bool
     */
    public function hasConnection($id);

    /**
     * For development only!
     *
     * @param string $requestForDump
     * @return string
     */
    public function onDump($requestForDump);

    /**
     * For development only!
     *
     * @param string $key
     * @return mixed|null
     */
    public function forDump($key);
}
