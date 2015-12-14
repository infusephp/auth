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

use App\Auth\Libs\Auth;
use App\Auth\Models\UserLink;
use App\Auth\Models\PersistentSession;
use Infuse\Model\ACLModel;

class Controller
{
    use \InjectApp;

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
        // interim user to serve as permissions requester until
        // app user is established
        $userModel = Auth::USER_MODEL;

        if (!class_exists($userModel)) {
            require_once 'User.php';
        }

        ACLModel::setRequester(new $userModel());

        $this->app['auth'] = function ($app) use ($req, $res) {
            $auth = new Auth();
            $auth->injectApp($app)
                 ->setRequest($req)
                 ->setResponse($res);

            return $auth;
        };

        $this->app['user'] = $user = $this->app['auth']->getAuthenticatedUser();

        // use the authenticated user as the requester for model permissions
        ACLModel::setRequester($user);
        $this->app['requester'] = $user;

        // CLI requests get super user permissions
        if (defined('STDIN')) {
            $user->enableSU();
        }
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
