<?php

namespace Stcp;

use Amp\Failure;
use Amp\Socket\Client as SocketClient;
use Amp\Success;
use Stcp\Authentication\AuthenticationInterface;
use Stcp\Event\Event;

class Client
{
    const VERSION = 1;

    const S_BYTE = 1;
    const S_U8   = 1;
    const S_U16  = 2;
    const S_U64  = 8;

    const FRAME_HEY = 0x01;
    const FRAME_MYNAMEIS = 0x02;
    const FRAME_SUP = 0x03;
    const FRAME_REMEMBERME = 0x04;
    const FRAME_LISTCLIENTS = 0x05;
    const FRAME_MESSAGE = 0x06;
    const FRAME_LOGOUT = 0x07;

    const FRAME_SERVER_SUP = 0x02;
    const FRAME_NOPE = 0x03;
    const FRAME_USERERROR = 0x04;
    const FRAME_NEWTOKEN = 0x05;
    const FRAME_CLIENTS = 0x06;
    const FRAME_ROOMEVENT = 0x07;

    /**
     * @var SocketClient
     */
    private $socketClient;

    /**
     * @var string
     */
    private $username;

    /**
     * @var AuthenticationInterface
     */
    private $authentication;

    /**
     * @var RoomState
     */
    private $roomState;

    private $buffer;

    public function __construct(SocketClient $socketClient, AuthenticationInterface $authentication, RoomState $roomState)
    {
        $this->socketClient = $socketClient;
        $this->authentication = $authentication;
        $this->roomState = $roomState;
        $this->buffer = "";
    }

    public function loop()
    {
        echo "Entered into loop with new client\n";

        hey:
        $frame = ord(yield from $this->read(self::S_BYTE));
        if ($frame !== self::FRAME_HEY) {
            yield from $this->write($this->unexpectedFrame($frame));
            echo "He sent us a wrong frame\n";
            goto hey;
        }
        echo "Got a HEY frame\n";

        handshake:
        $version = (yield from $this->read(self::S_U64));
        echo "Got a version: " . unpack("J", $version)[1] . "\n";

        $response = pack("CJ", self::FRAME_HEY, self::VERSION);
        yield from $this->write($response);
        echo "Responded with a version, waiting for auth now\n";

        authentication:
        $frame = ord(yield from $this->read(self::S_BYTE));
        if ($frame === self::FRAME_HEY) {
            echo "Got a HEY frame\n";
            goto handshake;
        } elseif ($frame === self::FRAME_MYNAMEIS) {
            echo "Got a MYNAMEIS frame\n";
            goto my_name_is;
        } elseif ($frame === self::FRAME_SUP) {
            echo "Got a SUP frame\n";
            goto sup;
        } else {
            yield from $this->write($this->unexpectedFrame($frame));
            goto authentication;
        }

        my_name_is:
        $length = ord(yield from $this->read(self::S_U8));
        $username = (yield from $this->read($length));
        $length = ord(yield from $this->read(self::S_U8));
        $password = (yield from $this->read($length));

        if (yield $this->authentication->byUsernamePassword($username, $password)) {
            $this->username = $username;
            yield from $this->write(pack("C", self::FRAME_SERVER_SUP));
            goto room;
        } else {
            yield from $this->write(pack("C", self::FRAME_NOPE));
            goto authentication;
        }

        sup:
        $length = unpack("n", yield from $this->read(self::S_U16));
        $token = (yield from $this->read($length));

        if (null !== ($username = (yield $this->authentication->byToken($token)))) {
            $this->username = $username;
            yield from $this->write(pack("C", self::FRAME_SERVER_SUP));
            goto room;
        } else {
            yield from $this->write(pack("C", self::FRAME_NOPE));
            goto authentication;
        }

        room:
        echo "Authenticated\n";

        yield $this->roomState->send(Event::newClient($this->username));
        $this->roomState->addReceiver($this->username, $this->socketClient);

        while (true) {
            $frame = ord(yield from $this->read(1));

            if ($frame === self::FRAME_REMEMBERME) {
                $token = (yield $this->authentication->generateToken($this->username));
                $response = pack("Cn", self::FRAME_NEWTOKEN, strlen($token)) . $token;

                yield from $this->write($response);
            } elseif ($frame === self::FRAME_LISTCLIENTS) {
                echo "Got LISTCLIENTS frame\n";
                $clients = $this->roomState->listClients();

                $message = [pack("CJ", self::FRAME_CLIENTS, count($clients))];
                foreach ($clients as $client) {
                    $message[] = chr(strlen($client));
                    $message[] = $client;
                }

                yield from $this->write(implode("", $message));
            } elseif ($frame === self::FRAME_MESSAGE) {
                $length = unpack("J", yield from $this->read(self::S_U64))[1];
                $message = (yield from $this->read($length));

                yield $this->roomState->send(Event::message($this->username, $message));
            } elseif ($frame === self::FRAME_LOGOUT) {
                $this->roomState->removeReceiver($this->username);
                yield $this->roomState->send(Event::clientLeft($this->username));
                $this->username = null;
                goto authentication;
            } else {
                yield from $this->write($this->unexpectedFrame($frame));
            }
        }
    }

    public function unexpectedFrame($frame)
    {
        $code = 1;
        $message = "Unexpected frame: {$frame}";

        return pack("Cnn", self::FRAME_USERERROR, $code, strlen($message)) . $message;
    }

    private function read($n)
    {
        if ( ! $this->socketClient->alive()) {
            echo "Client is not alive anymore\n";
            goto cleanup;
        }

        if (strlen($this->buffer) >= $n) {
            $message = substr($this->buffer, 0, $n);
            $this->buffer = substr($this->buffer, $n);

            return (yield new Success($message));
        }

        do {
            $bytes = (yield $this->socketClient->read());

            if ($bytes === null || ! $this->socketClient->alive()) {
                if ($bytes === null) {
                    echo "Got a null message instead of ({$n}) bytes\n";
                } else {
                    echo "Client is not alive anymore\n";
                }
                goto cleanup;
            }

            $this->buffer .= $bytes;
        } while (strlen($this->buffer) < $n);

        $message = substr($this->buffer, 0, $n);
        $this->buffer = substr($this->buffer, $n);

        return (yield new Success($message));

        cleanup:

        if ($this->username !== null) {
            $this->roomState->removeReceiver($this->username);
            yield $this->roomState->send(Event::clientLeft($this->username));
        }

        echo "Exiting loop\n";
        yield new Failure(new \RuntimeException("Client disconnected"));

        return "\0";
    }

    private function write($message)
    {
        if ( ! $this->socketClient->alive()) {
            if ($this->username !== null) {
                $this->roomState->removeReceiver($this->username);
                yield $this->roomState->send(Event::clientLeft($this->username));
            }

            echo "Exiting loop\n";
            yield new Failure(new \RuntimeException("Client disconnected"));
        }

        yield $this->socketClient->write($message);
    }
}
