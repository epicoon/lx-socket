<?php

namespace lx\socket\channel\request;

use lx\socket\Connection;
use lx\socket\channel\ChannelMessage;

class ChannelResponse extends ChannelMessage
{
    private string $key;

    public function initTransportData(ChannelRequest $request)
    {
        $this->key = $request->getKey();
        $this->setReceiver($request->getInitiator());
    }

    public function getDataForConnection(Connection $connection): array
    {
        $result = parent::getDataForConnection($connection);
        $result['__response__'] = $this->key;

        return $result;
    }
}
