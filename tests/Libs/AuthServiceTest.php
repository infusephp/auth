<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Auth\Interfaces\TwoFactorInterface;
use Infuse\Auth\Interfaces\UserInterface;
use Infuse\Auth\Services\Auth;
use Infuse\Test;

class AuthServiceTest extends PHPUnit_Framework_TestCase
{
    public static $ogUser;

    public static function setUpBeforeClass()
    {
        self::$ogUser = Test::$app['user'];
    }

    public static function tearDownAfterClass()
    {
        Test::$app['user'] = self::$ogUser;
    }

    public function testService()
    {
        Test::$app['config']->set('auth.strategies', [
            'test' => 'TestStrategy',
        ]);

        Test::$app['config']->set('auth.storage', 'Infuse\Auth\Libs\Storage\InMemoryStorage');

        Test::$app['config']->set('auth.2fa_strategy', 'TestTwoFactor');

        $service = new Auth(Test::$app);
        $auth = $service(Test::$app);

        $this->assertInstanceOf('Infuse\Auth\Libs\AuthManager', $auth);
        $this->assertInstanceOf('TestTwoFactor', $auth->getTwoFactorStrategy());
    }
}

class TestTwoFactor implements TwoFactorInterface
{
    public function needsVerification(UserInterface $user)
    {
    }

    public function verify(UserInterface $user, $token)
    {
    }
}
