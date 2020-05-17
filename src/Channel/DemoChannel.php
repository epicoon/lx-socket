<?php

namespace lx\socket\Channel;

use lx\socket\Connection;
use RuntimeException;

class DemoChannel extends Channel
{
    /**
     * @var array $clients
     */
    private $clients = [];

    /**
     * Handles new connections to the channel.
     *
     * @param Connection $client
     * @return void
     */
    public function onConnect(Connection $client): void
    {
        $id = $client->getId();
        $this->clients[$id] = $client;
    }

    /**
     * Handles client disconnects.
     *
     * @param Connection $client
     * @return void
     */
    public function onDisconnect(Connection $client): void
    {
        $id = $client->getId();
        unset($this->clients[$id]);
    }

    /**
     * Handles incoming data/requests.
     * If valid action is given the according method will be called.
     *
     * @param string $data
     * @param Connection $client
     * @return void
     */
    public function onData(string $data, Connection $client): void
    {
        try {
            $decodedData = $this->decodeData($data);
            $actionName = 'action' . ucfirst($decodedData['action']);
            if (method_exists($this, $actionName)) {
                call_user_func([$this, $actionName], $decodedData['data']);
            }
        } catch (RuntimeException $e) {
            // @todo Handle/Log error
        }
    }

    /**
     * Echoes data back to client(s).
     *
     * @param string $text
     * @return void
     */
    private function actionEcho(string $text): void
    {
        $encodedData = $this->encodeData('echo', $text);
        foreach ($this->clients as $sendto) {
            $sendto->send($encodedData);
        }
    }
}
