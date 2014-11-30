<?php

use app\auth\models\PersistentSession;

class PersistentSessionTest extends \PHPUnit_Framework_TestCase
{
    public static $sesh;

    public static function setUpBeforeClass()
    {
        TestBootstrap::app('db')->delete('PersistentSessions')
            ->where('user_email', 'test@exmaple.com')->execute();
    }

    public function testHasPermission()
    {
        $sesh = new PersistentSession();

        $this->assertFalse($sesh->can('create', TestBootstrap::app('user')));
    }

    public function testCreate()
    {
        self::$sesh = new PersistentSession();
        self::$sesh->grantAllPermissions();
        $this->assertTrue(self::$sesh->create([
            'token' => '969326B47C4994ADAF57AD7CE7345D5A40F1F9565DE899E8302DA903340E5A79969326B47C4994ADAF57AD7CE7345D5A40F1F9565DE899E8302DA903340E5A79',
            'user_email' => 'test@example.com',
            'series' => 'DeFx724Iqo6LwbJK4JB1MGXEbHpe9p3MNKZONqellNrBuWbytxGr7nPU5VwI3VwDeFx724Iqo6LwbJK4JB1MGXEbHpe9p3MNKZONqellNrBuWbytxGr7nPU5VwI3Vwff', ]));
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
