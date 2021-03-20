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
     * @return bool
     */
    public function isReconnectionAllowed();

    /**
     * @return ChannelEventListenerInterface
     */
    public function getEventListener();

    public function timerOn(): void;
    public function timerOff(): void;
    public function getTimer(): float;
    
    public function onConnect(Connection $connection): void;
    public function onReconnect(Connection $connection): void;
    public function onDisconnect(Connection $connection): void;
    public function onLeave(Connection $connection): void;

    public function onIteration(): void;

    public function onMessage(ChannelMessage $message): void;
    public function sendMessage(ChannelMessage $message);
    public function onEvent(ChannelEvent $event): void;
    public function sendEvent(ChannelEvent $event);

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
    public function checkOnConnect($connection, $authData);

    /**
     * @param Connection $connection
     * @param mixed $authData
     * @param string $oldConnectionId
     * @return bool;
     */
    public function checkOnReconnect($connection, $oldConnectionId, $authData);

    /**
     * @return bool
     */
    public function isClosed();
    public function close();
    public function drop();
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
}
