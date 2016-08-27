<?php

namespace Infuse\Auth;

use Infuse\HasApp;
use Pulsar\ACLModel;

class AuthMiddleware
{
    use HasApp;

    public function __invoke($req, $res, $next)
    {
        // inject the request/response into auth
        $auth = $this->app['auth'];
        $auth->setRequest($req)->setResponse($res);

        $user = $auth->getAuthenticatedUser();
        $this->app['user'] = $user;

        // use the authenticated user as the requester for model permissions
        ACLModel::setRequester($user);
        $this->app['requester'] = $user;

        return $next($req, $res);
    }
}
