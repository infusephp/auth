<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Auth\Libs\Auth;
use App\Auth\Models\UserLink;
use App\Auth\Models\UserLoginHistory;
use App\Users\Models\User;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class AuthTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $auth;
    public static $ogUserId;
    public static $link;

    public static function setUpBeforeClass()
    {
        Test::$app['user']->promoteToSuperUser();

        Test::$app['db']->delete('Users')
            ->where('user_email', 'test@example.com')
            ->execute();

        self::$user = User::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'user_email' => 'test@example.com',
            'user_password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);
        self::$user->grantAllPermissions();

        Test::$app['user']->demoteToNormalUser();

        self::$ogUserId = Test::$app['user']->id();
    }

    public static function tearDownAfterClass()
    {
        foreach ([self::$user] as $u) {
            if ($u) {
                $u->grantAllPermissions();
                $u->delete();
            }
        }
    }

    public function assertPreConditions()
    {
        $this->assertInstanceOf('App\Users\Models\User', self::$user);
    }

    public function assertPostConditions()
    {
        $app = Test::$app;
        if (!$app['user']->isSignedIn()) {
            $app['user'] = new User(self::$ogUserId, true);
        }
    }

    public function testDI()
    {
        $this->assertInstanceOf('App\Auth\Libs\Auth', Test::$app['auth']);
    }

    public function testConstruct()
    {
        $req = new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']);
        $res = new Response();

        self::$auth = new Auth();
        self::$auth->setApp(Test::$app)
                   ->setRequest($req)
                   ->setResponse($res);

        $this->assertEquals($req, self::$auth->getRequest());
        $this->assertEquals($res, self::$auth->getResponse());
    }

    public function testGetUserClass()
    {
        $this->assertEquals('App\Users\Models\User', self::$auth->getUserClass());
        Test::$app['config']->set('users.model', 'SomeOtherClass');
        $this->assertEquals('SomeOtherClass', self::$auth->getUserClass());
        Test::$app['config']->set('users.model', false);
        $this->assertEquals('App\Users\Models\User', self::$auth->getUserClass());
    }

    public function testGetUserWithCredentialsBadUsername()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'Please enter a valid username.');

        self::$auth->getUserWithCredentials('', '');
    }

    public function testGetUserWithCredentialsBadPassword()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'Please enter a valid password.');

        self::$auth->getUserWithCredentials('test@example.com', '');
    }

    public function testGetUserWithCredentialsFailTemporary()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::TEMPORARY, ]));

        self::$auth->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentialsFailDisabled()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('user_id', self::$user->id())
            ->execute();

        self::$user->enabled = false;
        $this->assertTrue(self::$user->save());

        $this->setExpectedException('App\Auth\Exception\AuthException', 'Sorry, your account has been disabled.');

        self::$auth->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentialsFailNotVerified()
    {
        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::VERIFY_EMAIL, ]));
        $link->created_at = '-10 years';
        $this->assertTrue($link->save());

        $this->setExpectedException('App\Auth\Exception\AuthException', 'You must verify your account with the email that was sent to you before you can log in.');

        self::$auth->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentials()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('user_id', self::$user->id())
            ->execute();

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $user = self::$auth->getUserWithCredentials('test@example.com', 'testpassword');

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testSignInUser()
    {
        $user = self::$auth->signInUser(self::$user->id());

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals($user->id(), self::$user->id());
        $this->assertTrue($user->isSignedIn());

        $this->assertEquals(1, UserLoginHistory::totalRecords([
            'user_id' => self::$user->id(),
            'type' => 'web', ]));
    }

    public function testLoginFail()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'We could not find a match for that email address and password.');

        self::$auth->login('test@example.com', 'bogus');
    }

    public function testLogin()
    {
        $this->assertTrue(self::$auth->login('test@example.com', 'testpassword'));
        $this->assertEquals(self::$user->id(), Test::$app['user']->id());
        $this->assertTrue(Test::$app['user']->isSignedIn());
        $this->assertEquals(self::$user->id(), Test::$app['user']->id());
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
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::TEMPORARY, ]));

        $user = self::$auth->getTemporaryUser('test@example.com');
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testUpgradeTemporaryAccount()
    {
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::TEMPORARY, ]));

        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::VERIFY_EMAIL, ]));
        $link->created_at = '-10 years';
        $this->assertTrue($link->save());

        $this->assertTrue(self::$user->isTemporary());
        $this->assertFalse(self::$user->isVerified());

        $this->assertTrue(self::$auth->upgradeTemporaryAccount(self::$user, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'user_password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1', ]));

        $this->assertFalse(self::$user->isTemporary());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testSendVerificationEmail()
    {
        $this->assertTrue(self::$auth->sendVerificationEmail(self::$user));
        $this->assertFalse(self::$user->isVerified(false));
    }

    public function testVerifyEmailWithTokenInvalid()
    {
        $this->assertFalse(self::$auth->verifyEmailWithToken('blah'));
    }

    public function testVerifyEmailWithToken()
    {
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::VERIFY_EMAIL,
            'created_at' => '-10 years', ]));
        $this->assertFalse(self::$user->isVerified());

        $user = self::$auth->verifyEmailWithToken($link->link);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testGetUserFromForgotTokenInvalid()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        self::$auth->getUserFromForgotToken('blah');
    }

    public function testGetUserFromForgotToken()
    {
        self::$link = new UserLink();
        $this->assertTrue(self::$link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::FORGOT_PASSWORD, ]));

        $user = self::$auth->getUserFromForgotToken(self::$link->link);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    /**
     * @depends testGetUserFromForgotToken
     */
    public function testGetUserFromForgotTokenExpired()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        self::$link->created_at = '-10 years';
        $this->assertTrue(self::$link->save());
        self::$auth->getUserFromForgotToken(self::$link->link);
    }

    public function testForgotStep1ValidationFailed()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $this->setExpectedException('App\Auth\Exception\AuthException', 'Please enter a valid email address.');

        self::$auth->forgotStep1('invalidemail', '127.0.0.1');
    }

    public function testForgotSetp1NoEmailMatch()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'We could not find a match for that email address.');

        self::$auth->forgotStep1('nomatch@example.com', '127.0.0.1');
    }

    public function testForgotStep1()
    {
        $this->assertTrue(self::$auth->forgotStep1('test@example.com', '127.0.0.1'));
        $this->assertEquals(1, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));
    }

    public function testForgotStep2Invalid()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        self::$auth->forgotStep2('blah', ['password', 'password'], '127.0.0.1');
    }

    public function testForgotStep2BadPassword()
    {
        $this->setExpectedException('App\Auth\Exception\AuthException', 'Please enter a valid password.');

        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $link = new UserLink();
        $link->user_id = self::$user->id();
        $link->link_type = UserLink::FORGOT_PASSWORD;
        $this->assertTrue($link->save());

        self::$auth->forgotStep2($link->link, ['f', 'f'], '127.0.0.1');
    }

    public function testForgotStep2()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $link = new UserLink();
        $link->user_id = self::$user->id();
        $link->link_type = UserLink::FORGOT_PASSWORD;
        $this->assertTrue($link->save());

        $oldUserPassword = self::$user->user_password;
        $this->assertTrue(self::$auth->forgotStep2($link->link, ['testpassword2', 'testpassword2'], '127.0.0.1'));
        self::$user->refresh();
        $this->assertNotEquals($oldUserPassword, self::$user->user_password);
        $this->assertEquals(0, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));
    }
}
