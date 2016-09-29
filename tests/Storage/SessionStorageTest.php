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
use Infuse\Auth\Libs\Storage\SessionStorage;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class SessionStorageTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $ogUserId;
    public static $rememberCookie;

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
            $app['user'] = new User(self::$ogUserId);
            $app['user']->signIn();
        }
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

    public function testGetAuthenticatedUserSession()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $req->setSession('user_id', self::$user->id());
        $req->setSession('user_agent', 'Firefox');
        $res = new Response();

        $user = $storage->getAuthenticatedUser($req, $res);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());
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

        $req = new Request();
        $res = new Response();

        $this->assertTrue($storage->signIn(self::$user, $req, $res));
        $this->assertEquals(self::$user->id(), $req->session('user_id'));

        // repeat calls should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $this->assertTrue($storage->signIn(self::$user, $req, $res));
        }
    }

    public function testRemember()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $this->assertTrue($storage->remember(self::$user, $req, $res));

        $this->assertTrue(is_array($res->cookies('persistent')));
        self::$rememberCookie = $res->cookies('persistent')[0];
    }

    /**
     * @depends testRemember
     */
    public function testGetAuthenticatedUserRememberMe()
    {
        $storage = $this->getStorage();

        $req = Request::create('/', 'GET', [], ['persistent' => self::$rememberCookie], [], ['HTTP_USER_AGENT' => 'Firefox']);
        $res = new Response();

        $user = $storage->getAuthenticatedUser($req, $res);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isSignedIn());
    }

    public function testSignOut()
    {
        $storage = $this->getStorage();

        $req = new Request();
        $res = new Response();

        $this->assertTrue($storage->signOut($req, $res));
    }

    private function getStorage()
    {
        return new SessionStorage(Test::$app['auth']);
    }
}
