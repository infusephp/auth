<?php

use app\auth\models\UserLoginHistory;

class UserLoginHistoryTest extends \PHPUnit_Framework_TestCase
{
	static $history;

	function testHasPermission()
	{
		$history = new UserLoginHistory;

		$this->assertFalse( $history->can( 'create', TestBootstrap::app( 'user' ) ) );
	}

	function testCreate()
	{
		self::$history = new UserLoginHistory;
		self::$history->grantAllPermissions();
		$this->assertTrue( self::$history->create( [
			'uid' => -1,
			'type' => LOGIN_TYPE_TRADITIONAL,
			'ip' => TestBootstrap::app( 'req' )->ip() ] ) );
	}

	/**
	 * @depends testCreate
	 */
	function testDelete()
	{
		$this->assertTrue( self::$history->delete() );
	}
}