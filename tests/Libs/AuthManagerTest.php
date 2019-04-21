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
use Infuse\Auth\Exception\AuthException;
use Infuse\Auth\Libs\AuthManager;
use Infuse\Auth\Libs\UserInvites;
use Infuse\Auth\Models\AccountSecurityEvent;
use Infuse\Auth\Models\ActiveSession;
use Infuse\Auth\Models\PersistentSession;
use Infuse\Auth\Models\UserLink;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class AuthManagerTest extends MockeryTestCase
{
    public static $user;
    public static $ogUserId;

    public static function setUpBeforeClass()
    {
        Test::$app['database']->getDefault()
            ->delete('Users')
            ->where('email', 'test@example.com')
            ->execute();

        self::$user = Test::$app['auth']->getUserRegistration()->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);

        self::$ogUserId = Test::$app['user']->id();
    }

    public static function tearDownAfterClass()
    {
        if (self::$user) {
            self::$user->grantAllPermissions()->delete();
        }
    }

    public function assertPostConditions()
    {
        $app = Test::$app;
        if (!$app['user']->isSignedIn()) {
            $app['user'] = new User(self::$ogUserId);
            $app['user']->markSignedIn();
        }
    }

    public function testDI()
    {
        $this->assertInstanceOf('Infuse\Auth\Libs\AuthManager', Test::$app['auth']);
    }

    public function testGetStrategy()
    {
        $auth = $this->getAuth();
        $strategy = $auth->getStrategy('traditional');
        $this->assertInstanceOf('Infuse\Auth\Libs\Strategy\TraditionalStrategy', $strategy);
        $this->assertEquals($strategy, $auth->getStrategy('traditional'));
    }

    public function testGetStrategyFail()
    {
        $this->expectException('InvalidArgumentException');

        $this->getAuth()->getStrategy('does_not_exist');
    }

    public function testRegisterStrategy()
    {
        $auth = $this->getAuth();
        $this->assertEquals($auth, $auth->registerStrategy('test', 'stdClass'));

        $this->assertInstanceOf('stdClass', $auth->getStrategy('test'));
    }

    public function testGetStorage()
    {
        $auth = $this->getAuth();
        $this->assertInstanceOf('Infuse\Auth\Libs\Storage\SessionStorage', $auth->getStorage());

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $this->assertEquals($auth, $auth->setStorage($storage));
        $this->assertEquals($storage, $auth->getStorage());
    }

    public function testGetTwoFactorStrategy()
    {
        $auth = $this->getAuth();
        $this->assertNull($auth->getTwoFactorStrategy());

        $strategy = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $this->assertEquals($auth, $auth->setTwoFactorStrategy($strategy));
        $this->assertEquals($strategy, $auth->getTwoFactorStrategy());
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

    function testGetCurrentUser()
    {
        $auth = $this->getAuth();
        $user = $auth->getCurrentUser();
        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse($user->isSignedIn());
        $this->assertEquals($user, Test::$app['user']);

        $user = new User(1234);
        $auth->setCurrentUser($user);
        $this->assertEquals($user, $auth->getCurrentUser());
        $this->assertEquals($user, Test::$app['user']);
    }

    public function testGetUserClass()
    {
        $auth = $this->getAuth();
        $this->assertEquals('App\Users\Models\User', $auth->getUserClass());
        Test::$app['config']->set('users.model', 'SomeOtherClass');
        $this->assertEquals('SomeOtherClass', $auth->getUserClass());
        Test::$app['config']->set('users.model', null);
        $this->assertEquals('App\Users\Models\User', $auth->getUserClass());
    }

    public function testAuthenticate()
    {
        $this->expectException('Infuse\Auth\Exception\AuthException', 'Please enter a valid username.');

        $auth = $this->getAuth();

        $this->assertTrue($auth->authenticate('traditional'));
    }

    public function testGetAuthenticatedUser()
    {
        $auth = $this->getAuth();

        $user = new User(1234);

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('getAuthenticatedUser')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn($user)
                ->once();
        $auth->setStorage($storage);

        $this->assertEquals($user, $auth->getAuthenticatedUser());
    }

    public function testGetAuthenticatedUserFail()
    {
        self::$user->markSignedOut();
        $auth = $this->getAuth();

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('getAuthenticatedUser')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn(false)
                ->once();
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $user = $auth->getAuthenticatedUser();

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(-1, $user->id());
        $this->assertFalse($user->isSignedIn());
    }

    public function testGetAuthenticatedUserNeedsTwoFactor()
    {
        $auth = $this->getAuth();
        $twoFactor = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $twoFactor->shouldReceive('needsVerification')
                  ->andReturn(true)
                  ->once();
        $auth->setTwoFactorStrategy($twoFactor);

        $user = new User(1234);
        $user->markSignedIn();

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('getAuthenticatedUser')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn($user)
                ->once();
        $auth->setStorage($storage);

        $this->assertEquals($user, $auth->getAuthenticatedUser());
        $this->assertFalse($user->isSignedIn());
        $this->assertFalse($user->isTwoFactorVerified());
    }

    public function testGetAuthenticatedUserTwoFactorVerified()
    {
        $auth = $this->getAuth();
        $twoFactor = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $auth->setTwoFactorStrategy($twoFactor);

        $user = new User(1234);
        $user->markSignedIn()->markTwoFactorVerified();

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('getAuthenticatedUser')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn($user)
                ->once();
        $auth->setStorage($storage);

        $this->assertEquals($user, $auth->getAuthenticatedUser());
        $this->assertTrue($user->isSignedIn());
        $this->assertTrue($user->isTwoFactorVerified());
    }

    public function testSignInUser()
    {
        self::$user->markSignedOut();

        unset(Test::$app['user']);

        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $user = $auth->signInUser(self::$user);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());

        $this->assertEquals($user, Test::$app['user']);

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.login')
            ->where('auth_strategy', 'web')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testSignInUserNeedsTwoFactor()
    {
        self::$user->markSignedIn();

        unset(Test::$app['user']);

        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $twoFactor = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $twoFactor->shouldReceive('needsVerification')
                  ->andReturn(true)
                  ->once();
        $auth->setTwoFactorStrategy($twoFactor);

        $user = $auth->signInUser(self::$user);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertFalse($user->isSignedIn());
        $this->assertFalse($user->isTwoFactorVerified());

        $this->assertEquals($user, Test::$app['user']);
    }

    public function testSignInUserTwoFactorVerified()
    {
        self::$user->markSignedOut();
        self::$user->markTwoFactorVerified();

        unset(Test::$app['user']);

        $auth = $this->getAuth();
        $twoFactor = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $auth->setTwoFactorStrategy($twoFactor);

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $user = $auth->signInUser(self::$user, '2fa_test');

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());
        $this->assertTrue($user->isTwoFactorVerified());

        $this->assertEquals($user, Test::$app['user']);

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.login')
            ->where('auth_strategy', '2fa_test')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testSignInUserRemember()
    {
        unset(Test::$app['user']);

        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $storage->shouldReceive('remember')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $user = $auth->signInUser(self::$user, 'test_strat', true);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());

        $this->assertEquals($user, Test::$app['user']);

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.login')
            ->where('auth_strategy', 'test_strat')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testSignInUserRememberFail()
    {
        $this->expectException('Infuse\Auth\Exception\AuthException');

        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true);
        $storage->shouldReceive('remember')
                ->andReturn(false);
        $auth->setStorage($storage);

        $auth->signInUser(self::$user, 'test_strat', true);
    }

    public function testSignInUserGuest()
    {
        unset(Test::$app['user']);

        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $user = new User(-1);
        $user = $auth->signInUser($user);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(-1, $user->id());
        $this->assertFalse($user->isSignedIn());

        $this->assertEquals($user, Test::$app['user']);

        $this->assertEquals(0, AccountSecurityEvent::where('user_id', -1)->count());
    }

    public function testVerifyTwoFactor()
    {
        $auth = $this->getAuth();

        $user = self::$user;
        $user->markSignedOut();

        $strategy = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $strategy->shouldReceive('verify')
                 ->withArgs([$user, 'token'])
                 ->once();
        $auth->setTwoFactorStrategy($strategy);

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('twoFactorVerified')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $this->assertEquals($auth, $auth->verifyTwoFactor($user, 'token'));

        $this->assertTrue($user->isTwoFactorVerified());
        $this->assertTrue($user->isSignedIn());
    }

    public function testVerifyTwoFactorException()
    {
        $this->expectException('Infuse\Auth\Exception\AuthException');

        $auth = $this->getAuth();

        $user = new User(10);

        $strategy = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $strategy->shouldReceive('verify')
                 ->andThrow(new AuthException('fail'));
        $auth->setTwoFactorStrategy($strategy);

        $auth->verifyTwoFactor($user, 'token');
    }

    public function testVerifyTwoFactorFail()
    {
        $this->expectException('Infuse\Auth\Exception\AuthException');

        $auth = $this->getAuth();

        $user = new User(10);
        $user->markSignedIn();

        $strategy = Mockery::mock('Infuse\Auth\Interfaces\TwoFactorInterface');
        $strategy->shouldReceive('verify');
        $auth->setTwoFactorStrategy($strategy);

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('twoFactorVerified')
                ->andReturn(false);
        $auth->setStorage($storage);

        $auth->verifyTwoFactor($user, 'token');
    }

    public function testSignInUserFail()
    {
        $this->expectException('Infuse\Auth\Exception\AuthException');

        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(false);
        $auth->setStorage($storage);

        $user = new User(-1);
        $auth->signInUser($user);
    }

    public function testLogout()
    {
        $auth = $this->getAuth();

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signOut')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn(true)
                ->once();
        $storage->shouldReceive('signIn')
                ->andReturn(true);
        $auth->setStorage($storage);

        $this->assertEquals($auth, $auth->logout());
        $this->assertInstanceOf($auth->getUserClass(), Test::$app['user']);
        $this->assertEquals(-1, Test::$app['user']->id());
        $this->assertFalse(Test::$app['user']->isSignedIn());
    }

    public function testLogoutFail()
    {
        $this->expectException('Infuse\Auth\Exception\AuthException');

        $auth = $this->getAuth();

        $storage = Mockery::mock('Infuse\Auth\Interfaces\StorageInterface');
        $storage->shouldReceive('signOut')
                ->andReturn(false)
                ->once();
        $auth->setStorage($storage);

        $auth->logout();
    }

    public function testSignOutAllSessions()
    {
        $auth = $this->getAuth();

        // create sessions
        $session = new ActiveSession();
        $session->id = 'sesh_1234';
        $session->user_id = self::$user->id();
        $session->ip = '127.0.0.1';
        $session->user_agent = 'Firefox';
        $session->expires = strtotime('+1 month');
        $this->assertTrue($session->save());

        $persistent = new PersistentSession();
        $persistent->email = 'test@example.com';
        $persistent->user_id = self::$user->id();
        $persistent->series = self::$user->email;
        $persistent->series = str_repeat('a', 128);
        $persistent->token = str_repeat('a', 128);
        $this->assertTrue($persistent->save());

        $this->assertTrue($auth->signOutAllSessions(self::$user));

        $n = ActiveSession::where('id', 'sesh_1234')
            ->where('valid', false)
            ->count();
        $this->assertEquals(1, $n);

        $this->assertEquals(0, PersistentSession::where('user_id', self::$user->id())->count());
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
        $link->user_id = self::$user->id();
        $link->type = UserLink::VERIFY_EMAIL;
        $this->assertTrue($link->save());
        $link->created_at = strtotime('-10 years');
        $this->assertTrue($link->save());
        $this->assertFalse(self::$user->isVerified());

        $auth = $this->getAuth();

        $user = $auth->verifyEmailWithToken($link->link);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue(self::$user->isVerified());
    }

    public function testGetUserFromForgotToken()
    {
        $reset = Mockery::mock('Infuse\Auth\Libs\ResetPassword');
        $reset->shouldReceive('getUserFromToken')
              ->withArgs(['forgot_token_1234'])
              ->andReturn(true)
              ->once();

        $auth = $this->getAuth()->setPasswordReset($reset);

        $this->assertTrue($auth->getUserFromForgotToken('forgot_token_1234'));
    }

    public function testGetPasswordReset()
    {
        $auth = $this->getAuth();
        $this->assertInstanceOf('Infuse\Auth\Libs\ResetPassword', $auth->getPasswordReset());
    }

    public function testForgotStep1()
    {
        $reset = Mockery::mock('Infuse\Auth\Libs\ResetPassword');
        $reset->shouldReceive('step1')
              ->withArgs(['test@example.com', '127.0.0.1', 'infuse/1.0'])
              ->andReturn(true)
              ->once();

        $auth = $this->getAuth()->setPasswordReset($reset);

        $this->assertTrue($auth->forgotStep1('test@example.com'));
    }

    public function testForgotStep2()
    {
        $reset = Mockery::mock('Infuse\Auth\Libs\ResetPassword');
        $reset->shouldReceive('step2')
              ->withArgs(['forgot_token_1234', ['testpassword2', 'testpassword2'], '127.0.0.1'])
              ->andReturn(true)
              ->once();

        $auth = $this->getAuth()->setPasswordReset($reset);

        $this->assertTrue($auth->forgotStep2('forgot_token_1234', ['testpassword2', 'testpassword2']));
    }

    function testGetUserInviter()
    {
        $auth = $this->getAuth();
        $this->assertInstanceOf(UserInvites::class, $auth->getUserInviter());
    }

    function testInvite()
    {
        $user = new User;
        $inviter = Mockery::mock(UserInvites::class);
        $inviter->shouldReceive('invite')
            ->withArgs(['test@example.com', [], []])
            ->andReturn($user)
            ->once();

        $auth = $this->getAuth()->setUserInviter($inviter);

        $this->assertEquals($user, $auth->invite('test@example.com'));
    }

    private function getAuth()
    {
        $auth = new AuthManager();
        $auth->setApp(Test::$app)
             ->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
             ->setResponse(new Response());

        return $auth;
    }
}
