<?php

namespace lx\socket\Channel;

use lx\socket\Connection;

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
