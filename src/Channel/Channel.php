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

    protected string $name;
    protected int $reconnectionPeriod = 0;
    /** @var array<Connection> */
    protected array $connections = [];
    /** @var Vector&iterable<string> */
    protected Vector $formerConnectionIds;
    protected ?ChannelEventListenerInterface $eventListener = null;
    protected array $parameters = [];
    protected ?string $password = null;
    protected bool $isClosed = false;
    protected float $timerStart = 0;

    public function __construct(array $config = [])
    {
        $this->__objectConstruct($config);

        if ($this->eventListener) {
            $this->eventListener->setChannel($this);
        }

        $this->formerConnectionIds = new Vector();
        $this->init();
    }

    public function init(): void
    {
        // pass
    }

    public static function getConfigProtocol(): array
    {
        return [
            'name' => true,
            'parameters' => true,
            'eventListener' => ChannelEventListener::class,
        ];
    }

    public function getEventListener(): ?ChannelEventListenerInterface
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

    public function getName(): string
    {
        return $this->name;
    }

    public function isReconnectionAllowed(): bool
    {
        return $this->reconnectionPeriod != 0;
    }

    public function checkOnConnect(Connection $connection, array $authData): bool
    {
        if ($this->requirePassword()) {
            return $this->checkPassword($authData['password'] ?? null);
        }

        return true;
    }

    public function checkOnReconnect(Connection $connection, string $oldConnectionId, array $authData): bool
    {
        if (!$this->formerConnectionIds->contains($oldConnectionId)) {
            return false;
        }

        $this->formerConnectionIds->remove($oldConnectionId);

        return $this->checkOnConnect($connection, $authData);
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }
    
    public function beforeClose(): void
    {
        // pass
    }

    public function close(): void
    {
        if ($this->isClosed) {
            return;
        }
        
        $this->beforeClose();

        foreach ($this->connections as $connection) {
            $connection->close(Connection::CLOSE_CODE_LEAVE);
        }

        $this->isClosed = true;
    }

    public function drop(): void
    {
        /** @var lx\socket\SocketServer $app */
        $app = lx::$app;
        $app->channels->close($this->getName());
    }

    public function open(): void
    {
        if (!$this->isClosed) {
            return;
        }

        $this->isClosed = false;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function requirePassword(): bool
    {
        return ($this->password !== null);
    }

    public function checkPassword(string $password): bool
    {
        return $this->password == $password;
    }

    public function getParameters(): array
    {
        $parameters = $this->parameters;
        $parameters['requirePassword'] = $this->requirePassword();
        return $parameters;
    }

    /**
     * @return mixed
     */
    public function getParameter(string $name)
    {
        return $this->parameters[$name] ?? null;
    }

    public function getData(): array
    {
        return [];
    }

    public function getConnectionsData(): array
    {
        $result = [];
        foreach ($this->connections as $connection) {
            $result[$connection->getId()] = $connection->getChannelOpenData();
        }
        return $result;
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function getConnectionIds(): array
    {
        return array_keys($this->connections);
    }

    public function getConnectionsCount(): int
    {
        return count($this->connections);
    }

    public function hasConnection(string $id): bool
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
        if (!array_key_exists($id, $this->connections)) {
            return;
        }
        
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

    public function sendMessage(ChannelMessage $message): void
    {
        $receivers = $message->getReceivers();
        foreach ($receivers as $receiver) {
            $receiver->send($message->getDataForConnection($receiver));
        }
    }

    public function onEvent(ChannelEvent $event): void
    {
        if ($this->eventListener === null) {
            return;
        }

        if ($this->eventListener->processAnyEvent($event) === false) {
            return;
        }

        $this->sendEvent($event);
    }

    public function sendEvent(ChannelEvent $event): void
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

    public function createEvent(string $eventName, array $eventData = []): ChannelEvent
    {
        return new ChannelEvent($eventName, $eventData, $this);
    }

    public function trigger(string $eventName, array $eventData = []): void
    {
        $event = $this->createEvent($eventName, $eventData);
        $this->sendEvent($event);
    }

    public function onRequest(ChannelRequest $request): void
    {
        $response = $this->handleRequest($request);
        $response->initTransportData($request);
        $this->sendMessage($response);
    }

    public function handleRequest(ChannelRequest $request): ChannelResponse
    {
        return $this->prepareResponse([]);
    }

    public function prepareResponse($data): ChannelResponse
    {
        return new ChannelResponse($data, $this);
    }
}
