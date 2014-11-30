<?php

use app\auth\models\GroupMember;

class GroupMemberTest extends \PHPUnit_Framework_TestCase
{
    public static $member;

    public static function setUpBeforeClass()
    {
        TestBootstrap::app('db')->delete('GroupMembers')->where('uid', -1)
            ->where('group', 'test')->execute();
    }

    public function testHasPermission()
    {
        $member = new GroupMember();

        $this->assertFalse($member->can('create', TestBootstrap::app('user')));
    }

    public function testCreate()
    {
        self::$member = new GroupMember();
        self::$member->grantAllPermissions();
        $this->assertTrue(self::$member->create([ 'group' => 'test', 'uid' => -1 ]));
    }

    /**
     * @depends testCreate
     */
    public function testDelete()
    {
        $this->assertTrue(self::$member->delete());
    }
}
