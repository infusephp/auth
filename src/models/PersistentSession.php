<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace app\auth\models;

use infuse\Model;
use infuse\Model\ACLModel;
use infuse\Utility as U;
use app\auth\libs\Auth;

class PersistentSession extends ACLModel
{
    public static $scaffoldApi;
    public static $autoTimestamps;

    public static $properties = [
        'token' => [
            'type' => 'string',
            'required' => true,
        ],
        'user_email' => [
            'type' => 'string',
            'validate' => 'email',
        ],
        'series' => [
            'type' => 'string',
            'required' => true,
            'validate' => 'string:128',
            'admin_hidden_property' => true,
        ],
        'uid' => [
            'type' => 'number',
            'relation' => Auth::USER_MODEL,
        ],
    ];

    public static $sessionLength = 7776000; // 3 months

    public static function idProperty()
    {
        return 'token';
    }

    protected function hasPermission($permission, Model $requester)
    {
        return $requester->isAdmin();
    }

    /**
     * Clears out expired user links.
     *
     * @return bool
     */
    public static function garbageCollect()
    {
        return !!self::$injectedApp['db']->delete('PersistentSessions')
            ->where('created_at', U::unixToDb(time() - self::$sessionLength), '<')->execute();
    }
}
