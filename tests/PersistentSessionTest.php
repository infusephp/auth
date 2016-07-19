<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Auth\Models\PersistentSession;
use App\Users\Models\User;
use Infuse\Test;

class PersistentSessionTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $sesh;

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
        self::$sesh = new PersistentSession();
        self::$sesh->token = '969326B47C4994ADAF57AD7CE7345D5A40F1F9565DE899E8302DA903340E5A79969326B47C4994ADAF57AD7CE7345D5A40F1F9565DE899E8302DA903340E5A79';
        self::$sesh->user_email = 'test@example.com';
        self::$sesh->series = 'DeFx724Iqo6LwbJK4JB1MGXEbHpe9p3MNKZONqellNrBuWbytxGr7nPU5VwI3VwDeFx724Iqo6LwbJK4JB1MGXEbHpe9p3MNKZONqellNrBuWbytxGr7nPU5VwI3Vwff';
        self::$sesh->user_id = self::$user->id();
        $this->assertTrue(self::$sesh->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$sesh->delete());
    }

    public function testGarbageCollect()
    {
        $this->assertTrue(PersistentSession::garbageCollect());
    }
}
