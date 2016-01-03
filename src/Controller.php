<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth;

use App\Auth\Models\UserLink;
use App\Auth\Models\PersistentSession;
use Infuse\HasApp;
use Pulsar\ACLModel;

class Controller
{
    use HasApp;

    public static $properties = [
        'models' => [
            'UserLink',
            'UserLoginHistory',
            'PersistentSession',
            'GroupMember',
        ],
    ];

    public static $scaffoldAdmin;

    public function middleware($req, $res)
    {
        // inject the request/response into auth
        $auth = $this->app['auth'];
        $auth->setRequest($req)->setResponse($res);

        $user = $auth->getAuthenticatedUser();
        $this->app['user'] = $user;

        // use the authenticated user as the requester for model permissions
        ACLModel::setRequester($user);
        $this->app['requester'] = $user;
    }

    public function garbageCollection()
    {
        // clear out expired persistent sessions
        $persistentSessionSuccess = PersistentSession::garbageCollect();

        if ($persistentSessionSuccess) {
            echo "Garbage collection of persistent sessions was successful.\n";
        } else {
            echo "Garbage collection of persistent sessions was NOT successful.\n";
        }

        // clear out expired user links
        $userLinkSuccess = UserLink::garbageCollect();

        if ($userLinkSuccess) {
            echo "Garbage collection of user links was successful.\n";
        } else {
            echo "Garbage collection of user links was NOT successful.\n";
        }

        return $persistentSessionSuccess && $userLinkSuccess;
    }
}
