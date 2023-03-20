<?php

namespace lx\socket\channel;

interface ChannelEventListenerInterface
{
    public function setChannel(ChannelInterface $channel): void;
    public function getChannel(): ChannelInterface;
    public function processAnyEvent(ChannelEvent $event): bool;
    public function beforeProcessEvent(ChannelEvent $event): bool;
    public function processEvent(ChannelEvent $event): bool;
}
