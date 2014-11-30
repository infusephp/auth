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
use app\auth\libs\Auth;

class UserLoginHistory extends Model
{
    public static $scaffoldApi;
    public static $autoTimestamps;

    public static $properties = [
        'uid' => [
            'type' => 'number',
            'relation' => Auth::USER_MODEL,
        ],
        'type' => [
            'type' => 'string',
        ],
        'ip' => [
            'type' => 'string',
            'admin_hidden_property' => true,
        ],
        'user_agent' => [
            'type' => 'string',
            'null' => true,
            'admin_hidden_property' => true,
        ],
    ];

    protected function hasPermission($permission, Model $requester)
    {
        return $requester->isAdmin();
    }
}
