<?php

namespace Stcp;

use Amp\Socket\Server as SocketServer;
use Stcp\Authentication\AuthenticationInterface;

class Server
{
    /**
     * @var SocketServer
     */
    private $socketServer;

    /**
     * @var RoomState
     */
    private $roomState;

    /**
     * @var AuthenticationInterface
     */
    private $authentication;

    public function __construct(SocketServer $server, RoomState $roomState, AuthenticationInterface $authentication)
    {
        $this->socketServer = $server;
        $this->roomState = $roomState;
        $this->authentication = $authentication;
    }

    public function loop()
    {
        while ($socketClient = (yield $this->socketServer->accept())) {
            $client = new Client($socketClient, $this->authentication, $this->roomState);
            \amp\resolve($client->loop());
        }
    }
}
