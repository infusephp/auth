<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs\Strategy;

use Infuse\Auth\Exception\AuthException;
use Infuse\Auth\Interfaces\UserInterface;
use Infuse\Auth\Libs\RateLimiter\NullRateLimiter;
use Infuse\Request;
use Infuse\Response;

class TraditionalStrategy extends AbstractStrategy
{
    /**
     * @var \Infuse\Auth\Interfaces\LoginRateLimiterInterface
     */
    private $rateLimiter;

    public function getId()
    {
        return 'web';
    }

    public function authenticate(Request $req, Response $res)
    {
        $username = $req->request('username');
        $password = $req->request('password');
        $remember = (bool) $req->request('remember');

        return $this->login($username, $password, $remember);
    }

    /**
     * Performs a traditional username/password login and
     * creates a signed in user.
     *
     * @param string $username username
     * @param string $password password
     * @param bool   $remember makes the session persistent
     *
     * @throws AuthException when the user cannot be signed in.
     *
     * @return bool success
     */
    public function login($username, $password, $remember = false)
    {
        $rateLimiter = $this->getRateLimiter();
        if (!$rateLimiter->canLogin($username)) {
            $window = $rateLimiter->getLockoutWindow($username);
            throw new AuthException("This account has been locked due to too many failed sign in attempts. The lock is only temporary. Please try again after $window.");
        }

        try {
            $user = $this->getUserWithCredentials($username, $password);
        } catch (AuthException $e) {
            // record user's failed login attempt and rethrow the exception
            $rateLimiter->recordFailedLogin($username);

            // throw a special message on the final failed attempt
            $remaining = $rateLimiter->getRemainingAttempts($username);
            if ($remaining === 0) {
                $window = $rateLimiter->getLockoutWindow($username);
                $message = $e->getMessage();
                $message .= " This account has been locked due to too many failed sign in attempts. The lock is only temporary. Please try again after $window.";
                throw new AuthException($message);
            }

            throw $e;
        }

        $this->signInUser($user, $this->getId(), $remember);

        return true;
    }

    /**
     * Fetches the user for a given username/password combination.
     *
     * @param string $username username
     * @param string $password password
     *
     * @throws AuthException when a matching user cannot be found.
     *
     * @return UserInterface matching user
     */
    public function getUserWithCredentials($username, $password)
    {
        if (empty($username)) {
            throw new AuthException('Please enter a valid username.');
        }

        if (empty($password)) {
            throw new AuthException('Please enter a valid password.');
        }

        // look the user up with the given username
        $usernameWhere = $this->buildUsernameWhere($username);
        $userClass = $this->auth->getUserClass();
        $user = $userClass::where($usernameWhere)
                          ->first();

        if (!$user) {
            throw new AuthException('We could not find a match for that email address and password.');
        }

        if (!$this->verifyPassword($user, $password)) {
            throw new AuthException('We could not find a match for that email address and password.');
        }

        if ($user->isTemporary()) {
            throw new AuthException('It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.');
        }

        if (!$user->isEnabled()) {
            throw new AuthException('Sorry, your account has been disabled.');
        }

        if (!$user->isVerified()) {
            throw new AuthException('You must verify your account with the email that was sent to you before you can log in.');
        }

        // success!
        return $user;
    }

    /**
     * Checks if a given password matches the user's password.
     *
     * @param UserInterface $user
     * @param string        $password
     *
     * @return bool
     */
    public function verifyPassword(UserInterface $user, $password)
    {
        $hashedPassword = $user->getHashedPassword();
        if (!$hashedPassword) {
            return false;
        }

        return password_verify($password, $hashedPassword);
    }

    /**
     * Gets the login rate limiter.
     *
     * @return \Infuse\Auth\Interfaces\LoginRateLimiterInterface
     */
    function getRateLimiter()
    {
        if ($this->rateLimiter) {
            return $this->rateLimiter;
        }

        $app = $this->auth->getApp();
        $class = $app['config']->get('auth.loginRateLimiter', NullRateLimiter::class);

        $this->rateLimiter = new $class();
        if (method_exists($this->rateLimiter, 'setApp')) {
            $this->rateLimiter->setApp($app);
        }

        return $this->rateLimiter;
    }

    /**
     * Builds a query string for matching the username.
     *
     * @param string $username username to match
     *
     * @return string
     */
    private function buildUsernameWhere($username)
    {
        $userClass = $this->auth->getUserClass();

        $conditions = array_map(
            function ($prop, $username) { return $prop." = '".$username."'"; },
            $userClass::$usernameProperties,
            array_fill(0, count($userClass::$usernameProperties),
            addslashes($username)));

        return '('.implode(' OR ', $conditions).')';
    }
}
