<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

class ChannelRequest extends ChannelMessage
{
    private string $route;
    private string $key;

    public function __construct(array $data, Channel $channel, Connection $initiator)
    {
        parent::__construct($data, $channel, $initiator);

        $this->route = $data['__metaData__']['__request__']['route'];
        $this->key = $data['__metaData__']['__request__']['key'];
        $this->setReceiver($initiator);
    }

    public function getRoute(): string
    {
        return $this->route;
    }
    
    public function getKey(): string
    {
        return $this->key;
    }
}
