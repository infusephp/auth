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

interface UserInterface
{
    /**
     * Gets the user's ID.
     *
     * @return int
     */
    public function id();

    /**
     * Gets the user's name.
     *
     * @param bool $full when true, get's the user's full name
     *
     * @return string
     */
    public function name($full = false);

    /**
     * Gets the user's email address.
     *
     * @return string $email
     */
    public function email();

    /**
     * Gets the hashed password for the user.
     *
     * @return string|null
     */
    public function getHashedPassword();

    /**
     * Checks if the user has an incomplete registration.
     *
     * @return bool
     */
    public function isTemporary();

    /**
     * Checks if the account has been verified.
     *
     * @param bool $withinTimeWindow when true, allows a time window before the account is considered unverified
     *
     * @return bool
     */
    public function isVerified($withinTimeWindow = true);

    /**
     * Checks if the user account is enabled and allowed to sign in.
     *
     * @return bool
     */
    public function isEnabled();

    /**
     * Enables the user account.
     *
     * @return bool
     */
    public function enable();

    /**
     * Checks if the user is signed in.
     *
     * @return bool
     */
    public function isSignedIn();

    /**
     * Marks the user as signed in.
     *
     * @return self
     */
    public function markSignedIn();

    /**
     * Marks the user as signed out.
     *
     * @return self
     */
    public function markSignedOut();

    /**
     * Checks if the user has been verified using
     * two-factor authentication.
     *
     * @return bool
     */
    public function isTwoFactorVerified();

    /**
     * Marks the user as verified using two-factor authentication.
     *
     * @return self
     */
    public function markTwoFactorVerified();

    /**
     * Sends the user an email.
     *
     * @param string $template template name
     * @param array  $params   template parameters
     *
     * @return bool success
     */
    public function sendEmail($template, array $params = []);
}
