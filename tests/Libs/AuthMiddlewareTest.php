<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Auth\AuthMiddleware;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class AuthMiddlewareTest extends MockeryTestCase
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
        $middleware = new AuthMiddleware();
        $middleware->setApp(Test::$app);

        $req = new Request();
        $res = new Response();
        $next = function ($req, $res) {
            return 'yay';
        };

        $this->assertEquals('yay', $middleware($req, $res, $next));

        $this->assertEquals($req, Test::$app['auth']->getRequest());
        $this->assertEquals($res, Test::$app['auth']->getResponse());

        $this->assertInstanceOf('App\Users\Models\User', Test::$app['user']);
        $this->assertEquals(-1, Test::$app['user']->id());

        $this->assertInstanceOf('App\Users\Models\User', Test::$app['requester']);
        $this->assertEquals(-1, Test::$app['requester']->id());
    }
}
