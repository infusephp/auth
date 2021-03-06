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
use Infuse\Auth\Models\AccountSecurityEvent;
use Infuse\Auth\Models\UserLink;
use Infuse\HasApp;
use Infuse\Utility as U;

class ResetPassword
{
    use HasApp;

    /**
     * @var AuthManager
     */
    private $auth;

    /**
     * @param AuthManager $auth
     */
    public function __construct(AuthManager $auth)
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
     * @return \Infuse\Auth\Interfaces\UserInterface
     */
    public function getUserFromToken($token)
    {
        $expiration = U::unixToDb(time() - UserLink::$forgotLinkTimeframe);
        $link = UserLink::where('link', $token)
            ->where('type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', $expiration, '>')
            ->first();

        if (!$link) {
            throw new AuthException('This link has expired or is invalid.');
        }

        $userClass = $this->auth->getUserClass();

        return $userClass::find($link->user_id);
    }

    /**
     * The first step in the forgot password sequence.
     *
     * @param string $email     email address
     * @param string $ip        ip address making the request
     * @param string $userAgent user agent used to make the request
     *
     * @throws AuthException when the step cannot be completed.
     *
     * @return bool
     */
    public function step1($email, $ip, $userAgent)
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Please enter a valid email address.');
        }

        $userClass = $this->auth->getUserClass();
        $user = $userClass::where('email', $email)->first();

        if (!$user || $user->isTemporary()) {
            throw new AuthException('We could not find a match for that email address.');
        }

        // can only issue a single active forgot token at a time
        $expiration = U::unixToDb(time() - UserLink::$forgotLinkTimeframe);
        $nExisting = UserLink::where('user_id', $user->id())
            ->where('type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', $expiration, '>')
            ->count();

        if ($nExisting > 0) {
            return true;
        }

        // generate a reset password link
        $link = $this->buildLink($user->id(), $ip, $userAgent);

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
        $user->password = $password;
        $success = $user->grantAllPermissions()->save();
        $user->enforcePermissions();

        if (!$success) {
            $msg = implode(' ', $user->getErrors()->all());
            throw new AuthException($msg);
        }

        $this->app['database']->getDefault()
            ->delete('UserLinks')
            ->where('user_id', $user->id())
            ->where('type', UserLink::FORGOT_PASSWORD)
            ->execute();

        return true;
    }

    /**
     * Builds a reset password link.
     *
     * @param int    $userId
     * @param string $ip
     * @param string $userAgent
     *
     * @return UserLink
     */
    public function buildLink($userId, $ip, $userAgent)
    {
        $link = new UserLink();
        $link->user_id = $userId;
        $link->type = UserLink::FORGOT_PASSWORD;

        try {
            $link->save();
        } catch (\Exception $e) {
            throw new \Exception("Could not create reset password link for user # $userId: ".$e->getMessage());
        }

        // record the reset password request event
        $event = new AccountSecurityEvent();
        $event->user_id = $userId;
        $event->type = AccountSecurityEvent::RESET_PASSWORD_REQUEST;
        $event->ip = $ip;
        $event->user_agent = $userAgent;
        $event->save();

        return $link;
    }
}
