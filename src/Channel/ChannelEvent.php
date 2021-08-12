<?php

namespace lx\socket\Channel;

use lx;
use lx\socket\Connection;

class ChannelEvent extends ChannelMessage
{
    protected string $name;
    protected bool $isStopped;
    protected array $subEvents;

    public function __construct(string $name, array $data, Channel $channel, ?Connection $initiator = null)
    {
        parent::__construct($data, $channel, $initiator);

        $this->name = $name;
        $this->isStopped = false;

        $this->subEvents = [];
        $this->isAsync = true;
    }

    public function send(): void
    {
        $this->getChannel()->sendEvent($this);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function replaceEvent(string $eventName, array $eventData = []): ChannelEvent
    {
        $this->name = $eventName;
        $this->data = $eventData;
        return $this;
    }

    public function addSubEvent(string $eventName, array $eventData = []): ChannelEvent
    {
        $event = new self($eventName, $eventData, $this->getChannel(), $this->getInitiator());
        $event->receivers = $this->receivers;
        $event->private = $this->private;
        $this->subEvents[] = $event;
        return $event;
    }

    public function setAsync($value): ChannelEvent
    {
        $this->isAsync = $value;
        return $this;
    }

    /**
     * @return array<ChannelEvent>
     */
    public function getSubEvents(): array
    {
        return $this->subEvents;
    }

    public function stop(): void
    {
        $this->isStopped = true;
    }

    public function isStopped(): bool
    {
        return $this->isStopped;
    }

    public function isMultiple(): bool
    {
        return !empty($this->subEvents);
    }

    public function isAsync(): bool
    {
        return $this->isAsync;
    }

    public function getDataForConnection(Connection $connection): array
    {
        if ($this->isMultiple() && !$this->isAsync()) {
            $result = [
                '__multipleEvents__' => [
                    array_merge(
                        parent::getDataForConnection($connection),
                        ['__event__' => $this->getName()]
                    )
                ],
            ];
            foreach ($this->getSubEvents() as $subEvent) {
                $result['__multipleEvents__'][] = array_merge(
                    $subEvent->getDataForConnection($connection),
                    ['__event__' => $subEvent->getName()]
                );
            }
            return $result;
        }

        $result = parent::getDataForConnection($connection);
        $result['__event__'] = $this->getName();
        return $result;
    }

    /**
     * @param mixed $data
     */
    public function dump($data): void
    {
        $this->getInitiator()->send([
            '__dump__' => lx::getDumpString($data),
        ]);
    }
}
