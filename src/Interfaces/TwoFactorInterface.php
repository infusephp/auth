<?php

namespace Infuse\Auth\Interfaces;

interface TwoFactorInterface
{
    /**
     * Checks if the user needs 2FA verification.
     *
     * @param UserInterface $user
     *
     * @return bool
     */
    public function needsVerification(UserInterface $user);

    /**
     * Verifies a user's 2FA token.
     *
     * @param UserInterface $user
     * @param mixed         $token
     *
     * @throws \Infuse\Auth\Exception\AuthException when the token cannot be verified.
     *
     * @return bool
     */
    public function verify(UserInterface $user, $token);
}
