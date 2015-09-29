<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use app\auth\models\UserLink;

class UserLinkTest extends \PHPUnit_Framework_TestCase
{
    public static $link;

    public function testHasPermission()
    {
        $link = new UserLink();

        $this->assertTrue($link->can('create', Test::$app['user']));

        $this->assertFalse($link->can('admins-only', Test::$app['user']));
    }

    public function testCannotCreate()
    {
        $errorStack = Test::$app['errors'];
        $errorStack->clear();

        $link = new UserLink();

        $this->assertFalse($link->create([
            'uid' => Test::$app['user']->id() - 1,
            'link_type' => USER_LINK_FORGOT_PASSWORD, ]));
        $errors = $errorStack->errors('UserLink.create');
        $expected = [[
            'error' => 'no_permission',
            'message' => 'no_permission',
            'context' => 'UserLink.create',
            'params' => [], ]];
        $this->assertEquals($expected, $errors);
    }

    public function testCreate()
    {
        self::$link = new UserLink();
        self::$link->grantAllPermissions();
        $this->assertTrue(self::$link->create([
            'uid' => -1,
            'link_type' => USER_LINK_FORGOT_PASSWORD, ]));
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
