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
use Infuse\Auth\Libs\RememberMeCookie;
use Infuse\Test;

class RememberMeCookieTest extends PHPUnit_Framework_TestCase
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
            $app['user']->markSignedIn();
        }
    }

    public function testEncode()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '1234', '_token');

        $expected = 'eyJ1c2VyX2VtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsImFnZW50IjoiRmlyZWZveCIsInNlcmllcyI6IjEyMzQiLCJ0b2tlbiI6Il90b2tlbiJ9';
        $this->assertEquals($expected, $cookie->encode());
    }

    public function testDecode()
    {
        $encoded = 'eyJ1c2VyX2VtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsInNlcmllcyI6IjEyMzQiLCJ0b2tlbiI6Il90b2tlbiIsImFnZW50IjoiRmlyZWZveCJ9';
        $cookie = RememberMeCookie::decode($encoded);

        $this->assertInstanceOf('Infuse\Auth\Libs\RememberMeCookie', $cookie);

        $this->assertEquals('test@example.com', $cookie->getEmail());
        $this->assertEquals('Firefox', $cookie->getUserAgent());
        $this->assertEquals('1234', $cookie->getSeries());
        $this->assertEquals('_token', $cookie->getToken());
        $this->assertTrue($cookie->isValid());
    }

    public function testDecodeFail()
    {
        $cookie = RememberMeCookie::decode(null);
        $this->assertInstanceOf('Infuse\Auth\Libs\RememberMeCookie', $cookie);

        $this->assertEquals('', $cookie->getEmail());
        $this->assertEquals('', $cookie->getUserAgent());
        $this->assertEquals('', $cookie->getToken());
        $this->assertEquals('', $cookie->getSeries());
        $this->assertFalse($cookie->isValid());

        $encoded = 'WHAT IS THIS NONSENSE';
        $cookie = RememberMeCookie::decode($encoded);

        $this->assertInstanceOf('Infuse\Auth\Libs\RememberMeCookie', $cookie);

        $this->assertEquals('', $cookie->getEmail());
        $this->assertEquals('', $cookie->getUserAgent());
        $this->assertEquals('', $cookie->getToken());
        $this->assertEquals('', $cookie->getSeries());
        $this->assertFalse($cookie->isValid());
    }

    public function testGenerateTokens()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $this->assertEquals(32, strlen($cookie->getSeries()));
        $this->assertEquals(32, strlen($cookie->getToken()));
        $this->assertNotEquals($cookie->getSeries(), $cookie->getToken());

        $cookie2 = new RememberMeCookie('test@example.com', 'Firefox');
        $this->assertNotEquals($cookie->getSeries(), $cookie2->getSeries())
        ;
        $this->assertNotEquals($cookie->getToken(), $cookie2->getToken());
    }

    public function testGetExpires()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $this->assertEquals(7776000, $cookie->getExpires());
    }

    public function testIsValid()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '1234', '_token');
        $this->assertTrue($cookie->isValid());

        $cookie = new RememberMeCookie('asdfasdf', 'Firefox', '1234', '_token');
        $this->assertFalse($cookie->isValid());

        $cookie = new RememberMeCookie('test@example.com', '', '1234', '_token');
        $this->assertFalse($cookie->isValid());

        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '', '_token');
        $this->assertFalse($cookie->isValid());

        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '1234', '');
        $this->assertFalse($cookie->isValid());
    }

    public function testPersist()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $session = $cookie->persist(self::$user->id());
        $this->assertInstanceOf('Infuse\Auth\Models\PersistentSession', $session);
        $this->assertTrue($session->exists());
    }

    public function testPersistFail()
    {
        $this->setExpectedException('Exception');
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $cookie->persist(123412341234);
    }

    public function testVerifyNotValid()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '1234', '');

        $req = Mockery::mock('Infuse\Request');

        $this->assertFalse($cookie->verify($req, Test::$app['auth']));
    }

    public function testVerifyUserAgentFail()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '1234', '_token');

        $req = Mockery::mock('Infuse\Request');
        $req->shouldReceive('agent')
            ->andReturn('Chrome');

        $this->assertFalse($cookie->verify($req, Test::$app['auth']));
    }

    public function testVerifyUserNotFound()
    {
        $cookie = new RememberMeCookie('test2@example.com', 'Firefox', '1234', '_token');

        $req = Mockery::mock('Infuse\Request');
        $req->shouldReceive('agent')
            ->andReturn('Firefox');

        $this->assertFalse($cookie->verify($req, Test::$app['auth']));
    }

    public function testVerifySeriesNotFound()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $cookie->persist(self::$user->id());

        $cookie = new RememberMeCookie('test@example.com', 'Firefox', '12345', $cookie->getToken());

        $req = Mockery::mock('Infuse\Request');
        $req->shouldReceive('agent')
            ->andReturn('Firefox');

        $this->assertFalse($cookie->verify($req, Test::$app['auth']));
    }

    public function testVerifyTokenMismatch()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $cookie->persist(self::$user->id());

        $cookie = new RememberMeCookie('test@example.com', 'Firefox', $cookie->getSeries(), '_token2');

        $req = Mockery::mock('Infuse\Request');
        $req->shouldReceive('agent')
            ->andReturn('Firefox');

        $this->assertFalse($cookie->verify($req, Test::$app['auth']));
    }

    public function testVerify()
    {
        $cookie = new RememberMeCookie('test@example.com', 'Firefox');
        $cookie->persist(self::$user->id());

        $cookie = new RememberMeCookie('test@example.com', 'Firefox', $cookie->getSeries(), $cookie->getToken());

        $req = Mockery::mock('Infuse\Request');
        $req->shouldReceive('agent')
            ->andReturn('Firefox');

        $user = $cookie->verify($req, Test::$app['auth']);

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }
}
