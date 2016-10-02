<?php

namespace Stcp\Event;

abstract class Event
{
    public static $types = [
        NewClient::class => 1,
        ClientLeft::class => 2,
        Message::class => 3
    ];

    public static function newClient(string $username)
    {
        return new NewClient($username);
    }

    public static function message(string $username, string $message)
    {
        return new Message($username, $message);
    }

    public static function clientLeft($username)
    {
        return new ClientLeft($username);
    }

    abstract function encode(): string;
}
