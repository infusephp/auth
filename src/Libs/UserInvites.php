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
use Infuse\Auth\Models\UserLink;
use Infuse\HasApp;
use Infuse\Utility as U;

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
     * Invites a new user.
     *
     * @param string $email
     * @param array $parameters
     * @param array $emailParameters
     *
     * @throws AuthException
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