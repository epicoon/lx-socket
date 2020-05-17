<?php

namespace lx\socket;

use RuntimeException;

/**
 * Class Socket
 * @package lx\socket
 */
class Socket
{
    /** @var resource */
    private $resource;

    /** @var int */
    private $errorCode = 0;

    /** @var string */
    private $errorString = '';

    /**
     * @param string $host
     * @param int $port
     * @return Socket
     */
    public static function createMasterSocket(string $host, int $port) : Socket
    {
        $protocol = 'tcp://';
        $url = $protocol . $host . ':' . $port;
        $resource = stream_socket_server(
            $url,
            $errno,
            $err,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            stream_context_create()
        );

        $socket = new self($resource);
        if ($resource === false) {
            $socket->setError($errno, $err);
        }

        return $socket;
    }

    /**
     * @param Socket $masterSocket
     * @return Socket
     */
    public static function createClientSocket(Socket $masterSocket) : Socket
    {
        $resource = stream_socket_accept($masterSocket->getResource());
        $socket = new self($resource);
        if ($resource === false) {
            $errno = socket_last_error($resource);
            $err = socket_strerror($errno);
            $socket->setError($errno, $err);
        }

        return $socket;
    }

    public function __destruct()
    {
        unset($this->resource);
    }

    /**
     * @param resource $resource
     * @return int
     */
    public static function getResourceId($resource) : int
    {
        return (int)$resource;
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return self::getResourceId($this->resource);
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return stream_socket_get_name($this->resource, true);
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param resource $resource
     * @return bool
     */
    public function matchResource($resource) : bool
    {
        return $this->resource == $resource;
    }

    /**
     * @return bool
     */
    public function hasError() : bool
    {
        return $this->errorCode != 0 || $this->errorString != '';
    }

    /**
     * @return int
     */
    public function getErrorCode() : int
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getErrorString() : string
    {
        return $this->errorString;
    }

    /**
     * @throws RuntimeException
     * @return string
     */
    public function readBuffer(): string
    {
        $buffer = '';
        $buffSize = 8192;
        $metadata['unread_bytes'] = 0;
        do {
            if (feof($this->resource)) {
                throw new RuntimeException('Could not read from stream.');
            }
            $result = fread($this->resource, $buffSize);
            if ($result === false || feof($this->resource)) {
                throw new RuntimeException('Could not read from stream.');
            }
            $buffer .= $result;
            $metadata = stream_get_meta_data($this->resource);
            $buffSize = ($metadata['unread_bytes'] > $buffSize) ? $buffSize : $metadata['unread_bytes'];
        } while ($metadata['unread_bytes'] > 0);

        return $buffer;
    }

    /**
     * @param string $string
     * @return int
     */
    public function writeBuffer(string $string): int
    {
        if (!isset($this->resource) || !$this->resource) {
            return 0;
        }

        $stringLength = strlen($string);
        if ($stringLength === 0) {
            return 0;
        }

        for ($written = 0; $written < $stringLength; $written += $fWritten) {
            $fWritten = @fwrite($this->resource, substr($string, $written));
            if ($fWritten === false) {
                throw new RuntimeException('Could not write to stream.');
            }
            if ($fWritten === 0) {
                throw new RuntimeException('Could not write to stream.');
            }
        }

        return $written;
    }

    /**
     * @return bool
     */
    public function shutdown() : bool
    {
        $result = stream_socket_shutdown($this->resource, STREAM_SHUT_RDWR);
        if (!$result) {
            return false;
        }

        unset($this->resource);
        return true;
    }

    /**
     * Socket constructor.
     * @param resource $resource
     */
    private function __construct($resource = null)
    {
        $this->resource = $resource;
    }

    /**
     * @param int $errNo
     * @param string $errString
     */
    private function setError(int $errNo, string $errString)
    {
        $this->errorCode = $errNo;
        $this->errorString = $errString;
    }
}
