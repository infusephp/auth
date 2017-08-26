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
use Infuse\Auth\Libs\RateLimiter\RedisRateLimiter;
use Infuse\Auth\Libs\Strategy\TraditionalStrategy;
use Infuse\Auth\Models\UserLink;
use Infuse\Test;
use PHPUnit\Framework\TestCase;

class TraditionalStrategyTest extends TestCase
{
    public static $user;
    public static $ogUserId;
    public static $link;

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
            $app['user'] = new User(self::$ogUserId);
            $app['user']->markSignedIn();
        }
    }

    public function testGetUserWithCredentialsBadUsername()
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Please enter a valid username.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('', '');
    }

    public function testGetUserWithCredentialsBadPassword()
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Please enter a valid password.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('test@example.com', '');
    }

    public function testGetUserWithCredentialsMissingUser()
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address and password.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::TEMPORARY, ]));

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('doesnotexist@example.com', 'testpassword');
    }

    public function testGetUserWithCredentialsWrongPassword()
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address and password.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::TEMPORARY, ]));

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('test@example.com', 'wrong password');
    }

    public function testGetUserWithCredentialsFailTemporary()
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::TEMPORARY, ]));

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentialsFailDisabled()
    {
        Test::$app['database']->getDefault()
            ->delete('UserLinks')
            ->where('user_id', self::$user->id())
            ->execute();

        self::$user->enabled = false;
        $this->assertTrue(self::$user->save());

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Sorry, your account has been disabled.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentialsFailNotVerified()
    {
        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::VERIFY_EMAIL, ]));
        $link->created_at = '-10 years';
        $this->assertTrue($link->save());

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('You must verify your account with the email that was sent to you before you can log in.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('test@example.com', 'testpassword');
    }

    public function testGetUserWithCredentials()
    {
        Test::$app['database']->getDefault()
            ->delete('UserLinks')
            ->where('user_id', self::$user->id())
            ->execute();

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $strategy = $this->getStrategy();
        $user = $strategy->getUserWithCredentials('test@example.com', 'testpassword');

        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testLoginFail()
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address and password.');

        $strategy = $this->getStrategy();
        $strategy->login('test@example.com', 'bogus');
    }

    public function testLogin()
    {
        $strategy = $this->getStrategy();
        $this->assertTrue($strategy->login('test@example.com', 'testpassword'));
        $this->assertEquals(self::$user->id(), Test::$app['user']->id());
        $this->assertTrue(Test::$app['user']->isSignedIn());
        $this->assertEquals(self::$user->id(), Test::$app['user']->id());
    }

    function testLoginRateLimited()
    {
        Test::$app['config']->set('auth.loginRateLimiter', RedisRateLimiter::class);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('This account has been locked due to too many failed sign in attempts. The lock is only temporary. Please try again after 30 minutes.');

        $strategy = $this->getStrategy();

        for ($i = 0; $i < 10; $i++) {
            try {
                $strategy->login('test@example.com', 'not the password');
            } catch (AuthException $e) {
                // ignore exceptions
            }
        }

        // should throw a locked out exception
        $strategy->login('test@example.com', 'not the password');
    }

    public function testVerifyPassword()
    {
        $strategy = $this->getStrategy();
        $user = new User();

        $this->assertFalse($strategy->verifyPassword($user, ''));
        $this->assertFalse($strategy->verifyPassword($user, false));
        $this->assertFalse($strategy->verifyPassword($user, null));
        $this->assertFalse($strategy->verifyPassword($user, []));

        $user->password = $strategy->hash('thisismypassword');
        $this->assertTrue($strategy->verifyPassword($user, 'thisismypassword'));
        $this->assertFalse($strategy->verifyPassword($user, 'thisisnotmypassword'));
        $this->assertFalse($strategy->verifyPassword($user, ''));
    }

    private function getStrategy()
    {
        return new TraditionalStrategy(Test::$app['auth']);
    }
}
