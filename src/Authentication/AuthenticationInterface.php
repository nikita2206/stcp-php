<?php

namespace Stcp\Authentication;

use Amp\Promise;

interface AuthenticationInterface
{
    /**
     * @param string $username
     * @param string $password
     * @return Promise<bool>
     */
    public function byUsernamePassword(string $username, string $password): Promise;

    /**
     * @param string $token
     * @return Promise<string|null>
     */
    public function byToken(string $token): Promise;

    /**
     * @param string $username
     * @return Promise<string>
     */
    public function generateToken(string $username): Promise;
}
