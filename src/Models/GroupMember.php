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

use Pulsar\ACLModel;
use Pulsar\Model;

class GroupMember extends ACLModel
{
    protected static $autoTimestamps;

    protected static $ids = ['group', 'user_id'];

    protected static $properties = [
        'group' => [],
        'user_id' => [
            'type' => Model::TYPE_NUMBER,
        ],
    ];

    protected function hasPermission($permission, Model $requester)
    {
        return $requester->isAdmin();
    }
}
