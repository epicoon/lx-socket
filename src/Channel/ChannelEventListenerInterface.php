<?php

namespace lx\socket\Channel;

/**
 * Interface ChannelEventListenerInterface
 * @package lx\socket\Channel
 */
interface ChannelEventListenerInterface
{
    /**
     * @param ChannelEvent $event
     */
    public function processEvent($event);
}
