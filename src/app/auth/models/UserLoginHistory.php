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

if( !defined( 'LOGIN_TYPE_TRADITIONAL' ) )
    define( 'LOGIN_TYPE_TRADITIONAL', 0 );
if( !defined( 'LOGIN_TYPE_PERSISTENT_SESSION' ) )
    define( 'LOGIN_TYPE_PERSISTENT_SESSION', 1 );
if( !defined( 'LOGIN_TYPE_OAUTH' ) )
    define( 'LOGIN_TYPE_OAUTH', 2 );

class UserLoginHistory extends Model
{
    public static $scaffoldApi;
    public static $autoTimestamps;

    public static $properties = [
        'uid' => [
            'type' => 'number',
            'relation' => Auth::USER_MODEL
        ],
        'type' => [
            'type' => 'number',
            'admin_type' => 'enum',
            'admin_enum' => [
                LOGIN_TYPE_TRADITIONAL => 'Regular',
                LOGIN_TYPE_PERSISTENT_SESSION => 'Persistent',
                LOGIN_TYPE_OAUTH => 'OAuth',
                3 => 'Facebook',
                4 => 'Twitter',
            ],
        ],
        'ip' => [
            'type' => 'string',
            'admin_hidden_property' => true
        ],
        'user_agent' => [
            'type' => 'string',
            'null' => true,
            'admin_hidden_property' => true
        ]
    ];

    protected function hasPermission($permission, Model $requester)
    {
        return $requester->isAdmin();
    }
}
