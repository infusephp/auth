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
use Infuse\HasApp;
use Redis;

/**
 * Implementation of the failed login rate limiter
 * using Redis.
 */
class RedisRateLimiter implements LoginRateLimiterInterface
{
    use HasApp;

    const DEFAULT_ACCOUNT_LOCKOUT_WINDOW = '30 minutes';
    const DEFAULT_MAX_FAILED_ATTEMPTS = 6;

    /**
     * @var Redis|null
     */
    protected $redis;

    /**
     * @param Redis|null $redis
     */
    function __construct(Redis $redis = null)
    {
        $this->redis = $redis;
    }

    /**
     * Gets the redis instance.
     *
     * @return Redis
     */
    function getRedis()
    {
        if (!$this->redis) {
            $this->redis = $this->app['redis'];
        }

        return $this->redis;
    }

    function canLogin($username)
    {
        return $this->getRemainingAttempts($username) > 0;
    }

    function getLockoutWindow($username)
    {
        return $this->app['config']->get('auth.accountLockoutWindow', self::DEFAULT_ACCOUNT_LOCKOUT_WINDOW);
    }

    function getMaxAttempts($username)
    {
        return $this->app['config']->get('auth.maxFailedLogins', self::DEFAULT_MAX_FAILED_ATTEMPTS);
    }

    function getRemainingAttempts($username)
    {
        $maxAttempts = $this->getMaxAttempts($username);

        $redis = $this->getRedis();
        $k = $this->getCounterKey($username);

        $failedAttempts = (int) $redis->get($k);

        return max(0, $maxAttempts - $failedAttempts);
    }

    function recordFailedLogin($username)
    {
        $redis = $this->getRedis();
        $k = $this->getCounterKey($username);

        // increment a failed login counter in redis and update the expiration date
        $redis->incr($k, 1);

        $window = $this->getLockoutWindow($username);
        $expiresIn = strtotime("+$window") - time();
        $redis->expire($k, $expiresIn);
    }

    /**
     * Gets the key of the failed login counter in Redis
     * for a given username.
     *
     * @param string $username
     *
     * @return string
     */
    function getCounterKey($username)
    {
        $k = $this->app['config']->get('cache.namespace');
        $k .= ':failed_login_counter.';
        $k .= $username;

        return $k;
    }
}