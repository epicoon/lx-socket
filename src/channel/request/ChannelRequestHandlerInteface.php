<?php

namespace lx\socket\channel\request;

use lx\socket\channel\ChannelInterface;

interface ChannelRequestHandlerInteface
{
    public function setChannel(ChannelInterface $channel): void;
    public function getChannel(): ChannelInterface;
    public function handleRequest(ChannelRequest $request): ?ChannelResponse;
}
