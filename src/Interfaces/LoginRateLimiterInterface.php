<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Interfaces;

/**
 * Provides rate limiting for failed login attempts.
 */
interface LoginRateLimiterInterface
{
    /**
     * Checks whether a user can login according to this rate limiter.
     *
     * @param string $username
     *
     * @return bool
     */
    function canLogin($username);

    /**
     * Gets the account lockout window after too many
     * failed attempts for a user.
     *
     * @param string $username
     *
     * @return string format compatible with strtotime(), i.e. 30 minutes
     */
    function getLockoutWindow($username);

    /**
     * Gets the maximum number of failed login attempts for a user.
     *
     * @param string $username
     *
     * @return int
     */
    function getMaxAttempts($username);

    /**
     * Gets the number of remaining login attempts for a user.
     *
     * @param string $username
     *
     * @return int
     */
    function getRemainingAttempts($username);

    /**
     * Records a failed login attempt.
     *
     * @param string $username
     */
    function recordFailedLogin($username);
}