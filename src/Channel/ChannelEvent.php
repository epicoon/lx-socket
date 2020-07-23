<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

/**
 * Class ChannelEvent
 * @package lx\socket\Channel
 */
class ChannelEvent extends ChannelMessage
{
    /** @var string */
    protected $name;

    /** @var bool */
    protected $isStopped;

    /** @var array */
    protected $subEvents;

    /**
     * ChannelEvent constructor.
     * @param string $name
     * @param array $data
     * @param Channel $channel
     * @param Connection|null $initiator
     */
    public function __construct($name, $data, $channel, $initiator = null)
    {
        parent::__construct($data, $channel, $initiator);

        $this->name = $name;
        $this->isStopped = false;

        $this->subEvents = [];
        $this->isAsync = true;
    }
    
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $eventName
     * @param mixed $eventData
     * @return ChannelEvent
     */
    public function replaceEvent($eventName, $eventData)
    {
        $this->name = $eventName;
        $this->data = $eventData;
        return $this;
    }

    /**
     * @param string $eventName
     * @param mixed $eventData
     * @return ChannelEvent
     */
    public function addSubEvent($eventName, $eventData)
    {
        $event = new self($eventName, $eventData, $this->getChannel(), $this->getInitiator());
        $event->receivers = $this->receivers;
        $event->private = $this->private;
        $this->subEvents[] = $event;
        return $event;
    }

    /**
     * @param bool $value
     */
    public function setAsync($value)
    {
        $this->isAsync = $value;
    }

    /**
     * @return ChannelEvent[]
     */
    public function getSubEvents()
    {
        return $this->subEvents;
    }

    public function stop()
    {
        $this->isStopped = true;
    }

    /**
     * @return bool
     */
    public function isStopped()
    {
        return $this->isStopped;
    }

    /**
     * @return bool
     */
    public function isMultiple()
    {
        return !empty($this->subEvents);
    }

    /**
     * @return bool
     */
    public function isAsync()
    {
        return $this->isAsync;
    }

    /**
     * @param string $connectionId
     * @return array
     */
    public function getDataForConnection($connectionId)
    {
        if ($this->isMultiple() && !$this->isAsync()) {
            $result = [
                '__multipleEvents__' => [
                    array_merge(
                        parent::getDataForConnection($connectionId),
                        ['__event__' => $this->getName()]
                    )
                ],
            ];
            foreach ($this->getSubEvents() as $subEvent) {
                $result['__multipleEvents__'][] = array_merge(
                    $subEvent->getDataForConnection($connectionId),
                    ['__event__' => $subEvent->getName()]
                );
            }
            return $result;
        }

        $result = parent::getDataForConnection($connectionId);
        $result['__event__'] = $this->getName();
        return $result;
    }

    /**
     * @param mixed $data
     */
    public function dump($data)
    {
        ob_start();
        var_dump($data);
        $out = ob_get_clean();
        $this->getInitiator()->send([
            '__dump__' => $out,
        ]);
    }
}
