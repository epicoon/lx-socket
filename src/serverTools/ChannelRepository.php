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
     * @param array $metaData
     * @return bool
     */
    public function create(string $channelName, string $channelClassName, array $metaData = [])
    {
        if ($this->has($channelName)) {
            //TODO log
            return false;
        }

        if (!is_subclass_of($channelClassName, ChannelInterface::class)) {
            //TODO log
            return false;
        }

        $this->channels[$channelName] = new $channelClassName($metaData);

        return true;
    }
}
