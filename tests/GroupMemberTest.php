<?php

use infuse\Database;

use app\auth\models\GroupMember;

class GroupMemberTest extends \PHPUnit_Framework_TestCase
{
	static $member;

	static function setUpBeforeClass()
	{
		Database::delete( 'GroupMembers', [ 'uid' => -1, 'group' => 'test' ] );
	}

	function testHasPermission()
	{
		$member = new GroupMember;

		$this->assertFalse( $member->can( 'create', TestBootstrap::app( 'user' ) ) );
	}

	function testCreate()
	{
		self::$member = new GroupMember;
		self::$member->grantAllPermissions();
		$this->assertTrue( self::$member->create( [ 'group' => 'test', 'uid' => -1 ] ) );
	}

	/**
	 * @depends testCreate
	 */
	function testDelete()
	{
		$this->assertTrue( self::$member->delete() );
	}
}