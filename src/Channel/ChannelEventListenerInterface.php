<?php

namespace lx\socket\Channel;

/**
 * Interface ChannelEventListenerInterface
 * @package lx\socket\Channel
 */
interface ChannelEventListenerInterface
{
    /**
     * @param ChannelInterface $channel
     * @return void
     */
    public function setChannel($channel);

    /**
     * @return ChannelInterface
     */
    public function getChannel();

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
