<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Auth\Models\UserLink;

class UserLinkTest extends PHPUnit_Framework_TestCase
{
    public static $link;

    public function testCreate()
    {
        self::$link = new UserLink();
        $this->assertTrue(self::$link->create([
            'user_id' => -1,
            'link_type' => UserLink::FORGOT_PASSWORD, ]));
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$link->delete());
    }

    public function testGarbageCollect()
    {
        $this->assertTrue(UserLink::garbageCollect());
    }
}
