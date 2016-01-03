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
use Pulsar\ACLModel;
use App\Auth\Libs\Auth;

class GroupMember extends ACLModel
{
    public static $scaffoldApi;
    protected static $autoTimestamps;

    protected static $ids = ['group', 'uid'];

    protected static $properties = [
        'group' => [],
        'uid' => [
            'type' => Model::TYPE_NUMBER,
            'relation' => Auth::USER_MODEL,
        ],
    ];

    protected function hasPermission($permission, Model $requester)
    {
        return $requester->isAdmin();
    }
}
