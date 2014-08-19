<?php

use infuse\Database;

use app\auth\libs\Auth;
use app\auth\models\UserLink;
use app\auth\models\UserLoginHistory;

class AuthTest extends \PHPUnit_Framework_TestCase
{
	static $user;
	static $auth;
	static $ogUserId;

	static function setUpBeforeClass()
	{
		$userModel = Auth::USER_MODEL;

		TestBootstrap::app( 'user' )->enableSU();

		Database::delete( 'Users', [ 'user_email' => 'test@example.com' ] );

		self::$user = $userModel::registerUser( [
			'first_name' => 'Bob',
			'last_name' => 'Loblaw',
			'user_email' => 'test@example.com',
			'user_password' => [ 'testpassword', 'testpassword' ],
			'ip' => '127.0.0.1'
		] );
		self::$user->grantAllPermissions();

		TestBootstrap::app( 'user' )->disableSU();

		self::$ogUserId = TestBootstrap::app( 'user' )->id();
	}

	static function tearDownAfterClass()
	{
		foreach( [ self::$user ] as $u )
		{
			if( $u )
			{
				$u->grantAllPermissions();
				$u->delete();
			}
		}
	}

	function assertPreConditions()
	{
		$this->assertInstanceOf( Auth::USER_MODEL, self::$user );
	}

	function assertPostConditions()
	{
		$userModel = Auth::USER_MODEL;

		$app = TestBootstrap::app();
		if( !$app[ 'user' ]->isLoggedIn() )
			$app[ 'user' ] = new $userModel( self::$ogUserId, true );
	}
	
	function testConstruct()
	{
		// self::$app = new App();
		self::$auth = new Auth( TestBootstrap::app() );
	}

	function testGetUserWithCredentialsFail()
	{
		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();
		$errorStack->setCurrentContext( '' );

		$this->assertFalse( self::$auth->getUserWithCredentials( '', '' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_bad_username',
			'message' => 'Please enter a valid username.',
			'context' => '',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );

		$errorStack->clear();

		$this->assertFalse( self::$auth->getUserWithCredentials( 'test@example.com', '' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_bad_password',
			'message' => 'Please enter a valid password.',
			'context' => '',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );
	}

	function testGetUserWithCredentialsFailTemporary()
	{
		$this->assertTrue( self::$user->set( 'enabled', true ) );
		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_TEMPORARY ] ) );
		
		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();
		$errorStack->setCurrentContext( '' );

		$this->assertFalse( self::$auth->getUserWithCredentials( 'test@example.com', 'testpassword' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_login_temporary',
			'message' => 'It looks like your account has not been setup yet. Please go to sign up to finish creating your account.',
			'context' => '',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );
	}

	function testGetUserWithCredentialsFailDisabled()
	{
		Database::delete( 'UserLinks', [ 'uid' => self::$user->id() ] );
		$this->assertTrue( self::$user->set( 'enabled', false ) );

		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();
		$errorStack->setCurrentContext( '' );

		$this->assertFalse( self::$auth->getUserWithCredentials( 'test@example.com', 'testpassword' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_login_disabled',
			'message' => 'Sorry, your account has been disabled.',
			'context' => '',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );
	}

	function testGetUserWithCredentialsFailNotVerified()
	{
		$this->assertTrue( self::$user->set( 'enabled', true ) );

		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_VERIFY_EMAIL ] ) );
		$this->assertTrue( $link->set( 'created_at', '-10 years' ) );

		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();
		$errorStack->setCurrentContext( '' );

		$this->assertFalse( self::$auth->getUserWithCredentials( 'test@example.com', 'testpassword' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_login_unverified',
			'message' => 'You must verify your account with the e-mail that was sent to you before you can log in.',
			'context' => '',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );
	}

	function testGetUserWithCredentials()
	{
		Database::delete( 'UserLinks', [ 'uid' => self::$user->id() ] );
		$this->assertTrue( self::$user->set( 'enabled', true ) );

		$user = self::$auth->getUserWithCredentials( 'test@example.com', 'testpassword' );

		$this->assertInstanceOf( Auth::USER_MODEL, $user );
		$this->assertEquals( self::$user->id(), $user->id() );
	}

	function testSignInUser()
	{
		$user = self::$auth->signInUser( self::$user->id() );

		$this->assertInstanceOf( '\\app\\users\\models\\User', $user );
		$this->assertEquals( $user->id(), self::$user->id() );
		$this->assertTrue( $user->isLoggedIn() );

		$this->assertEquals( 1, UserLoginHistory::totalRecords( [
			'uid' => self::$user->id(),
			'type' => LOGIN_TYPE_TRADITIONAL ] ) );
	}

	function testLogin()
	{
		$this->assertFalse( self::$auth->login( 'test@example.com', 'bogus' ) );

		$this->assertTrue( self::$auth->login( 'test@example.com', 'testpassword' ) );
		$this->assertEquals( self::$user->id(), TestBootstrap::app( 'user' )->id() );
		$this->assertTrue( TestBootstrap::app( 'user' )->isLoggedIn() );
		$this->assertEquals( self::$user->id(), TestBootstrap::app( 'user' )->id() );
	}

	/**
	 * @depends testLogin
	 */
	function testGetAuthenticatedUserSession()
	{
		$this->markTestIncomplete();
	}

	/**
	 * @depends testLogin
	 */
	function testGetAuthenticatedUserPersistentSession()
	{
		$this->markTestIncomplete();
	}

	/**
	 * @depends testLogin
	 */
	function testGetAuthenticatedUserGuest()
	{
		$this->markTestIncomplete();
	}

	/**
	 * @depends testLogin
	 */
	function testLogout()
	{
		$this->markTestIncomplete();
	}

	function testGetTemporaryUser()
	{
		$this->assertFalse( self::$auth->getTemporaryUser( 'test@example.com' ) );

		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_TEMPORARY ] ) );

		$user = self::$auth->getTemporaryUser( 'test@example.com' );
		$this->assertInstanceOf( '\\app\\users\\models\\User', $user );
		$this->assertEquals( self::$user->id(), $user->id() );
	}

	function testUpgradeTemporaryAccount()
	{
		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_TEMPORARY ] ) );

		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_VERIFY_EMAIL ] ) );
		$this->assertTrue( $link->set( 'created_at', '-10 years' ) );

		$this->assertTrue( self::$user->isTemporary() );
		$this->assertFalse( self::$user->isVerified() );

		$this->assertTrue( self::$auth->upgradeTemporaryAccount( self::$user, [
			'first_name' => 'Bob',
			'last_name' => 'Loblaw',
			'user_password' => [ 'testpassword', 'testpassword' ],
			'ip' => '127.0.0.1' ] ) );

		$this->assertFalse( self::$user->isTemporary() );
		$this->assertTrue( self::$user->isVerified() );
	}

	function testSendVerificationEmail()
	{
		$this->assertTrue( self::$auth->sendVerificationEmail( self::$user ) );
		$this->assertFalse( self::$user->isVerified( false ) );
	}

	function testVerifyEmailWithLink()
	{
		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_VERIFY_EMAIL ] ) );
		$this->assertTrue( $link->set( 'created_at', '-10 years' ) );
		$this->assertFalse( self::$user->isVerified() );

		$this->assertFalse( self::$auth->verifyEmailWithLink( 'blah' ) );

		$user = self::$auth->verifyEmailWithLink( $link->link );
		$this->assertInstanceOf( '\\app\\users\\models\\User', $user );
		$this->assertEquals( $user->id(), self::$user->id() );
		$this->assertTrue( self::$user->isVerified() );
	}

	function testGetUserFromForgotToken()
	{
		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_FORGOT_PASSWORD ] ) );

		$this->assertFalse( self::$auth->getUserFromForgotToken( 'blah' ) );
		
		$user = self::$auth->getUserFromForgotToken( $link->link );
		$this->assertInstanceOf( '\\app\\users\\models\\User', $user );
		$this->assertEquals( self::$user->id(), $user->id() );

		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();
		$errorStack->setCurrentContext( '' );

		$link->set( 'created_at', '-10 years' );
		$this->assertFalse( self::$auth->getUserFromForgotToken( $link->link ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_forgot_expired_invalid',
			'message' => 'This link has expired or is invalid.',
			'context' => 'UserLink.set',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );
	}

	function testForgotStep1()
	{
		Database::delete( 'UserLinks', [
			'link_type' => USER_LINK_FORGOT_PASSWORD,
			'uid' => self::$user->id() ] );

		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();

		$this->assertFalse( self::$auth->forgotStep1( 'invalidemail', '127.0.0.1' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'validation_failed',
			'message' => 'Email is invalid',
			'context' => 'auth.forgot',
			'params' => [ 'field' => 'email', 'field_name' => 'Email' ] ] ];
		$this->assertEquals( $expected, $errors );

		$errorStack = TestBootstrap::app( 'errors' );
		$errorStack->clear();

		$this->assertFalse( self::$auth->forgotStep1( 'nomatch@example.com', '127.0.0.1' ) );

		$errors = $errorStack->errors();
		$expected = [ [
			'error' => 'user_forgot_email_no_match',
			'message' => 'We could not find a match for that e-mail address.',
			'context' => 'auth.forgot',
			'params' => [] ] ];
		$this->assertEquals( $expected, $errors );

		$this->assertTrue( self::$auth->forgotStep1( 'test@example.com', '127.0.0.1' ) );
		$this->assertEquals( 1, UserLink::totalRecords( [
			'link_type' => USER_LINK_FORGOT_PASSWORD,
			'uid' => self::$user->id() ] ) );
	}

	function testForgotStep2()
	{
		Database::delete( 'UserLinks', [
			'link_type' => USER_LINK_FORGOT_PASSWORD,
			'uid' => self::$user->id() ] );
		$link = new UserLink;
		$link->grantAllPermissions();
		$this->assertTrue( $link->create( [
			'uid' => self::$user->id(),
			'link_type' => USER_LINK_FORGOT_PASSWORD ] ) );

		$this->assertFalse( self::$auth->forgotStep2( 'blah', [ 'password', 'password' ] ) );

		$oldUserPassword = self::$user->user_password;
		$this->assertTrue( self::$auth->forgotStep2( $link->link, [ 'testpassword2', 'testpassword2' ] ) );
		self::$user->load();
		$this->assertNotEquals( $oldUserPassword, self::$user->user_password );
		$this->assertEquals( 0, UserLink::totalRecords( [
			'link_type' => USER_LINK_FORGOT_PASSWORD,
			'uid' => self::$user->id() ] ) );
	}
}