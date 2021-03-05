<?php

namespace lx\socket\Channel;

use lx\StringHelper;

/**
 * Class ChannelEventListener
 * @package lx\socket\Channel
 */
class ChannelEventListener implements ChannelEventListenerInterface
{
    /** @var ChannelInterface */
    private $channel;

    /**
     * @return array
     */
    public function getAvailableEventNames()
    {
        return [];
    }

    /**
     * @param ChannelInterface $channel
     * @return void
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return ChannelInterface
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param ChannelEvent $event
     * @return bool
     */
    public function processEvent($event)
    {
        if (in_array($event->getName(), $this->getAvailableEventNames())) {
            return true;
        }
        
        $eventName = StringHelper::snakeToCamel('on-' . $event->getName(), ['_', '-']);
        if (method_exists($this, $eventName)) {
            $result = $this->$eventName($event);
        } else {
            $result = $this->processEventDefault($event);
        }

        if ($result === null) {
            $result = true;
        }
        return $result;
    }

    /**
     * @param ChannelEvent $event
     * @return bool
     */
    public function processEventDefault($event)
    {
        $event->replaceEvent('error', [
            'message' => 'Unknown event'
        ]);

        return true;
    }
}
