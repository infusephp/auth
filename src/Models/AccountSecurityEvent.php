<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Models;

use Pulsar\Model;

class AccountSecurityEvent extends Model
{
    const LOGIN = 'user.login';
    const CHANGE_PASSWORD = 'user.change_password';
    const RESET_PASSWORD_REQUEST = 'user.request_password_reset';

    protected static $properties = [
        'user_id' => [
            'required' => true,
        ],
        'type' => [
            'required' => true,
        ],
        'ip' => [
            'required' => true,
        ],
        'user_agent' => [
            'null' => true,
            'required' => true,
        ],
        'auth_strategy' => [
            'null' => true,
        ],
        'description' => [],
    ];

    protected static $autoTimestamps;
    protected static $casts = [];
    protected static $validations = [];
    protected static $protected = [];
    protected static $hidden = ['user_id'];
}
