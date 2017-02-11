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
use Infuse\Auth\Models\ActiveSession;
use Infuse\Test;
use PHPUnit\Framework\TestCase;

class ActiveSessionTest extends TestCase
{
    public static $user;
    public static $session;

    public static function setUpBeforeClass()
    {
        self::$user = new User();
        self::$user->email = 'test@example.com';
        self::$user->password = ['password', 'password'];
        self::$user->ip = '127.0.0.1';
        self::$user->first_name = 'Bob';
        self::$user->last_name = 'Loblaw';
        self::$user->save();

        Test::$app['db']->delete('ActiveSessions')
                        ->where('id', 'sesh_1234')
                        ->execute();
    }

    public static function tearDownAfterClass()
    {
        self::$user->delete();
    }

    public function testCreate()
    {
        self::$session = new ActiveSession();
        self::$session->id = 'sesh_1234';
        self::$session->user_id = self::$user->id();
        self::$session->ip = '127.0.0.1';
        self::$session->user_agent = 'Firefox';
        self::$session->expires = strtotime('+1 day');
        $this->assertTrue(self::$session->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray()
    {
        $expected = [
            'id' => 'sesh_1234',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'expires' => self::$session->expires,
            'created_at' => self::$session->created_at,
            'updated_at' => self::$session->updated_at,
        ];

        $this->assertEquals($expected, self::$session->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit()
    {
        self::$session->expires = strtotime('+2 days');
        $this->assertTrue(self::$session->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$session->delete());
    }
}
