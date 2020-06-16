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
    }
    
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getSubEvents()
    {
        return $this->subEvents;
    }

    /**
     * @param string $eventName
     * @param mixed $eventData
     */
    public function addSubEvent($eventName, $eventData)
    {
        $event = new self($eventName, $eventData, $this->getChannel(), $this->getInitiator());
        $event->receivers = $this->receivers;
        $event->private = $this->private;
        $this->subEvents[] = $event;
        return $event;
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
     * @param string $connectionId
     * @return array
     */
    public function getDataForConnection($connectionId)
    {
        $result = parent::getDataForConnection($connectionId);
        $result['__event__'] = $this->getName();

        return $result;
    }
}
