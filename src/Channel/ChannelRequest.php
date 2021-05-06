<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

class ChannelRequest extends ChannelMessage
{
    private string $name;
    private string $number;

    public function __construct(array $data, Channel $channel, Connection $initiator)
    {
        parent::__construct($data, $channel, $initiator);

        $this->name = $data['__metaData__']['__request__']['name'];
        $this->number = $data['__metaData__']['__request__']['number'];
        $this->setReceivers($initiator);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDataForConnection(string $connectionId): array
    {
        $result = parent::getDataForConnection($connectionId);
        $result['__response__'] = $this->number;

        return $result;
    }
}
