<?php

namespace lx\socket\channel;

interface ChannelEventListenerInterface
{
    public function setChannel(ChannelInterface $channel): void;
    public function getChannel(): ChannelInterface;
    public function getAvailableEventNames(): array;
    public function processAnyEvent(ChannelEvent $event): bool;
    public function processEvent(ChannelEvent $event): bool;
}
