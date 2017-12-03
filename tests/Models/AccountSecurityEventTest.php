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
use Mockery\Adapter\Phpunit\MockeryTestCase;

class AccountSecurityEventTest extends MockeryTestCase
{
    public static $user;
    public static $event;

    public static function setUpBeforeClass()
    {
        self::$user = new User();
        self::$user->email = 'test@example.com';
        self::$user->password = ['password', 'password'];
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
        self::$event = new AccountSecurityEvent();
        self::$event->user_id = self::$user->id();
        self::$event->type = 'user.login';
        self::$event->auth_strategy = 'web';
        self::$event->ip = '127.0.0.1';
        self::$event->user_agent = 'Firefox';
        $this->assertTrue(self::$event->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray()
    {
        $expected = [
            'id' => self::$event->id(),
            'type' => 'user.login',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'auth_strategy' => 'web',
            'description' => '',
            'created_at' => self::$event->created_at,
            'updated_at' => self::$event->updated_at,
        ];

        $this->assertEquals($expected, self::$event->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$event->delete());
    }
}
