<?php

namespace lx\socket\Channel;

use lx\StringHelper;

class ChannelEventListener implements ChannelEventListenerInterface
{
    private ChannelInterface $channel;

    public function getAvailableEventNames(): array
    {
        return [];
    }

    public function setChannel(ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getChannel(): ChannelInterface
    {
        return $this->channel;
    }

    public function processAnyEvent(ChannelEvent $event): bool
    {
        if (in_array($event->getName(), $this->getAvailableEventNames())) {
            return true;
        }
        
        $eventName = StringHelper::snakeToCamel('on-' . $event->getName(), ['_', '-']);
        if (method_exists($this, $eventName)) {
            $result = $this->$eventName($event);
        } else {
            $result = $this->processEvent($event);
        }

        if ($result === null) {
            $result = true;
        }
        return (bool)$result;
    }

    public function processEvent(ChannelEvent $event): bool
    {
        $event->replaceEvent('error', [
            'message' => 'Unknown event'
        ]);

        return true;
    }
}
