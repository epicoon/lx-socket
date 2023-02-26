<?php

namespace lx\socket\channel;

use lx\socket\Connection;
use lx\socket\channel\request\ChannelRequest;
use lx\socket\channel\request\ChannelResponse;
use DateTime;

interface ChannelInterface
{
    public function getName(): string;
    public function createdAt(): DateTime;
    public function isReconnectionAllowed(): bool;
    public function getEventListener(): ?ChannelEventListenerInterface;

    public function timerOn(): void;
    public function timerOff(): void;
    public function getTimer(): float;

    public function onConnect(Connection $connection): void;
    public function onReconnect(Connection $connection): void;
    public function afterConnect(Connection $connection): void;
    public function onAddConnectionOpenData(Connection $connection, array $keys) : void;
    public function onDisconnect(Connection $connection): void;
    public function onLeave(Connection $connection): void;
    public function afterDisconnect(Connection $connection): void;

    public function onIteration(): void;

    public function onMessage(ChannelMessage $message): void;
    public function sendMessage(ChannelMessage $message): void;
    public function onEvent(ChannelEvent $event): void;
    public function sendEvent(ChannelEvent $event): void;
    public function createEvent(string $eventName, array $eventData = []): ChannelEvent;
    public function trigger(string $eventName, array $eventData = []): void;
    public function onRequest(ChannelRequest $request): void;
    public function handleRequest(ChannelRequest $request): ?ChannelResponse;
    /**
     * @param mixed $data
     */
    public function prepareResponse($data): ChannelResponse;

    public function checkOnConnect(Connection $connection, array $authData): bool;
    public function checkOnReconnect(Connection $connection, string $oldConnectionId): bool;

    public function isClosed(): bool;
    public function beforeClose(): void;
    public function close(): void;
    public function drop(): void;
    public function open(): void;

    public function setPassword(string $password): void;
    public function requirePassword(): bool;
    public function checkPassword(string $password): bool;
    public function getParameters(): array;
    /**
     * @return mixed
     */
    public function getParameter(string $name);

    public function getChannelData(Connection $connection): array;
    public function getConnectionsData(Connection $connection): array;
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
