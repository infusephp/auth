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

class UserLoginHistory extends Model
{
    protected static $autoTimestamps;

    protected static $properties = [
        'uid' => [
            'relation' => Auth::USER_MODEL,
        ],
        'type' => [],
        'ip' => [
            'admin_hidden_property' => true,
        ],
        'user_agent' => [
            'null' => true,
            'admin_hidden_property' => true,
        ],
    ];
}
