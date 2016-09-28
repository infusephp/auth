<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Services;

use Infuse\Auth\Libs\Auth as AuthService;
use Pulsar\ACLModel;

class Auth
{
    public function __construct($app)
    {
        // CLI requests have a super user
        if (defined('STDIN')) {
            $userModel = $this->getUserClass($app);
            $user = new $userModel(-2, true);
            $user->enableSU();
            $app['user'] = $user;

            // use the super user as the requester for model permissions
            ACLModel::setRequester($user);
            $app['requester'] = $user;
        }
    }

    public function __invoke($app)
    {
        $auth = new AuthService();
        $auth->setApp($app);

        // register authentication strategies
        $strategies = $app['config']->get('auth.strategies', []);
        foreach ($strategies as $id => $class) {
            $auth->registerStrategy($id, $class);
        }

        return $auth;
    }

    /**
     * Gets the user model class.
     *
     * @return string
     */
    private function getUserClass($app)
    {
        return $app['config']->get('users.model', AuthService::DEFAULT_USER_MODEL);
    }
}
