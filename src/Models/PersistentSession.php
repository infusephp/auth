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
        'user_id' => [
            'type' => Model::TYPE_NUMBER,
        ],
        'email' => [
            'validate' => 'email',
        ],
        'series' => [
            'required' => true,
            'validate' => 'string:128',
        ],
        'token' => [
            'required' => true,
            'validate' => 'string:128',
        ],
        'two_factor_verified' => [
            'type' => Model::TYPE_BOOLEAN,
        ],
    ];

    protected static $autoTimestamps;
    protected static $casts = [];
    protected static $validations = [];
    protected static $protected = [];

    /**
     * @staticvar int
     */
    public static $sessionLength = 7776000; // 3 months in seconds
}
