<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

use Infuse\Auth\Libs\RateLimiter\RedisRateLimiter;
use Infuse\Test;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class RedisRateLimiterTest extends MockeryTestCase
{
    static function setUpBeforeClass()
    {
        Test::$app['redis']->del('authtest:failed_login_counter.test');
    }

    private function getLimiter()
    {
        $limiter = new RedisRateLimiter();
        $limiter->setApp(Test::$app);
        return $limiter;
    }

    function testGetLockoutWindow()
    {
        $this->assertEquals('30 minutes', $this->getLimiter()->getLockoutWindow('test'));
    }

    function testGetMaxAttempts()
    {
        $this->assertEquals(6, $this->getLimiter()->getMaxAttempts('test'));
    }

    function testRedisRateLimiting()
    {
        $limiter = $this->getLimiter();
        $this->assertEquals(6, $limiter->getRemainingAttempts('test'));
        $this->assertTrue($this->getLimiter()->canLogin('test'));

        $limiter->recordFailedLogin('test');
        $limiter->recordFailedLogin('test2');
        $this->assertEquals(5, $limiter->getRemainingAttempts('test'));
        $this->assertTrue($this->getLimiter()->canLogin('test'));

        for ($i = 0; $i < 10; $i++) {
            $limiter->recordFailedLogin('test');
        }

        $this->assertEquals(0, $limiter->getRemainingAttempts('test'));
        $this->assertFalse($this->getLimiter()->canLogin('test'));

        // verify ttl
        $ttl = Test::$app['redis']->ttl('authtest:failed_login_counter.test');
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(1800, $ttl);
    }
}