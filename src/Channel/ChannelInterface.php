<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

interface ChannelInterface
{
    public function getName(): string;
    public function isReconnectionAllowed(): bool;
    public function getEventListener(): ?ChannelEventListenerInterface;

    public function timerOn(): void;
    public function timerOff(): void;
    public function getTimer(): float;
    
    public function onConnect(Connection $connection): void;
    public function onReconnect(Connection $connection): void;
    public function onDisconnect(Connection $connection): void;
    public function onLeave(Connection $connection): void;

    public function onIteration(): void;

    public function onMessage(ChannelMessage $message): void;
    public function sendMessage(ChannelMessage $message): void;
    public function onEvent(ChannelEvent $event): void;
    public function sendEvent(ChannelEvent $event): void;
    public function createEvent(string $eventName, array $eventData = []): ChannelEvent;
    public function trigger(string $eventName, array $eventData = []): void;
    public function onRequest(ChannelRequest $request): void;

    public function checkOnConnect(Connection $connection, array $authData): bool;
    public function checkOnReconnect(Connection $connection, string $oldConnectionId, array $authData): bool;

    public function isClosed(): bool;
    public function close(): void;
    public function drop(): void;
    public function open(): void;

    public function setPassword(string $password): void;
    public function requirePassword(): bool;
    public function checkPassword(string $password): bool;
    public function getMetaData(): array;

    public function getData(): array;
    public function getConnectionsData(): array;
    /**
     * @return array<Connection>
     */
    public function getConnections(): array;
    /**
     * @return array<string>
     */
    public function getConnectionIds(): array;
    public function getConnectionsCount(): int;
    public function hasConnection(string $id): bool;
}
