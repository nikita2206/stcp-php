<?php

namespace Stcp\Event;

class NewClient extends Event
{
    private $username;

    protected function __construct(string $username)
    {
        $this->username = $username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function encode(): string
    {
        return pack("C", strlen($this->username)) . $this->username;
    }
}
