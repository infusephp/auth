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
use Infuse\Auth\Models\UserLink;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class UserLinkTest extends MockeryTestCase
{
    public static $user;
    public static $link;

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
        self::$link = new UserLink();
        self::$link->user_id = self::$user->id();
        self::$link->type = UserLink::FORGOT_PASSWORD;
        $this->assertTrue(self::$link->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$link->delete());
    }
}
