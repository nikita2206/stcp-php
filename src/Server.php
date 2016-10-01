<?php

namespace Stcp;

use Amp\Socket\Server as SocketServer;

class Server
{
    /**
     * @var SocketServer
     */
    private $socketServer;

    public function __construct(SocketServer $server)
    {
        $this->socketServer = $server;
    }

    public function loop()
    {
        while ($socketClient = (yield $this->socketServer->accept())) {
            $client = new Client($socketClient);
            \amp\resolve($client->loop());
        }
    }
}
