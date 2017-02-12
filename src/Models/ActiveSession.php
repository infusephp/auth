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

class ActiveSession extends Model
{
    protected static $properties = [
        'id' => [
            'required' => true,
            'mutable' => Model::MUTABLE_CREATE_ONLY,
        ],
        'user_id' => [
            'required' => true,
        ],
        'ip' => [
            'required' => true,
        ],
        'user_agent' => [
            'required' => true,
        ],
        'expires' => [
            'type' => Model::TYPE_DATE,
        ],
        'valid' => [
            'type' => Model::TYPE_BOOLEAN,
            'default' => true,
        ],
    ];

    protected static $autoTimestamps;
    protected static $casts = [];
    protected static $validations = [];
    protected static $protected = [];
    protected static $defaults = [];
    protected static $hidden = ['user_id', 'valid'];
}
