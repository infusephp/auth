<?php

use infuse\Database;

use app\auth\libs\Auth;
use app\auth\models\GroupMember;
use app\users\models\User;
use app\auth\models\UserLink;

class AbstractUserTest extends \PHPUnit_Framework_TestCase
{
	static $user;
	static $user2;
	static $ogUserId;

	static function setUpBeforeClass()
	{
		$userModel = Auth::USER_MODEL;

		if( !class_exists( $userModel ) )
			require_once 'src/app/auth/DefaultUser.php';

		self::$ogUserId = TestBootstrap::app( 'user' )->id();

		Database::delete( 'Users', [ 'user_email' => 'test@example.com' ] );
		Database::delete( 'Users', [ 'user_email' => 'test2@example.com' ] );
	}

	static function tearDownAfterClass()
	{
		foreach( [ self::$user, self::$user2 ] as $u )
		{
			if( $u )
			{
				$u->grantAllPermissions();
				$u->delete();
			}
		}
	}

	function assertPostConditions()
	{
		$app = TestBootstrap::app();
		if( $app[ 'user' ]->id() != self::$ogUserId )
			$app[ 'user' ] = new User( self::$ogUserId, true );
	}

	function testRegisterUser()
	{
		$this->assertFalse( User::registerUser( [] ) );

		self::$user = User::registerUser( [
			'first_name' => 'Bob',
			'last_name' => 'Loblaw',
			'user_email' => 'test@example.com',
			'user_password' => [ 'testpassword', 'testpassword' ],
			'ip' => '127.0.0.1'
		] );

		$this->assertInstanceOf( '\\app\\users\\models\\User', self::$user );
		$this->assertGreaterThan( 0, self::$user->id() );

		self::$user2 = User::registerUser( [
			'first_name' => 'Bob',
			'last_name' => 'Loblaw',
			'user_email' => 'test2@example.com',
			'user_password' => [ 'testpassword', 'testpassword' ],
			'ip' => '127.0.0.1'
		], true );

		$this->assertInstanceOf( '\\app\\users\\models\\User', self::$user );
		$this->assertGreaterThan( 0, self::$user->id() );
	}

	function testPermissions()
	{
		$testUser = TestBootstrap::app( 'user' );

		$user = new User( GUEST );
		$this->assertTrue( $user->can( 'create', $user ) );
		$this->assertTrue( $user->can( 'edit', $user ) );
		$this->assertFalse( $user->can( 'skip-password-required', $user ) );

		$this->assertFalse( $user->can( 'create', $testUser ) );
		$this->assertFalse( $user->can( 'edit', $testUser ) );
		$this->assertFalse( $user->can( 'skip-password-required', $testUser ) );

		$this->assertFalse( $user->can( 'whatever', $testUser ) );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testEdit()
	{
		$this->assertTrue( self::$user->set( 'ip', '127.0.0.2' ) );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testEditProtectedFieldFail()
	{
		$this->logInAsUser( self::$user );
		$this->assertFalse( self::$user->set( [ 'user_password' => 'testpassword2', 'user_email' => '' ] ) );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testEditProtectedField()
	{
		$this->logInAsUser( self::$user );
		$this->assertTrue( self::$user->set( [
			'current_password' => 'testpassword',
		 	'user_password' => 'testpassword2',
		 	'user_email' => '' ] ) );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testEditProtectedFieldAdmin()
	{
		TestBootstrap::app( 'user' )->enableSU();

		$this->assertTrue( self::$user->set( [
		 	'user_password' => 'testpassword',
		 	'user_email' => '' ] ) );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testName()
	{
		$this->assertEquals( 'Bob', self::$user->name() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testIsTemporary()
	{
		$this->assertFalse( self::$user->isTemporary() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testIsVerified()
	{
		$this->assertFalse( self::$user->isVerified( false ) );
		$this->assertTrue( self::$user->isVerified( true ) );

		$link = UserLink::findOne( [ 'uid' => self::$user->id(), 'link_type' => USER_LINK_VERIFY_EMAIL ] );
		$link->delete();

		$this->assertTrue( self::$user->isVerified() );

		$this->assertTrue( self::$user2->isVerified() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testIsLoggedIn()
	{
		$this->assertFalse( self::$user->isLoggedIn() );
		$this->assertTrue( TestBootstrap::app( 'user' )->isLoggedIn() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testIsAdmin()
	{
		$this->assertFalse( self::$user->isAdmin() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testGroups()
	{
		$this->assertEquals( [ 'everyone' ], self::$user->groups() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testIsMemberOf()
	{
		$this->assertTrue( self::$user->isMemberOf( 'everyone' ) );
		$member = new GroupMember;
		$member->create( [ 'uid' => self::$user->id(), 'group' => 'group' ] );
		$this->assertTrue( self::$user->isMemberOf( 'group' ) );
		$this->assertFalse( self::$user->isMemberOf( 'random' ) );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testProfilePicture()
	{
		$this->assertEquals( "https://secure.gravatar.com/avatar/55502f40dc8b7c769880b10874abc9d0?s=200&d=mm", self::$user->profilePicture() );
	}

	/**
	 * @depends testRegisterUser
	 */
	function testSendEmail()
	{
		$this->assertTrue( self::$user->sendEmail( 'welcome' ) );
		$this->assertTrue( self::$user->sendEmail( 'verify-email', [ 'verify' => 'test' ] ) );
		$this->assertTrue( self::$user->sendEmail( 'forgot-password', [ 'forgot' => 'test', 'ip' => 'test' ] ) );
	}

	/**
	 * @depends testRegisterUser
	 * @depends testEditProtectedFieldAdmin
	 */
	function testDeleteConfirm()
	{
		$this->assertFalse( self::$user->deleteConfirm( 'testpassword' ) );

		$this->logInAsUser( self::$user );

		$this->assertTrue( self::$user->deleteConfirm( 'testpassword' ) );
	}

	function testSU()
	{
		$user = TestBootstrap::app( 'user' );
		$this->assertFalse( $user->isAdmin() );

		$user->enableSU();
		$this->assertTrue( $user->isAdmin() );

		$user->disableSU();
		$this->assertFalse( $user->isAdmin() );
	}

	function testRegisterUserTemporary()
	{
		$this->assertFalse( User::createTemporary( [] ) );

		self::$user = User::createTemporary( [
			'user_email' => 'test@example.com' ] );

		$this->assertTrue( self::$user->isTemporary() );

		$upgradedUser = User::registerUser( [
				'first_name' => 'Bob',
				'last_name' => 'Loblaw',
				'user_email' => 'test@example.com',
				'user_password' => [ 'testpassword', 'testpassword' ],
				'ip' => '127.0.0.1'
			] );

		$this->assertInstanceOf( '\\app\\users\\models\\User', $upgradedUser );
		$this->assertEquals( self::$user->id(), $upgradedUser->id() );
		self::$user->load();
		$this->assertFalse( self::$user->isTemporary() );
	}

	private function logInAsUser( $user )
	{
		$app = TestBootstrap::app();
		$app[ 'user' ] = TestBootstrap::app( 'auth' )->signInUser( $user->id() );
	}
}