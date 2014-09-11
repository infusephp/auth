<?php

/**
 * @package infuse\framework
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @version 0.1.16
 * @copyright 2013 Jared King
 * @license MIT
 */

namespace app\auth;

use infuse\Model;

use InjectApp;
use app\auth\libs\Auth;
use app\auth\models\UserLink;
use app\auth\models\PersistentSession;

class Controller
{
    use InjectApp;
    
    public static $properties = [
        'models' => [
            'UserLink',
            'UserLoginHistory',
            'PersistentSession',
            'GroupMember'
        ]
    ];

    public static $scaffoldAdmin;

    public function middleware($req, $res)
    {
        // interim user to serve as permissions requester until
        // app user is established
        $userModel = Auth::USER_MODEL;

        if( !class_exists( $userModel ) )
            require_once 'User.php';

        Model::configure( [ 'requester' => new $userModel() ] );

        $this->app[ 'auth' ] = function ($app) {
            return new Auth( $app );
        };

        $this->app[ 'user' ] = $user = $this->app[ 'auth' ]->getAuthenticatedUser();

        // use the authenticated user as the requester for model permissions
        Model::configure( [ 'requester' => $user ] );

        // CLI requests get super user permissions
        if( $req->isCli() )
            $user->enableSU();
    }

    public function cron($command)
    {
        if ($command == 'garbage-collection') {
            // clear out expired persistent sessions
            $persistentSessionSuccess = PersistentSession::garbageCollect();

            if( $persistentSessionSuccess )
                echo "Garbage collection of persistent sessions was successful.\n";
            else
                echo "Garbage collection of persistent sessions was NOT successful.\n";

            // clear out expired user links
            $userLinkSuccess = UserLink::garbageCollect();

            if( $userLinkSuccess )
                echo "Garbage collection of user links was successful.\n";
            else
                echo "Garbage collection of user links was NOT successful.\n";

            return $persistentSessionSuccess && $userLinkSuccess;
        }
    }

    private function ensureHttp($req, $res)
    {
        if ( $req->isSecure() ) {
            $url = str_replace( 'https://', 'http://', $req->url() );
            header( 'HTTP/1.1 301 Moved Permanently' );
            header( "Location: $url" );
            exit;
        }
    }

    private function ensureHttps($req, $res)
    {
        if ( !$req->isSecure() && $this->app[ 'config' ]->get( 'site.ssl-enabled' ) ) {
            $url = str_replace( 'http://', 'https://', $req->url() );
            header( 'HTTP/1.1 301 Moved Permanently' );
            header( "Location: $url" );
            exit;
        }
    }
}
