<?php

/**
 * @package infuse\auth
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @copyright 2015 Jared King
 * @license MIT
 */

use app\auth\models\UserLoginHistory;

class UserLoginHistoryTest extends \PHPUnit_Framework_TestCase
{
    public static $history;

    public function testHasPermission()
    {
        $history = new UserLoginHistory();

        $this->assertFalse($history->can('create', Test::$app['user']));
    }

    public function testCreate()
    {
        self::$history = new UserLoginHistory();
        self::$history->grantAllPermissions();
        $this->assertTrue(self::$history->create([
            'uid' => -1,
            'type' => 'web',
            'ip' => Test::$app['req']->ip(), ]));
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$history->delete());
    }
}
