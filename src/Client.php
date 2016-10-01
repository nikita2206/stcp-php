<?php

namespace Stcp;

use Amp\Socket\Client as SocketClient;

class Client
{
    const FRAME_HEY = 0x01;
    const FRAME_MYNAMEIS = 0x02;
    const FRAME_SUP = 0x03;

    /**
     * @var SocketClient
     */
    private $socketClient;

    private $username;

    public function __construct(SocketClient $client)
    {
        $this->socketClient = $client;
    }

    public function loop()
    {
        if ( ! (yield from $this->handshake())) {
            return;
        }

        authentication:
        // should also expect HEY frame again
        $frame = (yield $this->socketClient->read(1));
        if (ord($frame) === self::FRAME_MYNAMEIS) {
            $length = ord(yield $this->socketClient->read(1));
            $username = (yield $this->socketClient->read($length));
            $length = ord(yield $this->socketClient->read(1));
            $password = (yield $this->socketClient->read($length));

            if ($username === "lou" && $password === "reed") {
                $this->username = $username;
                yield $this->socketClient->write(pack("C", 0x02));
            } else {
                yield $this->socketClient->write(pack("C", 0x03));
                goto authentication;
            }
        } elseif (ord($frame) === self::FRAME_SUP) {
            $length = unpack("n", yield $this->socketClient->read(2));
            $token = (yield $this->socketClient->read($length));

            if ($token === "right token") {
                $this->username = "lou";
                yield $this->socketClient->write(pack("C", 0x02));
            } else {
                yield $this->socketClient->write(pack("C", 0x03));
                goto authentication;
            }
        } else {
            $code = 1;
            $message = "Unexpected frame";

            $response = pack("Cnn", 0x04, $code, strlen($message)) . $message;
            yield $this->socketClient->write($response);
            return;
        }

        
    }

    public function handshake()
    {
        $frame = (yield $this->socketClient->read(9));
        list($hey, $version) = array_values(unpack("C/J", $frame));

        if ($hey !== self::FRAME_HEY) {
            $code = 1;
            $message = "Unexpected frame";

            $response = pack("Cnn", 0x04, $code, strlen($message)) . $message;
            yield $this->socketClient->write($response);
            return false;
        }

        $response = pack("CJ", self::FRAME_HEY, 1);
        yield $this->socketClient->write($response);

        return true;
    }
}
