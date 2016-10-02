<?php

namespace Stcp\Authentication;

use Amp\Promise;
use Amp\Success;

class InMemoryAuthentication implements AuthenticationInterface
{
    /**
     * @var array [username => {tokens: [string], password: string}]
     */
    private $users;

    /**
     * @var array [token => username]
     */
    private $tokens;

    public function __construct()
    {
        $this->users = [];
        $this->tokens = [];
    }

    public function byUsernamePassword(string $username, string $password): Promise
    {
        if ( ! isset($this->users[$username])) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $this->users[$username] = ["tokens" => [], "password" => $hashed];

            return new Success(true);
        } else {
            $user = $this->users[$username];

            return new Success(password_verify($password, $user["password"]));
        }
    }

    public function byToken(string $token): Promise
    {
        return new Success($this->tokens[$token] ?? null);
    }

    public function generateToken(string $username): Promise
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (isset($this->tokens[$token]));

        $this->tokens[$token] = $username;
        $this->users[$username]["tokens"][] = $token;

        return new Success($token);
    }
}
