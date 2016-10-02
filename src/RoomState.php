<?php

namespace Stcp;

use Amp\Socket\Client as SocketClient;
use Stcp\Event\Event;

class RoomState
{
    /**
     * @var string[]
     */
    private $clients;

    /**
     * @var SocketClient[]
     */
    private $sockets;

    public function __construct()
    {
        $this->clients = [];
        $this->sockets = [];
    }

    public function send(Event $event)
    {
        $message = pack("CC", Client::FRAME_ROOMEVENT, Event::$types[get_class($event)]) . $event->encode();
        echo "Sending event: " . bin2hex($message) . "\n";

        $promises = [];
        foreach ($this->sockets as $socket) {
            $promises[] = $socket->write($message);
        }

        return \amp\any($promises);
    }

    public function addReceiver(string $username, SocketClient $client)
    {
        $this->clients[] = $username;
        $this->sockets[$username] = $client;
    }

    public function removeReceiver(string $username)
    {
        if (isset($this->sockets[$username])) {
            unset($this->sockets[$username]);
        }
        $this->clients = array_filter($this->clients, function ($u1) use ($username) {
            return $u1 !== $username;
        });
    }

    /**
     * @return string[]
     */
    public function listClients()
    {
        return $this->clients;
    }
}
