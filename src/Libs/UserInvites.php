<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs;

use Infuse\Auth\Exception\AuthException;
use Infuse\HasApp;

/**
 * Creates invitations for new users.
 */
class UserInvites
{
    use HasApp;

    /**
     * @var AuthManager
     */
    private $auth;

    /**
     * @param AuthManager $auth
     */
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
        $this->setApp($auth->getApp());
    }

    /**
     * Invites a user by email address. If the user does
     * not exist then a temporary one will be created. If
     * there is an existing user then it will be returned.
     *
     * @param string $email
     * @param array $parameters
     * @param array $emailParameters
     *
     * @throws AuthException when the invite or user cannot be created.
     *
     * @return mixed
     */
    function invite($email, array $parameters = [], array $emailParameters = [])
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Please enter a valid email address.');
        }

        // check for existing user account
        $userClass = $this->auth->getUserClass();
        $user = $userClass::where('email', $email)->first();

        // register a new temporary account
        if (!$user) {
            $parameters['email'] = $email;
            $user = $userClass::createTemporary($parameters);
            if (!$user) {
                throw new AuthException('Could not invite ' . $email);
            }
        }

        $user->sendEmail('invite', $emailParameters);

        return $user;
    }
}