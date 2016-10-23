<?php

namespace Infuse\Auth\Interfaces;

interface UserInterface
{
    /**
     * Get's the user's name.
     *
     * @param bool $full when true, get's the user's full name
     *
     * @return string
     */
    public function name($full = false);

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
    public function signIn();

    /**
     * Marks the user as signed out.
     *
     * @return self
     */
    public function signOut();

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
