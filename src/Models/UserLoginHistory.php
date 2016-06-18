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

use Pulsar\Model;

class UserLoginHistory extends Model
{
    protected static $properties = [
        'user_id' => [],
        'type' => [],
        'ip' => [
            'admin_hidden_property' => true,
        ],
        'user_agent' => [
            'null' => true,
            'admin_hidden_property' => true,
        ],
    ];

    protected static $autoTimestamps;
}
