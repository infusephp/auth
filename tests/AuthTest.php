<?php

use app\auth\libs\Auth;
use app\auth\models\UserLink;
use app\auth\models\UserLoginHistory;

class AuthTest extends \PHPUnit_Framework_TestCase
{
    public static $user;
    public static $auth;
    public static $ogUserId;

    public static function setUpBeforeClass()
    {
        $userModel = Auth::USER_MODEL;

        TestBootstrap::app('user')->enableSU();

        TestBootstrap::app('db')->delete('Users')
            ->where('user_email', 'test@example.com')->execute();

        self::$user = $userModel::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'user_email' => 'test@example.com',
            'user_password' => [ 'testpassword', 'testpassword' ],
            'ip' => '127.0.0.1',
        ]);
        self::$user->grantAllPermissions();

        TestBootstrap::app('user')->disableSU();

        self::$ogUserId = TestBootstrap::app('user')->id();
    }

    public static function tearDownAfterClass()
    {
        foreach ([ self::$user ] as $u) {
            if ($u) {
                $u->grantAllPermissions();
                $u->delete();
            }
        }
    }

    public function assertPreConditions()
    {
        $this->assertInstanceOf(Auth::USER_MODEL, self::$user);
    }

    public function assertPostConditions()
    {
        $userModel = Auth::USER_MODEL;

        $app = TestBootstrap::app();
        if (!$app[ 'user' ]->isLoggedIn()) {
            $app[ 'user' ] = new $userModel(self::$ogUserId, true);
        }
    }

    public function testConstruct()
    {
        // self::$app = new App();
        self::$auth = new Auth(TestBootstrap::app());
    }

    public function testGetUserWithCredentialsFail()
    {
        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();
        $errorStack->setCurrentContext('');

        $this->assertFalse(self::$auth->getUserWithCredentials('', ''));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'user_bad_username',
            'message' => 'user_bad_username',
            'context' => '',
            'params' => [], ] ];
        $this->assertEquals($expected, $errors);

        $errorStack->clear();

        $this->assertFalse(self::$auth->getUserWithCredentials('test@example.com', ''));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'user_bad_password',
            'message' => 'user_bad_password',
            'context' => '',
            'params' => [], ] ];
        $this->assertEquals($expected, $errors);
    }

    public function testGetUserWithCredentialsFailTemporary()
    {
        $this->assertTrue(self::$user->set('enabled', true));
        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_TEMPORARY, ]));

        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();
        $errorStack->setCurrentContext('');

        $this->assertFalse(self::$auth->getUserWithCredentials('test@example.com', 'testpassword'));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'user_login_temporary',
            'message' => 'user_login_temporary',
            'context' => '',
            'params' => [], ] ];
        $this->assertEquals($expected, $errors);
    }

    public function testGetUserWithCredentialsFailDisabled()
    {
        TestBootstrap::app('db')->delete('UserLinks')
            ->where('uid', self::$user->id())->execute();

        $this->assertTrue(self::$user->set('enabled', false));

        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();
        $errorStack->setCurrentContext('');

        $this->assertFalse(self::$auth->getUserWithCredentials('test@example.com', 'testpassword'));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'user_login_disabled',
            'message' => 'user_login_disabled',
            'context' => '',
            'params' => [], ] ];
        $this->assertEquals($expected, $errors);
    }

    public function testGetUserWithCredentialsFailNotVerified()
    {
        $this->assertTrue(self::$user->set('enabled', true));

        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_VERIFY_EMAIL, ]));
        $this->assertTrue($link->set('created_at', '-10 years'));

        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();
        $errorStack->setCurrentContext('');

        $this->assertFalse(self::$auth->getUserWithCredentials('test@example.com', 'testpassword'));

        $errors = $errorStack->errors();
        $expected = [
            [
                'error' => 'user_login_unverified',
                'message' => 'user_login_unverified',
                'context' => '',
                'params' => [
                    'uid' => self::$user->id(), ], ], ];
        $this->assertEquals($expected, $errors);
    }

    public function testGetUserWithCredentials()
    {
        TestBootstrap::app('db')->delete('UserLinks')
            ->where('uid', self::$user->id())->execute();

        $this->assertTrue(self::$user->set('enabled', true));

        $user = self::$auth->getUserWithCredentials('test@example.com', 'testpassword');

        $this->assertInstanceOf(Auth::USER_MODEL, $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testSignInUser()
    {
        $user = self::$auth->signInUser(self::$user->id());

        $this->assertInstanceOf('\\app\\users\\models\\User', $user);
        $this->assertEquals($user->id(), self::$user->id());
        $this->assertTrue($user->isLoggedIn());

        $this->assertEquals(1, UserLoginHistory::totalRecords([
            'uid' => self::$user->id(),
            'type' => 'web', ]));
    }

    public function testLogin()
    {
        $this->assertFalse(self::$auth->login('test@example.com', 'bogus'));

        $this->assertTrue(self::$auth->login('test@example.com', 'testpassword'));
        $this->assertEquals(self::$user->id(), TestBootstrap::app('user')->id());
        $this->assertTrue(TestBootstrap::app('user')->isLoggedIn());
        $this->assertEquals(self::$user->id(), TestBootstrap::app('user')->id());
    }

    /**
     * @depends testLogin
     */
    public function testGetAuthenticatedUserSession()
    {
        $this->markTestIncomplete();
    }

    /**
     * @depends testLogin
     */
    public function testGetAuthenticatedUserPersistentSession()
    {
        $this->markTestIncomplete();
    }

    /**
     * @depends testLogin
     */
    public function testGetAuthenticatedUserGuest()
    {
        $this->markTestIncomplete();
    }

    /**
     * @depends testLogin
     */
    public function testLogout()
    {
        $this->markTestIncomplete();
    }

    public function testGetTemporaryUser()
    {
        $this->assertFalse(self::$auth->getTemporaryUser('test@example.com'));

        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_TEMPORARY, ]));

        $user = self::$auth->getTemporaryUser('test@example.com');
        $this->assertInstanceOf('\\app\\users\\models\\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testUpgradeTemporaryAccount()
    {
        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_TEMPORARY, ]));

        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_VERIFY_EMAIL, ]));
        $this->assertTrue($link->set('created_at', '-10 years'));

        $this->assertTrue(self::$user->isTemporary());
        $this->assertFalse(self::$user->isVerified());

        $this->assertTrue(self::$auth->upgradeTemporaryAccount(self::$user, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'user_password' => [ 'testpassword', 'testpassword' ],
            'ip' => '127.0.0.1' ]));

        $this->assertFalse(self::$user->isTemporary());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testSendVerificationEmail()
    {
        $this->assertTrue(self::$auth->sendVerificationEmail(self::$user));
        $this->assertFalse(self::$user->isVerified(false));
    }

    public function testVerifyEmailWithLink()
    {
        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_VERIFY_EMAIL, ]));
        $this->assertTrue($link->set('created_at', '-10 years'));
        $this->assertFalse(self::$user->isVerified());

        $this->assertFalse(self::$auth->verifyEmailWithLink('blah'));

        $user = self::$auth->verifyEmailWithLink($link->link);
        $this->assertInstanceOf('\\app\\users\\models\\User', $user);
        $this->assertEquals($user->id(), self::$user->id());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testGetUserFromForgotToken()
    {
        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_FORGOT_PASSWORD, ]));

        $this->assertFalse(self::$auth->getUserFromForgotToken('blah'));

        $user = self::$auth->getUserFromForgotToken($link->link);
        $this->assertInstanceOf('\\app\\users\\models\\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());

        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();
        $errorStack->setCurrentContext('');

        $link->set('created_at', '-10 years');
        $this->assertFalse(self::$auth->getUserFromForgotToken($link->link));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'user_forgot_expired_invalid',
            'message' => 'user_forgot_expired_invalid',
            'context' => 'UserLink.set',
            'params' => [], ] ];
        $this->assertEquals($expected, $errors);
    }

    public function testForgotStep1()
    {
        TestBootstrap::app('db')->delete('UserLinks')->where('link_type', USER_LINK_FORGOT_PASSWORD)
            ->where('uid', self::$user->id())->execute();

        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();

        $this->assertFalse(self::$auth->forgotStep1('invalidemail', '127.0.0.1'));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'validation_failed',
            'message' => 'validation_failed',
            'context' => 'auth.forgot',
            'params' => [ 'field' => 'email', 'field_name' => 'Email' ], ] ];
        $this->assertEquals($expected, $errors);

        $errorStack = TestBootstrap::app('errors');
        $errorStack->clear();

        $this->assertFalse(self::$auth->forgotStep1('nomatch@example.com', '127.0.0.1'));

        $errors = $errorStack->errors();
        $expected = [ [
            'error' => 'user_forgot_email_no_match',
            'message' => 'user_forgot_email_no_match',
            'context' => 'auth.forgot',
            'params' => [], ] ];
        $this->assertEquals($expected, $errors);

        $this->assertTrue(self::$auth->forgotStep1('test@example.com', '127.0.0.1'));
        $this->assertEquals(1, UserLink::totalRecords([
            'link_type' => USER_LINK_FORGOT_PASSWORD,
            'uid' => self::$user->id(), ]));
    }

    public function testForgotStep2()
    {
        TestBootstrap::app('db')->delete('UserLinks')->where('link_type', USER_LINK_FORGOT_PASSWORD)
            ->where('uid', self::$user->id())->execute();

        $link = new UserLink();
        $link->grantAllPermissions();
        $this->assertTrue($link->create([
            'uid' => self::$user->id(),
            'link_type' => USER_LINK_FORGOT_PASSWORD, ]));

        $this->assertFalse(self::$auth->forgotStep2('blah', ['password', 'password'], '127.0.0.1'));

        $oldUserPassword = self::$user->user_password;
        $this->assertTrue(self::$auth->forgotStep2($link->link, ['testpassword2', 'testpassword2'], '127.0.0.1'));
        self::$user->load();
        $this->assertNotEquals($oldUserPassword, self::$user->user_password);
        $this->assertEquals(0, UserLink::totalRecords([
            'link_type' => USER_LINK_FORGOT_PASSWORD,
            'uid' => self::$user->id(), ]));
    }
}
