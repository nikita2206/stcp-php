<?php

namespace Stcp\Event;

class Message extends Event
{
    private $username;
    private $message;

    protected function __construct(string $username, string $message)
    {
        $this->username = $username;
        $this->message = $message;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function encode(): string
    {
        return pack("C", strlen($this->username)) . $this->username . pack("J", strlen($this->message)) . $this->message;
    }
}
