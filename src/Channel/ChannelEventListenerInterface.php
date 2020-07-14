<?php

namespace lx\socket\Channel;

/**
 * Interface ChannelEventListenerInterface
 * @package lx\socket\Channel
 */
interface ChannelEventListenerInterface
{
    /**
     * @return array
     */
    public function getAvailableEventNames();

    /**
     * @param ChannelEvent $event
     * @return bool
     */
    public function processEvent($event);

    /**
     * @param ChannelEvent $event
     * @return bool
     */
    public function processEventDefault($event);
}
