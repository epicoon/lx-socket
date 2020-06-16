<?php

namespace lx\socket\serverTools;

use lx\socket\Channel\ChannelInterface;
use lx\StringHelper;
use RuntimeException;

/**
 * Class ChannelRepository
 * @package lx\socket
 */
class ChannelRepository
{
    /** @var array */
    private $channels = [];

    /**
     * @return array
     */
    public function getChannelNames(): array
    {
        return array_keys($this->channels);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        return array_key_exists($key, $this->channels);
    }

    /**
     * @param string $key
     * @return ChannelInterface
     */
    public function get(string $key): ChannelInterface
    {
        if ($this->has($key) === false) {
            throw new RuntimeException('Unknown channel requested.');
        }

        return $this->channels[$key];
    }

    /**
     * @param string $channelName
     * @param string $channelClassName
     * @param array $config
     * @return ChannelInterface|null
     */
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
        $channel = \lx::$app->diProcessor->create($channelClassName, $config);
        $this->channels[$channelName] = $channel;

        return $channel;
    }

    /**
     * @param string $channelName
     */
    public function close(string $channelName)
    {
        if (!$this->has($channelName)) {
            return;
        }

        $this->get($channelName)->close();
        unset($this->channels[$channelName]);
    }
}
