<?php

namespace lx\socket\Channel;

use lx;
use Exception;
use InvalidArgumentException;
use lx\ObjectTrait;
use lx\socket\Connection;
use RuntimeException;

abstract class Channel implements ChannelInterface
{
    use ObjectTrait;

    /** @var string */
    protected $name;

    /** @var array&Connection[] */
    protected $connections = [];

    /** @var ChannelEventListenerInterface */
    protected $eventListener;
    
    /** @var array */
    protected $metaData = [];

    /** @var null|string */
    protected $password = null;

    /** @var bool */
    protected $isClosed = false;

    /**
     * Channel constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->__objectConstruct($config);

        if ($this->eventListener) {
            $this->eventListener->setChannel($this);
        }

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

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param Connection $connection
     * @param mixed $authData
     * @return bool;
     */
    public function checkAuthData($connection, $authData)
    {
        if ($this->requirePassword()) {

            return $this->checkPassword($authData);
        }

        return true;
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
            $connection->close(1001);
        }

        $this->isClosed = true;
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
    
    /**
     * @param Connection $connection
     */
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

    /**
     * @param Connection $connection
     */
    public function onDisconnect(Connection $connection): void
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

    /**
     * @param ChannelMessage $message
     */
    public function onMessage($message): void
    {
        $this->sendMessage($message);
    }

    /**
     * @param ChannelMessage $message
     */
    public function sendMessage($message)
    {
        $receivers = $message->getReceivers();
        foreach ($receivers as $id => $receiver) {
            $receiver->send($message->getDataForConnection($id));
        }
    }

    /**
     * @param ChannelEvent $event
     */
    public function onEvent($event): void
    {
        if (!isset($this->eventListener)) {
            return;
        }

        if ($this->eventListener->processEvent($event) === false) {
            return;
        }

        $this->sendEvent($event);
    }

    /**
     * @param ChannelEvent $event
     */
    public function sendEvent($event)
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
