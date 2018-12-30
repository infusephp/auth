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
use Mockery\Adapter\Phpunit\MockeryTestCase;

class SymfonySessionStorageTest extends MockeryTestCase
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

        self::$user = Test::$app['auth']->getUserRegistration()->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);
    }

    public static function tearDownAfterClass()
    {
        if (self::$user) {
            self::$user->grantAllPermissions()->delete();
        }
    }

    protected function setUp()
    {
        self::$mock = Mockery::mock();
        self::$mock->shouldReceive('getName')
            ->andReturn('mysession');
        Test::$app['symfony_session'] = self::$mock;
    }

    protected function tearDown()
    {
        self::$mock = false;
    }

    public function testGetAuthenticatedUserSessionInvalidUserAgent()
    {
        $storage = $this->getStorage();

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());
        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Chrome']);
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    public function testGetAuthenticatedUserSessionDoesNotExist()
    {
        $storage = $this->getStorage();

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(12341234);
        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    public function testGetAuthenticatedUserSessionGuest()
    {
        $storage = $this->getStorage();

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(-1);
        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $user = $storage->getAuthenticatedUser($req, $res);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(-1, $user->id());
        $this->assertFalse($user->isSignedIn());
    }

    public function testGetAuthenticatedUserGuest()
    {
        $storage = $this->getStorage();

        self::$mock->shouldReceive('get')
            ->andReturn(null);

        $req = new Request();
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }

    public function testSignIn()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        self::$mock->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mock->shouldReceive('getId')
            ->withArgs([])
            ->andReturn('sesh_1234')
            ->twice();

        self::$mock->shouldReceive('setId')
            ->withArgs(['sesh_1234'])
            ->once();

        self::$mock->shouldReceive('migrate')
            ->withArgs([true])
            ->once();

        self::$mock->shouldReceive('save')
            ->once();

        self::$mock->shouldReceive('start')
            ->once();

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(12341234);

        self::$mock->shouldReceive('replace');

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
    }

    public function testSignInAlreadySignedIn()
    {
        $storage = $this->getStorage();

        $user = new User(12341234234);
        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(12341234234);

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        // repeat calls should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $this->assertTrue($storage->signIn($user, $req, $res));
        }
    }

    function testSignInTwoFactorRemembered()
    {
        $storage = $this->getStorage();

        self::$mock->shouldReceive('isStarted')
            ->andReturn(true);
        self::$mock->shouldReceive('getId')
            ->andReturn('sesh_12345');
        self::$mock->shouldReceive('migrate')
            ->withArgs([true]);
        self::$mock->shouldReceive('setId')
            ->withArgs(['sesh_12345']);
        self::$mock->shouldReceive('save');
        self::$mock->shouldReceive('start');
        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(null);
        self::$mock->shouldReceive('replace');
        self::$mock->shouldReceive('set');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

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

        self::$mock->shouldReceive('set');

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

        self::$mock->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());

        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        self::$mock->shouldReceive('get')
            ->withArgs(['2fa_verified'])
            ->andReturn(null);

        self::$mock->shouldReceive('getId')
            ->andReturn('sesh_1234');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

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

        self::$mock->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());

        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        self::$mock->shouldReceive('getId')
            ->andReturn('sesh_1234');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

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

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());

        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        self::$mock->shouldReceive('get')
            ->withArgs(['2fa_verified'])
            ->andReturn(true);

        self::$mock->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mock->shouldReceive('getId')
            ->andReturn('sesh_12345');

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

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

        self::$mock->shouldReceive('set');

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

        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn(null);

        self::$mock->shouldReceive('set');

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

        self::$mock->shouldReceive('set');

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

        self::$mock->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn(null);

        self::$mock->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(null);

        self::$mock->shouldReceive('set');

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

        self::$mock->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mock->shouldReceive('getId')
            ->andReturn('sesh_1234');

        self::$mock->shouldReceive('invalidate')
            ->once();

        $this->assertTrue($storage->signOut($req, $res));

        $this->assertEquals(0, ActiveSession::where('id', 'sesh_1234')->count());
    }

    private function getStorage()
    {
        $req = new Request();
        $res = new Response();
        Test::$app['auth']->setRequest($req)->setResponse($res);

        return new SymfonySessionStorage(Test::$app['auth']);
    }
}
