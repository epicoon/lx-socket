<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

/**
 * Class ChannelQuestion
 * @package lx\socket\Channel
 */
class ChannelQuestion extends ChannelMessage
{
    /** @var string */
    private $name;

    /** @var mixed */
    private $number;

    /**
     * ChannelQuestion constructor.
     * @param array $data
     * @param Channel $channel
     * @param Connection $initiator
     */
    public function __construct($data, $channel, $initiator = null)
    {
        parent::__construct($data, $channel, $initiator);

        $this->name = $data['__metaData__']['__question__']['name'];
        $this->number = $data['__metaData__']['__question__']['number'];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $connectionId
     * @return array
     */
    public function getDataForConnection($connectionId)
    {
        $result = parent::getDataForConnection($connectionId);
        $result['__answer__'] = $this->number;

        return $result;
    }
}
