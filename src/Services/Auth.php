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

use Infuse\Application;
use Infuse\Auth\Libs\AuthManager;
use Pulsar\ACLModelRequester;

class Auth
{
    /**
     * @var AuthManager
     */
    private $auth;

    public function __construct(Application $app)
    {
        // CLI requests have a super user
        if (defined('STDIN')) {
            $auth = $this->getAuthManager($app);
            $userClass = $auth->getUserClass();
            $user = new $userClass();
            $user->promoteToSuperUser();
            $auth->setCurrentUser($user);

            // use the super user as the requester for model permissions
            ACLModelRequester::set($user);
            $app['requester'] = $user;
        }
    }

    public function __invoke(Application $app)
    {
        return $this->getAuthManager($app);
    }

    private function getAuthManager(Application $app): AuthManager
    {
        if ($this->auth) {
            return $this->auth;
        }

        $this->auth = new AuthManager();
        $this->auth->setApp($app);

        // register authentication strategies
        $strategies = $app['config']->get('auth.strategies', []);
        foreach ($strategies as $id => $class) {
            $this->auth->registerStrategy($id, $class);
        }

        if ($class = $app['config']->get('auth.2fa_strategy')) {
            $strategy = new $class($this->auth);
            $this->auth->setTwoFactorStrategy($strategy);
        }

        // specify storage type
        if ($class = $app['config']->get('auth.storage')) {
            $storage = new $class($this->auth);
            $this->auth->setStorage($storage);
        }

        return $this->auth;
    }
}
