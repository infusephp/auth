<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Auth\Models\UserLoginHistory;

class UserLoginHistoryTest extends PHPUnit_Framework_TestCase
{
    public static $history;

    public function testCreate()
    {
        self::$history = new UserLoginHistory();
        self::$history->user_id = -1;
        self::$history->type = 'web';
        self::$history->ip = '127.0.0.1';
        self::$history->user_agent = 'Firefox';
        $this->assertTrue(self::$history->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$history->delete());
    }
}
