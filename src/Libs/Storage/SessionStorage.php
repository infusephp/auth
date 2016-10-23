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

use Infuse\Auth\Interfaces\UserInterface;
use Infuse\Auth\Libs\RememberMeCookie;
use Infuse\Auth\Models\ActiveSession;
use Infuse\Request;
use Infuse\Response;

class SessionStorage extends AbstractStorage
{
    const SESSION_USER_ID_KEY = 'user_id';
    const REMEMBER_ME_COOKIE_NAME = 'persistent';

    public function signIn(UserInterface $user, Request $req, Response $res)
    {
        // nothing to do if the user ID is already signed in
        $currentUserId = $req->session(self::SESSION_USER_ID_KEY);
        $userId = $user->id();
        if ($currentUserId == $userId) {
            return true;
        }

        // we are going to kill the current session and start a new one
        $req->destroySession();

        if (session_status() == PHP_SESSION_ACTIVE) {
            // remove the currently active session, for signed in users
            if ($currentUserId > 0 && $sid = session_id()) {
                // delete any active sessions for this session ID
                $this->deleteSession($sid);
            }

            // regenerate session id to prevent session hijacking
            session_regenerate_id(true);

            // hang on to the new session id
            $sid = session_id();

            // close the old and new sessions
            session_write_close();

            // re-open the new session
            session_id($sid);
            session_start();

            // record the active session, for signed in users
            if ($userId > 0) {
                // create an active session for this session ID
                $this->createSession($sid, $userId, $req);
            }
        }

        // set the user id
        $req->setSession([
            self::SESSION_USER_ID_KEY => $userId,
            'user_agent' => $req->agent(),
        ]);

        return true;
    }

    public function remember(UserInterface $user, Request $req, Response $res)
    {
        $cookie = new RememberMeCookie($user->email, $req->agent());
        $this->sendRememberMeCookie($user->id(), $cookie, $res);

        return true;
    }

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

    public function signOut(Request $req, Response $res)
    {
        // things to destroy:
        // - session cookie
        // - session variables
        // - PHP session
        // - remember me cookie

        $sessionCookie = session_get_cookie_params();
        $res->setCookie(session_name(),
                        '',
                        time() - 86400,
                        $sessionCookie['path'],
                        $sessionCookie['domain'],
                        $sessionCookie['secure'],
                        $sessionCookie['httponly']);

        $req->destroySession();

        if (session_status() == PHP_SESSION_ACTIVE) {
            $sid = session_id();

            session_destroy();

            if ($sid) {
                // delete active sessions for this session ID
                $this->deleteSession($sid);
            }
        }

        $this->destroyRememberMeCookie($res);

        return true;
    }

    ///////////////////////////////
    // Private Methods
    ///////////////////////////////

    /**
     * Tries to get an authenticated user via the current session.
     *
     * @param Request $req
     *
     * @return UserInterface|false
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
        $user = new $userClass($userId);

        // if this is a guest user then just return it now
        if ($userId <= 0) {
            return $user;
        }

        // check if the user exists
        if (!$user->exists()) {
            return false;
        }

        // refresh the active session
        if (session_status() == PHP_SESSION_ACTIVE) {
            // check if the session valid
            $sid = session_id();
            if (!$this->sessionIsValid($sid)) {
                return false;
            }

            $this->refreshSession($sid);
        }

        return $user->signIn();
    }

    /**
     * Creates an active session for a user.
     *
     * @param string  $sid    session ID
     * @param int     $userId
     * @param Request $req
     *
     * @return ActiveSession
     */
    private function createSession($sid, $userId, Request $req)
    {
        $sessionCookie = session_get_cookie_params();
        $expires = time() + $sessionCookie['lifetime'];

        $session = new ActiveSession();
        $session->id = $sid;
        $session->user_id = $userId;
        $session->ip = $req->ip();
        $session->user_agent = $req->agent();
        $session->expires = $expires;
        $session->save();

        return $session;
    }

    /**
     * Checks if a session has been invalidated.
     *
     * @param string $sid
     *
     * @return bool
     */
    private function sessionIsValid($sid)
    {
        return $this->app['db']->select('count(*)')
                               ->from('ActiveSessions')
                               ->where('id', $sid)
                               ->where('valid', false)
                               ->scalar() == 0;
    }

    /**
     * Refreshes the expiration on an active session.
     *
     * @param string $sid session ID
     *
     * @return bool
     */
    private function refreshSession($sid)
    {
        $sessionCookie = session_get_cookie_params();
        $expires = time() + $sessionCookie['lifetime'];

        $this->app['db']->update('ActiveSessions')
                        ->where('id', $sid)
                        ->values(['expires' => $expires])
                        ->execute();

        return true;
    }

    /**
     * Deletes an active session.
     *
     * @param string $sid
     *
     * @return bool
     */
    private function deleteSession($sid)
    {
        $this->app['db']->delete('ActiveSessions')
                        ->where('id', $sid)
                        ->execute();

        return true;
    }

    /**
     * Tries to get an authenticated user via remember me.
     *
     * @param Request  $req
     * @param Response $res
     *
     * @return UserInterface|false
     */
    private function getUserRememberMe(Request $req, Response $res)
    {
        // retrieve and verify the remember me cookie
        $cookie = $this->getRememberMeCookie($req);
        $user = $cookie->verify($req, $this->auth);
        if (!$user) {
            $this->destroyRememberMeCookie($res);

            return false;
        }

        $signedInUser = $this->auth->signInUser($user, 'persistent');

        // generate a new remember me cookie for the next time, using
        // the same series
        $new = new RememberMeCookie($user->email,
                                    $req->agent(),
                                    $cookie->getSeries());
        $this->sendRememberMeCookie($user->id(), $new, $res);

        // mark this session as persistent (could be useful to know)
        $req->setSession('persistent', true);

        return $signedInUser;
    }

    /**
     * Gets the decoded remember me cookie from the request.
     *
     * @param Request $req
     *
     * @return RememberMeCookie
     */
    private function getRememberMeCookie(Request $req)
    {
        $encoded = $req->cookies(self::REMEMBER_ME_COOKIE_NAME);

        return RememberMeCookie::decode($encoded);
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
        $res->setCookie(self::REMEMBER_ME_COOKIE_NAME,
                        $cookie->encode(),
                        $cookie->getExpires(time()),
                        $sessionCookie['path'],
                        $sessionCookie['domain'],
                        $sessionCookie['secure'],
                        true);

        // save the cookie in the DB
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
        $res->setCookie(self::REMEMBER_ME_COOKIE_NAME,
                        '',
                        time() - 86400,
                        $sessionCookie['path'],
                        $sessionCookie['domain'],
                        $sessionCookie['secure'],
                        true);

        return $this;
    }
}
