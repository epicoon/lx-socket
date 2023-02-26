<?php

namespace lx\socket\channel\request;

use lx\socket\channel\ChannelInterface;

class ChannelRequestHandler implements ChannelRequestHandlerInteface
{
    private ChannelInterface $channel;

    public function setChannel(ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getChannel(): ChannelInterface
    {
        return $this->channel;
    }

    public function handleRequest(ChannelRequest $request): ?ChannelResponse
    {
        //TODO
//        return new ChannelResponse($data, $this->getChannel());

        return null;
    }
}
