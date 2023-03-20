<?php

namespace lx\socket\channel;

use lx\StringHelper;

class ChannelEventListener implements ChannelEventListenerInterface
{
    private ChannelInterface $channel;

    protected function getTransitEventNames(): array
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
        if (in_array($event->getName(), $this->getTransitEventNames())) {
            return true;
        }

        if (!$this->beforeProcessEvent($event)) {
            return false;
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

    public function beforeProcessEvent(ChannelEvent $event): bool
    {
        return true;
    }

    public function processEvent(ChannelEvent $event): bool
    {
        $event->replaceEvent('error', [
            'message' => 'Unknown event'
        ]);

        return true;
    }
}
