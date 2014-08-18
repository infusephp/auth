<?php

use app\auth\models\UserLink;

class UserLinkTest extends \PHPUnit_Framework_TestCase
{
	static $link;

	function testHasPermission()
	{
		$link = new UserLink;

		$this->assertTrue( $link->can( 'create', TestBootstrap::app( 'user' ) ) );

		$this->assertFalse( $link->can( 'admins-only', TestBootstrap::app( 'user' ) ) );
	}

	function testCannotCreate()
	{
		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();

		$link = new UserLink;

		$this->assertFalse( $link->create( [
			'uid' => TestBootstrap::app( 'user' )->id() - 1,
			'link_type' => USER_LINK_FORGOT_PASSWORD ] ) );
		$errors = $errorStack->errors( 'UserLink.create' );
		$expected = [ [
			'error' => 'no_permission',
			'message' => 'You do not have permission to do that',
			'context' => 'UserLink.create',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );
	}

	function testCreate()
	{
		self::$link = new UserLink;
		self::$link->grantAllPermissions();
		$this->assertTrue( self::$link->create( [
			'uid' => -1,
			'link_type' => USER_LINK_FORGOT_PASSWORD ] ) );
	}

	/**
	 * @depends testCreate
	 */
	function testDelete()
	{
		$this->assertTrue( self::$link->delete() );
	}

	function testGarbageCollect()
	{
		$this->assertTrue( UserLink::garbageCollect() );
	}
}