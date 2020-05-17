<?php

namespace lx\socket\serverTools;

use lx\socket\Channel\ChannelInterface;
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
     * @param string $key
     * @return bool
     */
    public function has(string $key) : bool
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
    public function get(string $key) : ChannelInterface
    {
        if ($this->has($key) === false) {
            throw new RuntimeException('Unknown channel requested.');
        }

        return $this->channels[$key];
    }


    /**
     * @param string $key
     * @param ChannelInterface $channel
     * @return void
     */
    public function set(string $key, ChannelInterface $channel) : void
    {
        $this->channels[$key] = $channel;
    }
}
