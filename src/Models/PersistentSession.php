<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth\Models;

use App\Auth\Libs\Auth;
use Infuse\Model;
use Infuse\Model\ACLModel;
use Infuse\Utility as U;

class PersistentSession extends ACLModel
{
    public static $scaffoldApi;
    protected static $autoTimestamps;

    protected static $ids = ['token'];

    protected static $properties = [
        'token' => [
            'required' => true,
        ],
        'user_email' => [
            'validate' => 'email',
        ],
        'series' => [
            'required' => true,
            'validate' => 'string:128',
            'admin_hidden_property' => true,
        ],
        'uid' => [
            'type' => Model::TYPE_NUMBER,
            'relation' => Auth::USER_MODEL,
        ],
    ];

    public static $sessionLength = 7776000; // 3 months

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
