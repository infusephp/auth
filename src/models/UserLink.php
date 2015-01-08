<?php

/**
 * @package infuse\framework
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @version 0.1.16
 * @copyright 2013 Jared King
 * @license MIT
 */

namespace app\auth\models;

use infuse\Model;
use infuse\Utility as U;
use app\auth\libs\Auth;

if (!defined('USER_LINK_FORGOT_PASSWORD')) {
    define('USER_LINK_FORGOT_PASSWORD', 0);
}
if (!defined('USER_LINK_VERIFY_EMAIL')) {
    define('USER_LINK_VERIFY_EMAIL', 1);
}
if (!defined('USER_LINK_TEMPORARY')) {
    define('USER_LINK_TEMPORARY', 2);
}

class UserLink extends Model
{
    public static $scaffoldApi;
    public static $autoTimestamps;

    public static $properties = [
        'uid' => [
            'type' => 'number',
            'required' => true,
            'relation' => Auth::USER_MODEL,
        ],
        'link' => [
            'type' => 'string',
            'required' => true,
            'validate' => 'string:32',
        ],
        'link_type' => [
            'type' => 'number',
            'validate' => 'enum:0,1,2',
            'required' => true,
            'admin_type' => 'enum',
            'admin_enum' => [
                USER_LINK_FORGOT_PASSWORD => 'Forgot Password',
                USER_LINK_VERIFY_EMAIL => 'Verify E-mail',
                USER_LINK_TEMPORARY => 'Temporary',
            ],
        ],
    ];

    public static $verifyTimeWindow = 86400; // one day

    public static $forgotLinkTimeframe = 1800; // 30 minutes

    public static function idProperty()
    {
        return [ 'uid', 'link' ];
    }

    protected function hasPermission($permission, Model $requester)
    {
        if ($permission == 'create') {
            return true;
        }

        return $requester->isAdmin();
    }

    protected function preCreateHook(&$data)
    {
        // can only create user links for the current user
        $user = $this->app[ 'user' ];
        if ($data[ 'uid' ] != $user->id() && !$this->can('create-with-mismatched-uid', $user)) {
            $this->app[ 'errors' ]->push(['error' => ERROR_NO_PERMISSION ]);

            return false;
        }

        if (!isset($data[ 'link' ])) {
            $data[ 'link' ] = U::guid(false);
        }

        return true;
    }

    /**
     * Clears out expired user links
     *
     * @return boolean
     */
    public static function garbageCollect()
    {
        return !!self::$injectedApp['db']->delete('UserLinks')->where('link_type', USER_LINK_FORGOT_PASSWORD)
            ->where('created_at', U::unixToDb(time() - self::$forgotLinkTimeframe), '<')->execute();
    }
}
