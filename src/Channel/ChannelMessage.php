<?php

namespace lx\socket\Channel;

use lx\ArrayHelper;
use lx\socket\Connection;

/**
 * Class ChannelMessage
 * @package lx\socket\Channel
 */
class ChannelMessage
{
    /** @var string|array */
    protected $data;

    /** @var array */
    protected $dataForConnections;

    /** @var array */
    protected $receivers;

    /** @var bool */
    protected $returnToSender;

    /** @var bool */
    protected $private;

    /** @var Channel */
    protected $channel;

    /** @var Connection|null */
    protected $initiator;

    /**
     * ChannelEvent constructor.
     * @param string|array $data
     * @param Channel $channel
     * @param Connection|null $initiator
     */
    public function __construct($data, $channel, $initiator = null)
    {
        if (array_key_exists('__data__', $data) && array_key_exists('__metaData__', $data)) {
            $this->data = $data['__data__'];
            $this->receivers = $data['__metaData__']['receivers'] ?? [];
            $this->private = $data['__metaData__']['private'] ?? false;
        } else {
            $this->data = $data;
            $this->receivers = [];
            $this->private = false;
        }

        $this->dataForConnections = [];
        $this->returnToSender = true;

        $this->channel = $channel;
        $this->initiator = $initiator;
    }

    /**
     * @return Channel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return Connection
     */
    public function getInitiator()
    {
        return $this->initiator;
    }

    /**
     * @return array
     */
    public function getReceivers()
    {
        $connections = $this->getChannel()->getConnections();
        if (empty($this->receivers)) {
            $receivers = $connections;
        } else {
            $receivers = [];
            foreach ($this->receivers as $receiverId) {
                $receivers[$receiverId] = $connections[$receiverId];
            }
        }

        if ($this->isReturnToSender()) {
            if (!array_key_exists($this->initiator->getId(), $receivers)) {
                $receivers[$this->initiator->getId()] = $this->initiator;
            }
        } else {
            if ($this->initiator && array_key_exists($this->initiator->getId(), $receivers)) {
                unset($receivers[$this->initiator->getId()]);
            }
        }

        return $receivers;
    }

    /**
     * @param array|null $receiverIds
     */
    public function setReceivers($receiverIds)
    {
        $this->receivers = $receiverIds;
    }

    /**
     * @return bool
     */
    public function isPrivate()
    {
        return $this->private;
    }

    /**
     * @return string|array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @param array $data
     */
    public function addData($data)
    {
        $this->data = ArrayHelper::mergeRecursiveDistinct($this->data, $data, true);
    }

    /**
     * @param bool $bool
     */
    public function setReturnToSender($bool)
    {
        $this->returnToSender = $bool;
    }

    /**
     * @return bool
     */
    public function isReturnToSender()
    {
        if (!$this->initiator) {
            return false;
        }

        return $this->returnToSender;
    }

    /**
     * @param string $connectionId
     * @param array $data
     */
    public function setDataForConnection($connectionId, $data)
    {
        $this->dataForConnections[$connectionId] = $data;
    }

    /**
     * @param string $connectionId
     * @return array
     */
    public function getDataForConnection($connectionId)
    {
        $result = [
            'data' => $this->getData(),
            'private' => $this->isPrivate(),
            'from' => $this->getInitiator() ? $this->getInitiator()->getId() : null,
            'receivers' => $this->receivers,
        ];

        if (array_key_exists($connectionId, $this->dataForConnections)) {
            $result['data'] += $this->dataForConnections[$connectionId];
        }

        $result['toMe'] = in_array($connectionId, $this->receivers);

        return $result;
    }
}
