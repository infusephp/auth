<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs\Storage;

use Infuse\Auth\Libs\RememberMeCookie;
use Infuse\Request;
use Infuse\Response;
use Pulsar\Model;

class SessionStorage extends AbstractStorage
{
    const SESSION_USER_ID_KEY = 'user_id';
    const REMEMBER_ME_COOKIE_NAME = 'persistent';

    public function getAuthenticatedUser(Request $req, Response $res)
    {
        if ($user = $this->getUserSession($req)) {
            return $user;
        }

        if ($user = $this->getUserRememberMe($req, $res)) {
            return $user;
        }

        return false;
    }

    public function signIn(Model $user, Request $req, Response $res)
    {
        // nothing to do if the user ID is already signed in
        $userId = $user->id();
        if ($req->session(self::SESSION_USER_ID_KEY) == $userId) {
            return true;
        }

        if (!headers_sent() && session_status() == PHP_SESSION_ACTIVE) {
            // regenerate session id to prevent session hijacking
            session_regenerate_id(true);

            // hang on to the new session id
            $sid = session_id();

            // close the old and new sessions
            session_write_close();

            // re-open the new session
            session_id($sid);
            session_start();
        }

        // set the user id
        $req->setSession([
            self::SESSION_USER_ID_KEY => $userId,
            'user_agent' => $req->agent(),
        ]);

        return true;
    }

    public function signOut(Request $req, Response $res)
    {
        // things to destroy:
        // - session cookie
        // - session variables
        // - PHP session
        // - remember me cookie

        $sessionCookie = session_get_cookie_params();
        $res->setCookie(
            session_name(),
            '',
            time() - 86400,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            $sessionCookie['httponly']);

        $req->destroySession();

        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->destroyRememberMeCookie($res);

        return true;
    }

    public function remember(Model $user, Request $req, Response $res)
    {
        $cookie = new RememberMeCookie($user->email, $req->agent());
        $this->sendRememberMeCookie($user->id(), $cookie, $res);

        return true;
    }

    /**
     * Tries to get an authenticated user via the current session.
     *
     * @param Request $req
     *
     * @return Model|false
     */
    private function getUserSession(Request $req)
    {
        // check for a session hijacking attempt via the stored user agent
        if ($req->session('user_agent') !== $req->agent()) {
            return false;
        }

        $userId = $req->session(self::SESSION_USER_ID_KEY);
        if ($userId === null) {
            return false;
        }

        $userClass = $this->auth->getUserClass();
        $user = new $userClass($userId, true);

        // check if the user exists
        if (!$user->exists()) {
            return false;
        }

        return $user;
    }

    /**
     * Tries to get an authenticated user via remember me.
     *
     * @param Request  $req
     * @param Response $res
     *
     * @return Model|false
     */
    private function getUserRememberMe(Request $req, Response $res)
    {
        // get the decoded remember me cookie
        $encoded = $req->cookies(self::REMEMBER_ME_COOKIE_NAME);
        $cookie = RememberMeCookie::decode($encoded);

        $user = $cookie->verify($req, $this->auth);
        if (!$user) {
            $this->destroyRememberMeCookie($res);

            return false;
        }

        $signedInUser = $this->auth->signInUser($user->id(), 'persistent');

        // generate a new remember me cookie for the next time, using
        // the same series
        $new = new RememberMeCookie($user->email, $req->agent(), $cookie->getSeries());
        $this->sendRememberMeCookie($user->id(), $new, $res);

        // mark this session as persistent (could be useful to know)
        $req->setSession('persistent', true);

        return $signedInUser;
    }

    /**
     * Stores a persistent session cookie on the response.
     *
     * @param int              $userId
     * @param RememberMeCookie $cookie
     * @param Response         $res
     */
    private function sendRememberMeCookie($userId, RememberMeCookie $cookie, Response $res)
    {
        // send the cookie with the same properties as the session cookie
        $sessionCookie = session_get_cookie_params();
        $res->setCookie(
            self::REMEMBER_ME_COOKIE_NAME,
            $cookie->encode(),
            $cookie->getExpires(time()),
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        $cookie->persist($userId);
    }

    /**
     * Destroys the remember me cookie.
     *
     * @param Response $res
     *
     * @return self
     */
    private function destroyRememberMeCookie(Response $res)
    {
        $sessionCookie = session_get_cookie_params();
        $res->setCookie(
            self::REMEMBER_ME_COOKIE_NAME,
            '',
            time() - 86400,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        return $this;
    }
}
