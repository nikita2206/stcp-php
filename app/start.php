<?php

require_once __DIR__ . "/../vendor/autoload.php";

$socket = \Amp\Socket\listen("tcp://127.0.0.1:4224");
$socketServer = new \Amp\Socket\Server($socket);

$roomState = new \Stcp\RoomState();
$authentication = new \Stcp\Authentication\InMemoryAuthentication();

$server = new \Stcp\Server($socketServer, $roomState, $authentication);

\Amp\run([$server, "loop"]);
