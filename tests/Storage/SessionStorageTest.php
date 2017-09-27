<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs\Storage;

use App\Users\Models\User;
use Infuse\Auth\Libs\Auth;
use Infuse\Auth\Models\ActiveSession;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;
use Mockery;
use PHPUnit\Framework\TestCase;

function session_status()
{
    return call_user_func_array([SessionStorageTest::$mock, 'session_status'], func_get_args());
}

function session_id()
{
    return call_user_func_array([SessionStorageTest::$mock, 'session_id'], func_get_args());
}

function session_regenerate_id()
{
    return call_user_func_array([SessionStorageTest::$mock, 'session_regenerate_id'], func_get_args());
}

function session_write_close()
{
    return call_user_func_array([SessionStorageTest::$mock, 'session_write_close'], func_get_args());
}

function session_start()
{
    return call_user_func_array([SessionStorageTest::$mock, 'session_start'], func_get_args());
}

function session_destroy()
{
    return call_user_func_array([SessionStorageTest::$mock, 'session_destroy'], func_get_args());
}

function session_name()
{
    return 'mysession';
}

class SessionStorageTest extends TestCase
{
    public static $mock;
    public static $user;
    public static $rememberCookie;

    public static function setUpBeforeClass()
    {
        Test::$app['database']->getDefault()
            ->delete('Users')
            ->where('email', 'test@example.com')
            ->execute();

        self::$user = User::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);
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

    protected function setUp()
    {
        self::$mock = Mockery::mock();
    }

    protected function tearDown()
    {
        self::$mock = false;
    }

    public function assertPreConditions()
    {
        $this->assertInstanceOf('App\Users\Models\User', self::$user);
    }

    public function testGetAuthenticatedUserSessionInvalidUserAgent()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Chrome']);
        $req->setSession('user_id', self::$user->id());
        $req->setSession('user_agent', 'Firefox');
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    public function testGetAuthenticatedUserSessionDoesNotExist()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', 12341234);
        $req->setSession('user_agent', 'Firefox');
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    public function testGetAuthenticatedUserSessionGuest()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', -1);
        $req->setSession('user_agent', 'Firefox');
        $res = new Response();

        $user = $storage->getAuthenticatedUser($req, $res);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(-1, $user->id());
        $this->assertFalse($user->isSignedIn());
    }

    public function testGetAuthenticatedUserGuest()
    {
        $storage = $this->getStorage();

        $req = new Request();
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    public function testSignIn()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', 12341234); // simulate some other user
        $res = new Response();

        $req->setSession(['test' => 1]);

        self::$mock->shouldReceive('session_status')
                   ->andReturn(PHP_SESSION_ACTIVE);

        self::$mock->shouldReceive('session_id')
                   ->withArgs([])
                   ->andReturn('sesh_1234')
                   ->twice();

        self::$mock->shouldReceive('session_id')
                   ->withArgs(['sesh_1234'])
                   ->andReturn('sesh_1234')
                   ->once();

        self::$mock->shouldReceive('session_regenerate_id')
                   ->withArgs([true])
                   ->once();

        self::$mock->shouldReceive('session_write_close')
                   ->once();

        self::$mock->shouldReceive('session_start')
                   ->once();

        $expectedExpires = time() + ini_get('session.cookie_lifetime');

        $this->assertTrue($storage->signIn(self::$user, $req, $res));

        // should sign a user into the session
        $this->assertEquals(self::$user->id(), $req->session('user_id'));
        $this->assertNull($req->session('test'));

        // should record an active session
        $session = ActiveSession::where('id', 'sesh_1234')->first();
        $this->assertInstanceOf('Infuse\Auth\Models\ActiveSession', $session);

        $expected = [
            'id' => 'sesh_1234',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'expires' => $expectedExpires,
        ];
        $arr = $session->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);
        $this->assertEquals(self::$user->id(), $session->user_id);
        $this->assertTrue($session->valid);

        // repeat calls should do nothing
        $req->setSession(['test' => 2]);
        for ($i = 0; $i < 5; ++$i) {
            $this->assertTrue($storage->signIn(self::$user, $req, $res));
        }

        $this->assertEquals(2, $req->session('test'));
    }

    function testSignInTwoFactorRemembered()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        self::$mock->shouldReceive('session_status')
            ->andReturn(PHP_SESSION_ACTIVE);
        self::$mock->shouldReceive('session_id')
            ->andReturn('sesh_12345');
        self::$mock->shouldReceive('session_regenerate_id');
        self::$mock->shouldReceive('session_write_close');
        self::$mock->shouldReceive('session_start');

        $user = new User(self::$user->id());
        $user->markTwoFactorVerified();

        $this->assertTrue($storage->signIn($user, $req, $res));

        // should sign a user into the session
        $this->assertEquals(self::$user->id(), $req->session('user_id'));

        // should remember 2fa verified status
        $this->assertTrue($req->session('2fa_verified'));
    }

    public function testTwoFactorVerified()
    {
        $storage = $this->getStorage();
        $req = new Request();
        $res = new Response();

        $this->assertTrue($storage->twoFactorVerified(self::$user, $req, $res));

        $this->assertTrue($req->session('2fa_verified'));
    }

    /**
     * @depends testSignIn
     */
    public function testGetAuthenticatedUserSession()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', self::$user->id());
        $req->setSession('user_agent', 'Firefox');
        $res = new Response();

        self::$mock->shouldReceive('session_status')
                   ->andReturn(PHP_SESSION_ACTIVE);

        self::$mock->shouldReceive('session_id')
                   ->andReturn('sesh_1234');

        // add a delay here so we can check if the updated_at timestamp
        // on the session has changed
        sleep(1);
        $expectedExpires = time() + ini_get('session.cookie_lifetime');

        $user = $storage->getAuthenticatedUser($req, $res);

        // should return a signed in user
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());

        // should record an active session
        $session = ActiveSession::where('id', 'sesh_1234')->first();
        $this->assertInstanceOf('Infuse\Auth\Models\ActiveSession', $session);

        $expected = [
            'id' => 'sesh_1234',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'expires' => $expectedExpires,
        ];
        $arr = $session->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);
        $this->assertNotEquals($session->created_at, $session->updated_at);
        $this->assertEquals(self::$user->id(), $session->user_id);
        $this->assertTrue($session->valid);
    }

    /**
     * @depends testGetAuthenticatedUserSession
     */
    public function testGetAuthenticatedUserSessionInvalidated()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', self::$user->id());
        $req->setSession('user_agent', 'Firefox');
        $res = new Response();

        self::$mock->shouldReceive('session_status')
                   ->andReturn(PHP_SESSION_ACTIVE);

        self::$mock->shouldReceive('session_id')
                   ->andReturn('sesh_1234');

        $session = new ActiveSession('sesh_1234');
        $session->valid = false;
        $this->assertTrue($session->save());

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    /**
     * @depends testSignIn
     */
    public function testGetAuthenticatedUserWith2FA()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', self::$user->id());
        $req->setSession('user_agent', 'Firefox');
        $req->setSession('2fa_verified', true);
        $res = new Response();

        self::$mock->shouldReceive('session_status')
                   ->andReturn(PHP_SESSION_ACTIVE);

        self::$mock->shouldReceive('session_id')
                   ->andReturn('sesh_12345');

        $user = $storage->getAuthenticatedUser($req, $res);

        // should return a signed in user with verified 2FA
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());
        $this->assertTrue($user->isTwoFactorVerified());
    }

    public function testRemember()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $this->assertTrue($storage->remember(self::$user, $req, $res));

        $this->assertTrue(is_array($res->cookies('mysession-remember')));
        self::$rememberCookie = $res->cookies('mysession-remember')[0];

        $this->assertTrue($req->session('remembered'));
    }

    /**
     * @depends testRemember
     */
    public function testGetAuthenticatedUserRememberMe()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], ['mysession-remember' => self::$rememberCookie], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $user = $storage->getAuthenticatedUser($req, $res);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());
        $this->assertFalse($user->isTwoFactorVerified());
        $this->assertTrue($req->session('remembered'));
        $this->assertNull($req->session('2fa_verified'));
    }

    public function testRemember2FAVerified()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        self::$user->markTwoFactorVerified();

        $this->assertTrue($storage->remember(self::$user, $req, $res));

        $this->assertTrue(is_array($res->cookies('mysession-remember')));
        self::$rememberCookie = $res->cookies('mysession-remember')[0];
    }

    /**
     * @depends testRemember2FAVerified
     */
    public function testGetAuthenticatedUserRememberMe2FA()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], ['mysession-remember' => self::$rememberCookie], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $user = $storage->getAuthenticatedUser($req, $res);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());
        $this->assertTrue($user->isTwoFactorVerified());

        // NOTE: We are using the in-memory storage in this test, however,
        // if this were using the session storage it would also mark
        // the session as two factor verified whenever the user is
        // signed in from the remember me cookie.
    }

    public function testSignOut()
    {
        $storage = $this->getStorage();

        $req = new Request();
        $res = new Response();

        self::$mock->shouldReceive('session_status')
                   ->andReturn(PHP_SESSION_ACTIVE);

        self::$mock->shouldReceive('session_id')
                   ->andReturn('sesh_1234');

        self::$mock->shouldReceive('session_destroy')
                   ->once();

        $this->assertTrue($storage->signOut($req, $res));

        $this->assertEquals(0, ActiveSession::where('id', 'sesh_1234')->count());
    }

    private function getStorage()
    {
        $req = new Request();
        $res = new Response();
        Test::$app['auth']->setRequest($req)->setResponse($res);

        return new SessionStorage(Test::$app['auth']);
    }
}
