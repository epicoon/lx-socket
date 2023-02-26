<?php

namespace lx\socket\serverTools;

use lx\socket\channel\ChannelInterface;
use lx\StringHelper;
use RuntimeException;

class ChannelRepository
{
    private array $channels = [];
    private array $onTimer = [];

    public function getChannelNames(): array
    {
        return array_keys($this->channels);
    }

    /**
     * @return array<ChannelInterface>
     */
    public function getOnTimer(): array
    {
        return $this->onTimer;
    }
    
    public function channelToTimer(ChannelInterface $channel): void
    {
        if (!$this->has($channel->getName())) {
            return;
        }
        
        $this->onTimer[$channel->getName()] = $channel;
    }
    
    public function channelFromTimer(ChannelInterface $channel): void
    {
        unset($this->onTimer[$channel->getName()]);
    }

    public function has(string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        return array_key_exists($key, $this->channels);
    }

    public function get(string $channelName): ChannelInterface
    {
        if ($this->has($channelName) === false) {
            throw new RuntimeException('Unknown channel requested.');
        }

        return $this->channels[$channelName];
    }

    public function create(string $channelName, string $channelClassName, array $config = []) : ?ChannelInterface
    {
        if ($this->has($channelName)) {
            //TODO log
            return null;
        }

        if (!is_subclass_of($channelClassName, ChannelInterface::class)) {
            //TODO log
            return null;
        }

        $config['name'] = $channelName;
        $channel = \lx::$app->diProcessor->create($channelClassName, [$config]);
        $this->channels[$channelName] = $channel;

        return $channel;
    }

    public function close(string $channelName)
    {
        if (!$this->has($channelName)) {
            return;
        }

        $this->get($channelName)->close();
        unset($this->channels[$channelName]);
        unset($this->onTimer[$channelName]);
    }
}
