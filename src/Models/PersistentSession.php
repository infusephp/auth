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

class PersistentSession extends Model
{
    protected static $ids = ['token'];

    protected static $properties = [
        'token' => [
            'required' => true,
        ],
        'email' => [
            'validate' => 'email',
        ],
        'series' => [
            'required' => true,
            'validate' => 'string:128',
        ],
        'user_id' => [
            'type' => Model::TYPE_NUMBER,
        ],
    ];

    protected static $autoTimestamps;

    /**
     * @staticvar int
     */
    public static $sessionLength = 7776000; // 3 months in seconds
}
