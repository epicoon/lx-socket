<?php

namespace lx\socket\Channel;

use lx;
use Exception;
use InvalidArgumentException;
use lx\ObjectTrait;
use lx\socket\Connection;
use RuntimeException;
use lx\Vector;

abstract class Channel implements ChannelInterface
{
    use ObjectTrait;

    /** @var string */
    protected $name;

    /** @var int */
    protected $reconnectionPeriod = 0;

    /** @var array<Connection> */
    protected $connections = [];

    /** @var Vector&iterable<string> */
    protected $formerConnectionIds;

    /** @var ChannelEventListenerInterface */
    protected $eventListener;

    /** @var array */
    protected $metaData = [];

    /** @var null|string */
    protected $password = null;

    /** @var bool */
    protected $isClosed = false;

    /** @var float */
    protected $timerStart = 0;

    public function __construct(array $config = [])
    {
        $this->__objectConstruct($config);

        if ($this->eventListener) {
            $this->eventListener->setChannel($this);
        }

        $this->formerConnectionIds = new Vector();
        $this->init();
    }

    public function init()
    {
        // pass
    }

    public static function getConfigProtocol(): array
    {
        return [
            'name' => true,
            'metaData' => true,
            'eventListener' => ChannelEventListener::class,
        ];
    }

    /**
     * @return ChannelEventListenerInterface
     */
    public function getEventListener()
    {
        return $this->eventListener;
    }

    public function timerOn(): void
    {
        /** @var lx\socket\SocketServer $app */
        $app = lx::$app;
        $app->channels->channelToTimer($this);
        $this->timerStart = microtime(true);
    }

    public function timerOff(): void
    {
        /** @var lx\socket\SocketServer $app */
        $app = lx::$app;
        $app->channels->channelFromTimer($this);
        $this->timerStart = 0;
    }

    public function getTimer(): float
    {
        return microtime(true) - $this->timerStart;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isReconnectionAllowed()
    {
        return $this->reconnectionPeriod != 0;
    }

    /**
     * @param Connection $connection
     * @param mixed $authData
     * @return bool;
     */
    public function checkOnConnect($connection, $authData)
    {
        if ($this->requirePassword()) {
            return $this->checkPassword($authData);
        }

        return true;
    }

    /**
     * @param Connection $connection
     * @param string $oldConnectionId
     * @param mixed $authData
     * @return bool;
     */
    public function checkOnReconnect($connection, $oldConnectionId, $authData)
    {
        if (!$this->formerConnectionIds->contains($oldConnectionId)) {
            return false;
        }

        $this->formerConnectionIds->remove($oldConnectionId);

        return $this->checkOnConnect($connection, $authData);
    }

    /**
     * @return bool
     */
    public function isClosed()
    {
        return $this->isClosed;
    }

    public function close()
    {
        if ($this->isClosed) {
            return;
        }

        foreach ($this->connections as $connection) {
            $connection->close(Connection::CLOSE_CODE_LEAVE);
        }

        $this->isClosed = true;
    }

    public function drop()
    {
        /** @var lx\socket\SocketServer $app */
        $app = lx::$app;
        $app->channels->close($this->getName());
    }

    public function open()
    {
        if (!$this->isClosed) {
            return;
        }

        $this->isClosed = false;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return bool
     */
    public function requirePassword()
    {
        return ($this->password !== null);
    }

    /**
     * @param string $password
     * @return bool
     */
    public function checkPassword($password)
    {
        return $this->password == $password;
    }

    /**
     * @return array
     */
    public function getMetaData()
    {
        $metaData = $this->metaData;
        $metaData['requirePassword'] = $this->requirePassword();
        return $metaData;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return [];
    }

    /**
     * @return array
     */
    public function getConnectionsData()
    {
        $result = [];
        foreach ($this->connections as $connection) {
            $result[$connection->getId()] = $connection->getChannelOpenData();
        }
        return $result;
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
     * @param string $id
     * @return bool
     */
    public function hasConnection($id)
    {
        return array_key_exists($id, $this->connections);
    }

    public function onConnect(Connection $connection): void
    {
        $id = $connection->getId();
        $this->connections[$id] = $connection;

        $clientData = $connection->getChannelOpenData();
        foreach ($this->connections as $otherConnection) {
            $otherConnection->send([
                '__lxws_event__' => 'clientJoin',
                'client' => $clientData,
            ]);
        }
    }

    public function onReconnect(Connection $connection): void
    {
        if (!$this->isReconnectionAllowed()) {
            return;
        }

        //TODO проверка на возможность переподсоединения?

        $id = $connection->getId();
        $this->connections[$id] = $connection;

        $clientData = $connection->getChannelOpenData();
        foreach ($this->connections as $otherConnection) {
            $otherConnection->send([
                '__lxws_event__' => 'clientReconnected',
                'client' => $clientData,
                'oldConnectionId' => $connection->getOldId(),
            ]);
        }
    }

    public function onDisconnect(Connection $connection): void
    {
        $id = $connection->getId();
        unset($this->connections[$id]);
        if ($this->isReconnectionAllowed() && !$this->formerConnectionIds->contains($id)) {
            $this->formerConnectionIds->push($id);
        }

        $clientData = $connection->getChannelOpenData();
        foreach ($this->connections as $otherConnection) {
            $otherConnection->send([
                '__lxws_event__' => 'clientDisconnected',
                'client' => $clientData,
            ]);
        }
    }

    public function onLeave(Connection $connection): void
    {
        $id = $connection->getId();
        unset($this->connections[$id]);

        $clientData = $connection->getChannelOpenData();
        foreach ($this->connections as $otherConnection) {
            $otherConnection->send([
                '__lxws_event__' => 'clientLeave',
                'client' => $clientData,
            ]);
        }
    }

    public function onIteration(): void
    {
        // pass
    }

    public function onMessage(ChannelMessage $message): void
    {
        $this->sendMessage($message);
    }

    public function sendMessage(ChannelMessage $message)
    {
        $receivers = $message->getReceivers();
        foreach ($receivers as $id => $receiver) {
            $receiver->send($message->getDataForConnection($id));
        }
    }

    public function onEvent(ChannelEvent $event): void
    {
        if (!isset($this->eventListener)) {
            return;
        }

        if ($this->eventListener->processEvent($event) === false) {
            return;
        }

        $this->sendEvent($event);
    }

    public function sendEvent(ChannelEvent $event)
    {
        if ($event->isStopped()) {
            return;
        }

        $this->sendMessage($event);

        if ($event->isMultiple() && $event->isAsync()) {
            $events = $event->getSubEvents();
            foreach ($events as $subEvent) {
                $this->sendMessage($subEvent);
            }
        }
    }

    /**
     * @param string $eventName
     * @param array|null $eventData
     */
    public function createEvent($eventName, $eventData = [])
    {
        return new ChannelEvent($eventName, $eventData, $this);
    }

    /**
     * @param string $eventName
     * @param array $eventData
     */
    public function trigger($eventName, $eventData = [])
    {
        $event = $this->createEvent($eventName, $eventData);
        $this->sendEvent($event);
    }

    /**
     * @param ChannelQuestion $question
     */
    public function onQuestion($question)
    {
        $this->sendMessage($question);
    }
}
