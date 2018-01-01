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
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Infuse\Auth\Libs\UserRegistration;

class UserRegistrationTest extends MockeryTestCase
{
    public static $user;
    public static $user2;
    public static $user3;
    public static $user4;

    public static function setUpBeforeClass()
    {
        $db = Test::$app['database']->getDefault();
        $db->delete('Users')
            ->where('email', ['test@example.com', 'test2@example.com', 'test3@example.com', 'test4@example.com'])
            ->execute();
    }

    public static function tearDownAfterClass()
    {
        foreach ([self::$user, self::$user2, self::$user3, self::$user4] as $u) {
            if ($u) {
                $u->grantAllPermissions();
                $u->delete();
            }
        }
    }

    public function testCreateFail()
    {
        $this->expectException(AuthException::class);
        $registrar = $this->getUserRegistration();
        $registrar->registerUser([]);
    }

    public function testRegisterUser()
    {
        $registrar = $this->getUserRegistration();

        self::$user = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);

        $this->assertInstanceOf(User::class, self::$user);
        $this->assertGreaterThan(0, self::$user->id());
        $this->assertFalse(self::$user->isVerified(false));
    }

    function testRegisterUserVerifiedEmail()
    {
        $registrar = $this->getUserRegistration();

        self::$user2 = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test2@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ], true);

        $this->assertInstanceOf(User::class, self::$user2);
        $this->assertGreaterThan(0, self::$user2->id());
        $this->assertTrue(self::$user2->isVerified(false));
    }

    function testCreateTemporaryFailNoEmail()
    {
        $this->expectException(AuthException::class);
        $registrar = $this->getUserRegistration();
        $registrar->createTemporaryUser([]);
    }

    function testGetTemporaryUser()
    {
        $registrar = $this->getUserRegistration();
        $this->assertFalse($registrar->getTemporaryUser('test@example.com'));
    }

    public function testCreateTemporaryUser()
    {
        $registrar = $this->getUserRegistration();

        self::$user3 = $registrar->createTemporaryUser([
            'email' => 'test3@example.com',
            'password' => '',
            'first_name' => '',
            'last_name' => '',
            'ip' => '',
            'enabled' => true,
        ]);

        $this->assertInstanceOf(User::class, self::$user3);
        $this->assertTrue(self::$user3->isTemporary());

        $user = $registrar->getTemporaryUser('test3@example.com');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user3->id(), $user->id());
    }

    /**
     * @depends testRegisterUser
     */
    public function testUpgradeTemporaryUserFail()
    {
        $this->expectException(AuthException::class);

        $registrar = $this->getUserRegistration();

        $registrar->upgradeTemporaryUser(self::$user, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);
    }

    /**
     * @depends testCreateTemporaryUser
     */
    public function testUpgradeTemporaryUser()
    {
        $registrar = $this->getUserRegistration();

        $this->assertEquals($registrar, $registrar->upgradeTemporaryUser(self::$user3, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]));
    }

    /**
     * @depends testCreateTemporaryUser
     */
    function testRegisterUserUpgradeTemporary()
    {
        $registrar = $this->getUserRegistration();

        self::$user4 = $registrar->createTemporaryUser([
            'email' => 'test4@example.com',
            'password' => '',
            'first_name' => '',
            'last_name' => '',
            'ip' => '',
            'enabled' => true,
        ]);

        $upgradedUser = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test4@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);

        $this->assertInstanceOf(User::class, $upgradedUser);
        $this->assertEquals(self::$user4->id(), $upgradedUser->id());
        $this->assertFalse($upgradedUser->isTemporary());
        $this->assertFalse($registrar->getTemporaryUser('test4@example.com'));
    }

    private function getUserRegistration()
    {
        $auth = new AuthManager();
        $auth->setApp(Test::$app)
            ->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
            ->setResponse(new Response());

        return new UserRegistration($auth);
    }
}