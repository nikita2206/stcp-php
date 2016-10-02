<?php

$username = $argv[1];
$password = $argv[2];

$sock = fsockopen("127.0.0.1", 4224);

echo "> " . fwrite($sock, pack("CJ", 0x01, 1)) . " bytes\n";

echo "< " . bin2hex(fread($sock, 9)) . "\n";

echo "> " . fwrite($sock, pack("CC", 0x02, strlen($username)) . $username
          . pack("C", strlen($password)) . $password) . " bytes \n";

$response = fread($sock, 1);
echo "< " . bin2hex($response) . "\n";
$frame = ord($response);
if ($frame === 0x03) {
    echo "Expected SUP frame, got NOPE instead (not authenticated)\n";
    exit;
}

echo "| You're in the room\n\n";


$stdin = fopen("php://stdin", "r");
stream_set_blocking($stdin, 0);
stream_set_blocking($sock, 0);

$read = [$sock, $stdin];
$write = [];
$except = [];

while (false !== stream_select($read, $write, $except, null)) {
    if ($read) {
        if (in_array($stdin, $read, true)) {
            $message = fread($stdin, 8092);
            $request = pack("CJ", 0x06, strlen($message)) . $message;

            stream_set_blocking($sock, 1);
            fwrite($sock, $request);
            stream_set_blocking($sock, 0);
        }
        if (in_array($sock, $read, true)) {
            $response = fread($sock, 1);
            if (strlen($response) !== 1) {
                echo "| Expected 1 bytes, got nothing\n";
                exit;
            }
            if (ord($response) !== 0x07) {
                echo "| Expected ROOMEVENT, got " . ord($response) . "\n";
                exit;
            }

            stream_set_blocking($sock, 1);

            $type = fread($sock, 1);
            if (strlen($type) === 0) {
                echo "| Expected type, got nothing\n";
                exit;
            }

            $type = ord($type);
            if ( ! in_array($type, [1, 2, 3], true)) {
                echo "| Expected room type, got: " . $type . "\n";
                exit;
            }

            $response = fread($sock, 1);
            if (strlen($response) !== 1) {
                echo "| Expected one byte, got nothing\n";
                exit;
            }

            $length = ord($response);
            $username = fread($sock, $length);
            if (strlen($username) !== $length) {
                echo "| Expected {$length} bytes, got " . strlen($username) . "\n";
                exit;
            }

            if ($type === 1) {
                echo "| {$username} have decided to connect\n";
            } elseif ($type === 2) {
                echo "| {$username} has left\n";
            } else {

                $response = fread($sock, 8);
                if (strlen($response) !== 8) {
                    echo "| Expected 8 bytes, got " . strlen($response) . "\n";
                    exit;
                }

                $length = unpack("J", $response)[1];
                $response = fread($sock, $length);

                if (strlen($response) !== $length) {
                    echo "| Expected {$length}, got " . strlen($response) . "\n";
                    exit;
                }

                echo "| {$username}: {$response}";
            }

            stream_set_blocking($sock, 0);
        }
    }

    go_on:
    $write = $except = [];
    $read = [$sock, $stdin];
}

echo "| Connection closed\n";
