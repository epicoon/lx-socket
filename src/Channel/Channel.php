<?php

namespace lx\socket\Channel;

use InvalidArgumentException;
use RuntimeException;

abstract class Channel implements ChannelInterface
{
    /**
     * @var array $instances
     */
    protected static $instances = [];

    protected function __construct()
    {
        // singleton construct required this method to be protected/private
    }

    final private function __clone()
    {
        // singleton construct required this method to be protected/private
    }

    /**
     * Creates and returns new Channel object.
     *
     * @return ChannelInterface
     */
    final public static function getInstance(): ChannelInterface
    {
        $calledClassName = get_called_class();
        if (!isset(self::$instances[$calledClassName])) {
            self::$instances[$calledClassName] = new $calledClassName();
        }

        return self::$instances[$calledClassName];
    }

    /**
     * Decodes json data received from stream.
     *
     * @param string $data
     * @throws RuntimeException
     * @return array
     */
    protected function decodeData(string $data): array
    {
        $decodedData = json_decode($data, true);
        if (empty($decodedData)) {
            throw new RuntimeException('Could not decode data.');
        }

        if (isset($decodedData['action'], $decodedData['data']) === false) {
            throw new RuntimeException('Decoded data is invalid.');
        }

        return $decodedData;
    }

    /**
     * Encodes data to be send to client.
     *
     * @param string $action
     * @param mixed $data
     * @throws InvalidArgumentException
     * @return string
     */
    protected function encodeData(string $action, $data): string
    {
        if (empty($action)) {
            throw new InvalidArgumentException('Action can not be empty.');
        };

        return json_encode([
            'action' => $action,
            'data' => $data
        ]);
    }
}
