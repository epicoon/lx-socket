<?php

namespace lx\socket\Channel;

use lx\ArrayHelper;
use lx\socket\Connection;

class ChannelMessage
{
    /** @var string|array */
    protected $data;
    protected array $dataForConnections;
    protected array $receivers;
    protected bool $returnToSender;
    protected bool $private;
    protected Channel $channel;
    protected ?Connection $initiator;

    /**
     * ChannelEvent constructor.
     * @param string|array $data
     * @param Channel $channel
     * @param Connection|null $initiator
     */
    public function __construct($data, Channel $channel, ?Connection $initiator = null)
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
    
    public function send(): void
    {
        $this->getChannel()->sendMessage($this);
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getInitiator(): ?Connection
    {
        return $this->initiator;
    }

    /**
     * @return array<Connection>
     */
    public function getReceivers(): array
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

    public function isPrivate(): bool
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

    public function isReturnToSender(): bool
    {
        if (!$this->initiator) {
            return false;
        }

        return $this->returnToSender;
    }

    public function getDataForConnection(Connection $connection): array
    {
        $result = [
            'data' => $this->getData(),
            'private' => $this->isPrivate(),
            'from' => $this->getInitiator() ? $this->getInitiator()->getId() : null,
            'receivers' => $this->receivers,
        ];
        $connectionId = $connection->getId();

        if (array_key_exists($connectionId, $this->dataForConnections)) {
            $result['data'] += $this->dataForConnections[$connectionId];
        }

        $result['toMe'] = in_array($connectionId, $this->receivers);

        return $result;
    }

    public function setReceiver(Connection $receiver): ChannelMessage
    {
        $this->receivers = [$receiver->getId()];
        return $this;
    }

    public function addReceiver(Connection $receiver): ChannelMessage
    {
        if (!in_array($receiver->getId(), $this->receivers)) {
            $this->receivers[] = $receiver->getId();
        }
        return $this;
    }

    /**
     * @param array<Connection> $receivers
     */
    public function setReceivers(array $receivers): ChannelMessage
    {
        $this->receivers = [];
        $this->addReceivers($receivers);
        return $this;
    }

    /**
     * @param array<Connection> $receivers
     */
    public function addReceivers(array $receivers): ChannelMessage
    {
        foreach ($receivers as $receiver) {
            $this->addReceiver($receiver);
        }
        return $this;
    }
    
    /**
     * @param string|array $data
     */
    public function setData($data): ChannelMessage
    {
        $this->data = $data;
        return $this;
    }

    public function addData(array $data): ChannelMessage
    {
        $this->data = ArrayHelper::mergeRecursiveDistinct($this->data, $data, true);
        return $this;
    }

    public function setReturnToSender(bool $bool): ChannelMessage
    {
        $this->returnToSender = $bool;
        return $this;
    }

    public function setDataForConnection(Connection $connection, array $data): ChannelMessage
    {
        $this->dataForConnections[$connection->getId()] = $data;
        return $this;
    }

    public function addDataForConnection(Connection $connection, array $data, bool $rewrite = false): ChannelMessage
    {
        if (!array_key_exists($connection->getId(), $this->dataForConnections)) {
            $this->dataForConnections[$connection->getId()] = [];
        }
        
        $this->dataForConnections[$connection->getId()] = ArrayHelper::mergeRecursiveDistinct(
            $this->dataForConnections[$connection->getId()],
            $data,
            $rewrite
        );
        return $this;
    }
}
