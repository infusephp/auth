<?php

namespace App\Auth\Services;

use App\Auth\Libs\Auth as AuthService;
use Pulsar\ACLModel;

class Auth
{
    public function __construct($app)
    {
        // CLI requests have a super user
        if (defined('STDIN')) {
            $userModel = AuthService::USER_MODEL;
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
        $auth->injectApp($app);

        return $auth;
    }
}
