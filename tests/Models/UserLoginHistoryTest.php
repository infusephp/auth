<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Users\Models\User;
use Infuse\Auth\Models\AccountSecurityEvent;

class AccountSecurityEventTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $history;

    public static function setUpBeforeClass()
    {
        self::$user = new User();
        self::$user->user_email = 'test@example.com';
        self::$user->user_password = ['password', 'password'];
        self::$user->ip = '127.0.0.1';
        self::$user->first_name = 'Bob';
        self::$user->last_name = 'Loblaw';
        self::$user->save();
    }

    public static function tearDownAfterClass()
    {
        self::$user->delete();
    }

    public function testCreate()
    {
        self::$history = new AccountSecurityEvent();
        self::$history->user_id = self::$user->id();
        self::$history->type = 'user.login';
        self::$history->auth_strategy = 'web';
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
