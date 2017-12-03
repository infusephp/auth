<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

use Infuse\Auth\Libs\RateLimiter\NullRateLimiter;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class NullRateLimiterTest extends MockeryTestCase
{
    private function getLimiter()
    {
        return new NullRateLimiter();
    }

    function testCanLogin()
    {
        $this->assertTrue($this->getLimiter()->canLogin('test'));
    }

    function testGetLockoutWindow()
    {
        $this->assertEquals('', $this->getLimiter()->getLockoutWindow('test'));
    }

    function testGetMaxAttempts()
    {
        $this->assertEquals(-1, $this->getLimiter()->getMaxAttempts('test'));
    }

    function testGetRemainingAttempts()
    {
        $this->assertEquals(1, $this->getLimiter()->getRemainingAttempts('test'));
    }

    function testRecordFailedAttempt()
    {
        $limiter = $this->getLimiter();
        $limiter->recordFailedLogin('test');
        $this->assertEquals(1, $limiter->getRemainingAttempts('test'));
    }
}