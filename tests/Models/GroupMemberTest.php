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
use Infuse\Auth\Models\GroupMember;
use Infuse\Test;
use PHPUnit\Framework\TestCase;

class GroupMemberTest extends TestCase
{
    public static $user;
    public static $member;

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

    public function testHasPermission()
    {
        $member = new GroupMember();

        $this->assertFalse($member->can('create', Test::$app['user']));
    }

    public function testCreate()
    {
        self::$member = new GroupMember();
        self::$member->grantAllPermissions();
        $this->assertTrue(self::$member->create(['group' => 'test', 'user_id' => self::$user->id()]));
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$member->delete());
    }
}
