<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs;

use Infuse\Auth\Exception\AuthException;
use Infuse\Auth\Models\UserLink;
use Infuse\HasApp;
use Infuse\Utility as U;

class ResetPassword
{
    use HasApp;

    /**
     * @var Auth
     */
    private $auth;

    /**
     * @param Auth $auth
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        $this->setApp($auth->getApp());
    }

    /**
     * Looks up a user from a given forgot token.
     *
     * @param string $token
     *
     * @throws AuthException when the token is invalid.
     *
     * @return User
     */
    public function getUserFromToken($token)
    {
        $expiration = U::unixToDb(time() - UserLink::$forgotLinkTimeframe);
        $link = UserLink::where('link', $token)
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', $expiration, '>')
            ->first();

        if (!$link) {
            throw new AuthException('This link has expired or is invalid.');
        }

        $userClass = $this->auth->getUserClass();

        return new $userClass($link->user_id);
    }

    /**
     * The first step in the forgot password sequence.
     *
     * @param string $email email address
     * @param string $ip    ip address making the request
     *
     * @throws AuthException when the step cannot be completed.
     *
     * @return bool
     */
    public function step1($email, $ip)
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Please enter a valid email address.');
        }

        $userClass = $this->auth->getUserClass();
        $user = $userClass::where('user_email', $email)->first();

        if (!$user || $user->isTemporary()) {
            throw new AuthException('We could not find a match for that email address.');
        }

        // can only issue a single active forgot token at a time
        $expiration = U::unixToDb(time() - UserLink::$forgotLinkTimeframe);
        $nExisting = UserLink::totalRecords([
            'user_id' => $user->id(),
            'link_type' => UserLink::FORGOT_PASSWORD,
            'created_at > "'.$expiration.'"',
        ]);

        if ($nExisting > 0) {
            return true;
        }

        // generate a reset password link
        $link = $this->buildLink($user->id());

        // and send it
        return $user->sendEmail('forgot-password', [
            'ip' => $ip,
            'forgot' => $link->link,
        ]);
    }

    /**
     * Step 2 in the forgot password process. Resets the password
     * given a valid token.
     *
     * @param string $token    token
     * @param array  $password new password
     * @param string $ip       ip address making the request
     *
     * @throws AuthException when the step cannot be completed.
     *
     * @return bool
     */
    public function step2($token, array $password, $ip)
    {
        $user = $this->getUserFromToken($token);

        // Update the password
        $user->user_password = $password;
        $success = $user->grantAllPermissions()->save();
        $user->enforcePermissions();

        if (!$success) {
            throw new AuthException('Please enter a valid password.');
        }

        $this->app['db']->delete('UserLinks')
            ->where('user_id', $user->id())
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->execute();

        $user->sendEmail('password-changed',
            ['ip' => $ip]);

        return true;
    }

    /**
     * Builds a reset password link.
     *
     * @param int $userId
     *
     * @return UserLink
     */
    public function buildLink($userId)
    {
        $link = new UserLink();
        $link->user_id = $userId;
        $link->link_type = UserLink::FORGOT_PASSWORD;

        try {
            $link->save();
        } catch (\Exception $e) {
            throw new \Exception("Could not create reset password link for user # $userId: ".$e->getMessage());
        }

        return $link;
    }
}
