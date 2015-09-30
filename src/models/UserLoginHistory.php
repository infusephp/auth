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
use app\auth\libs\Auth;

class UserLoginHistory extends ACLModel
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
