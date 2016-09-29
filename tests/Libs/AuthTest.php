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
use Infuse\Auth\Models\AccountSecurityEvent;
use Infuse\Auth\Models\UserLink;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class AuthTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $ogUserId;

    public static function setUpBeforeClass()
    {
        Test::$app['db']->delete('Users')
            ->where('email', 'test@example.com')
            ->execute();

        self::$user = User::registerUser([
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
        foreach ([self::$user] as $u) {
            if ($u) {
                $u->grantAllPermissions()->delete();
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
            $app['user'] = new User(self::$ogUserId);
            $app['user']->signIn();
        }
    }

    public function testDI()
    {
        $this->assertInstanceOf('Infuse\Auth\Libs\Auth', Test::$app['auth']);
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
        $this->setExpectedException('InvalidArgumentException');

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

        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
        $this->assertEquals($auth, $auth->setStorage($storage));
        $this->assertEquals($storage, $auth->getStorage());
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
        Test::$app['config']->set('users.model', null);
        $this->assertEquals('App\Users\Models\User', $auth->getUserClass());
    }

    public function testAuthenticate()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid username.');

        $auth = $this->getAuth();

        $this->assertTrue($auth->authenticate('traditional'));
    }

    public function testGetAuthenticatedUser()
    {
        $auth = $this->getAuth();

        $user = new User(1234);

        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
        $storage->shouldReceive('getAuthenticatedUser')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn($user)
                ->once();
        $auth->setStorage($storage);

        $this->assertEquals($user, $auth->getAuthenticatedUser());
    }

    public function testGetAuthenticatedUserFail()
    {
        $auth = $this->getAuth();

        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
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

    public function testSignInUser()
    {
        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
        $storage->shouldReceive('signIn')
                ->andReturn(true)
                ->once();
        $auth->setStorage($storage);

        $user = $auth->signInUser(self::$user);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());

        $this->assertEquals($user, Test::$app['user']);

        $this->assertEquals(1, AccountSecurityEvent::totalRecords([
            'user_id' => self::$user->id(),
            'type' => 'user.login',
            'auth_strategy' => 'web', ]));
    }

    public function testSignInUserRemember()
    {
        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
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

        $this->assertEquals(1, AccountSecurityEvent::totalRecords([
            'user_id' => self::$user->id(),
            'type' => 'user.login',
            'auth_strategy' => 'test_strat', ]));
    }

    public function testSignInUserGuest()
    {
        $auth = $this->getAuth();
        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
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

        $this->assertEquals(0, AccountSecurityEvent::totalRecords(['user_id' => -1]));
    }

    public function testLogin()
    {
        $auth = $this->getAuth();
        $this->assertTrue($auth->login('test@example.com', 'testpassword'));
    }

    public function testLogout()
    {
        $auth = $this->getAuth();

        $storage = Mockery::mock('Infuse\Auth\Libs\Storage\StorageInterface');
        $storage->shouldReceive('signOut')
                ->withArgs([$auth->getRequest(), $auth->getResponse()])
                ->andReturn(true)
                ->once();
        $storage->shouldReceive('signIn')
                ->once();
        $auth->setStorage($storage);

        $this->assertTrue($auth->logout());

        $this->assertInstanceOf($auth->getUserClass(), Test::$app['user']);
        $this->assertEquals(-1, Test::$app['user']->id());
        $this->assertFalse(Test::$app['user']->isSignedIn());
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
            'type' => UserLink::VERIFY_EMAIL,
            'created_at' => '-10 years', ]));
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

    private function getAuth()
    {
        $auth = new Auth();
        $auth->setApp(Test::$app)
             ->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
             ->setResponse(new Response());

        return $auth;
    }
}
