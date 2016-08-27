<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Users\Models\User;
use Infuse\Auth\Libs\Auth;
use Infuse\Auth\Models\UserLink;
use Infuse\Auth\Models\UserLoginHistory;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class AuthTest extends PHPUnit_Framework_TestCase
{
    public static $user;
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
        $this->assertInstanceOf('Infuse\Auth\Libs\Auth', Test::$app['auth']);
    }

    public function testGetRequest()
    {
        $auth = $this->getAuth();
        $req = new Request();
        $auth->setRequest($req);
        $this->assertEquals($req, $auth->getRequest());
    }

    public function testGetResponse()
    {
        $auth = $this->getAuth();
        $res = new Response();
        $auth->setResponse($res);
        $this->assertEquals($res, $auth->getResponse());
    }

    public function testGetUserClass()
    {
        $auth = $this->getAuth();
        $this->assertEquals('App\Users\Models\User', $auth->getUserClass());
        Test::$app['config']->set('users.model', 'SomeOtherClass');
        $this->assertEquals('SomeOtherClass', $auth->getUserClass());
        Test::$app['config']->set('users.model', false);
        $this->assertEquals('App\Users\Models\User', $auth->getUserClass());
    }

    public function testGetUserWithCredentialsBadUsername()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid username.');

        $auth = $this->getAuth();
        $auth->getUserWithCredentials('', '');
    }

    public function testGetUserWithCredentialsBadPassword()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid password.');

        $auth = $this->getAuth();
        $auth->getUserWithCredentials('test@example.com', '');
    }

    public function testGetUserWithCredentialsFailTemporary()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::TEMPORARY, ]));

        $auth = $this->getAuth();
        $auth->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentialsFailDisabled()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('user_id', self::$user->id())
            ->execute();

        self::$user->enabled = false;
        $this->assertTrue(self::$user->save());

        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Sorry, your account has been disabled.');

        $auth = $this->getAuth();
        $auth->getUserWithCredentials('test@example.com', 'testpassword');
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

        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'You must verify your account with the email that was sent to you before you can log in.');

        $auth = $this->getAuth();
        $auth->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentials()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('user_id', self::$user->id())
            ->execute();

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $auth = $this->getAuth();
        $user = $auth->getUserWithCredentials('test@example.com', 'testpassword');

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testSignInUser()
    {
        $auth = $this->getAuth();
        $user = $auth->signInUser(self::$user->id());

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals($user->id(), self::$user->id());
        $this->assertTrue($user->isSignedIn());

        $this->assertEquals(1, UserLoginHistory::totalRecords([
            'user_id' => self::$user->id(),
            'type' => 'web', ]));
    }

    public function testLoginFail()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'We could not find a match for that email address and password.');

        $auth = $this->getAuth();
        $auth->login('test@example.com', 'bogus');
    }

    public function testLogin()
    {
        $auth = $this->getAuth();
        $this->assertTrue($auth->login('test@example.com', 'testpassword'));
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
        $auth = $this->getAuth();
        $this->assertFalse($auth->getTemporaryUser('test@example.com'));

        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::TEMPORARY, ]));

        $user = $auth->getTemporaryUser('test@example.com');
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

        $auth = $this->getAuth();

        $this->assertTrue($auth->upgradeTemporaryAccount(self::$user, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'user_password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1', ]));

        $this->assertFalse(self::$user->isTemporary());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testSendVerificationEmail()
    {
        $auth = $this->getAuth();
        $this->assertTrue($auth->sendVerificationEmail(self::$user));
        $this->assertFalse(self::$user->isVerified(false));
    }

    public function testVerifyEmailWithTokenInvalid()
    {
        $auth = $this->getAuth();
        $this->assertFalse($auth->verifyEmailWithToken('blah'));
    }

    public function testVerifyEmailWithToken()
    {
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::VERIFY_EMAIL,
            'created_at' => '-10 years', ]));
        $this->assertFalse(self::$user->isVerified());

        $auth = $this->getAuth();

        $user = $auth->verifyEmailWithToken($link->link);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testGetUserFromForgotTokenInvalid()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        $auth = $this->getAuth();
        $auth->getUserFromForgotToken('blah');
    }

    public function testGetUserFromForgotToken()
    {
        self::$link = new UserLink();
        $this->assertTrue(self::$link->create([
            'user_id' => self::$user->id(),
            'link_type' => UserLink::FORGOT_PASSWORD, ]));

        $auth = $this->getAuth();
        $user = $auth->getUserFromForgotToken(self::$link->link);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    /**
     * @depends testGetUserFromForgotToken
     */
    public function testGetUserFromForgotTokenExpired()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        self::$link->created_at = '-10 years';
        $this->assertTrue(self::$link->save());

        $auth = $this->getAuth();
        $auth->getUserFromForgotToken(self::$link->link);
    }

    public function testForgotStep1ValidationFailed()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid email address.');

        $auth = $this->getAuth();
        $auth->forgotStep1('invalidemail', '127.0.0.1');
    }

    public function testForgotSetp1NoEmailMatch()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'We could not find a match for that email address.');

        $auth = $this->getAuth();
        $auth->forgotStep1('nomatch@example.com', '127.0.0.1');
    }

    public function testForgotStep1()
    {
        $auth = $this->getAuth();
        $this->assertTrue($auth->forgotStep1('test@example.com', '127.0.0.1'));
        $this->assertEquals(1, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));
    }

    public function testForgotStep2Invalid()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        $auth = $this->getAuth();
        $auth->forgotStep2('blah', ['password', 'password'], '127.0.0.1');
    }

    public function testForgotStep2BadPassword()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid password.');

        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $link = new UserLink();
        $link->user_id = self::$user->id();
        $link->link_type = UserLink::FORGOT_PASSWORD;
        $this->assertTrue($link->save());

        $auth = $this->getAuth();
        $auth->forgotStep2($link->link, ['f', 'f'], '127.0.0.1');
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

        $auth = $this->getAuth();

        $oldUserPassword = self::$user->user_password;
        $this->assertTrue($auth->forgotStep2($link->link, ['testpassword2', 'testpassword2'], '127.0.0.1'));
        self::$user->refresh();
        $this->assertNotEquals($oldUserPassword, self::$user->user_password);
        $this->assertEquals(0, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));
    }

    private function getAuth()
    {
        $auth = new Auth();
        $auth->setApp(Test::$app)
             ->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
             ->setResponse(new Response());

        return $auth;
    }
}
