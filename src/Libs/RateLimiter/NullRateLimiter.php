<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs\RateLimiter;

use Infuse\Auth\Interfaces\LoginRateLimiterInterface;

/**
 * Default failed login rate limiter. Does not actually
 * perform any rate limiting checks.
 */
class NullRateLimiter implements LoginRateLimiterInterface
{
    function canLogin($username)
    {
        return true;
    }

    function getLockoutWindow($username)
    {
        return '';
    }

    function getMaxAttempts($username)
    {
        return -1;
    }

    function getRemainingAttempts($username)
    {
        return 1;
    }

    function recordFailedLogin($username)
    {
        // do nothing
    }
}