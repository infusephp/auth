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
use infuse\QueryBuilder;
use Infuse\Request;
use Infuse\Response;
use Infuse\Utility;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SymfonySessionStorage extends AbstractStorage
{
    const SESSION_USER_ID_KEY = 'user_id';
    const SESSION_USER_AGENT_KEY = 'user_agent';
    const SESSION_2FA_VERIFIED_KEY = '2fa_verified';
    const SESSION_REMEMBERED_KEY = 'remembered';

    /**
     * @var string
     */
    private $rememberMeCookieName;

    public function signIn(UserInterface $user, Request $req, Response $res)
    {
        $session = $this->getSession();

        // nothing to do if the user ID is already signed in
        $currentUserId = $session->get(self::SESSION_USER_ID_KEY);
        $userId = $user->id();
        if ($currentUserId == $userId) {
            return true;
        }

        // we are going to kill the current session and start a new one
        $req->destroySession();

        if ($session->isStarted()) {
            // remove the currently active session, for signed in users
            if ($currentUserId > 0 && $sid = $session->getId()) {
                // delete any active sessions for this session ID
                $this->deleteSession($sid);
            }

            // regenerate session id to prevent session hijacking
            $session->migrate(true);

            // hang on to the new session id
            $sid = $session->getId();

            // close the old and new sessions
            $session->save();

            // re-open the new session
            $session->setId($sid);
            $session->start();

            // record the active session, for signed in users
            if ($userId > 0) {
                // create an active session for this session ID
                $this->createSession($sid, $userId, $req);
            }
        }

        // set the user id
        $session->replace([
            self::SESSION_USER_ID_KEY => $userId,
            self::SESSION_USER_AGENT_KEY => $req->agent(),
        ]);
        $req->setSession([
            self::SESSION_USER_ID_KEY => $userId,
            self::SESSION_USER_AGENT_KEY => $req->agent(),
        ]);

        // mark the user's session as 2fa verified if needed
        if ($user->isTwoFactorVerified()) {
            $this->twoFactorVerified($user, $req, $res);
        }

        return true;
    }

    public function remember(UserInterface $user, Request $req, Response $res)
    {
        $cookie = new RememberMeCookie($user->email(), $req->agent());
        $this->sendRememberMeCookie($user, $cookie, $res);

        // mark this session as remembered (could be useful to know)
        $this->getSession()->set(self::SESSION_REMEMBERED_KEY, true);
        $req->setSession(self::SESSION_REMEMBERED_KEY, true);

        return true;
    }

    public function twoFactorVerified(UserInterface $user, Request $req, Response $res)
    {
        $this->getSession()->set(self::SESSION_2FA_VERIFIED_KEY, true);
        $req->setSession(self::SESSION_2FA_VERIFIED_KEY, true);

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
        // - session variables
        // - PHP session
        // - remember me cookie

        $req->destroySession();

        $session = $this->getSession();

        if ($session->isStarted()) {
            $sid = $session->getId();

            $session->invalidate();

            if ($sid) {
                // delete active sessions for this session ID
                $this->deleteSession($sid);
            }
        }

        $this->destroyRememberMeCookie($req, $res);

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
        $session = $this->getSession();
        if ($session->get(self::SESSION_USER_AGENT_KEY) !== $req->agent()) {
            return false;
        }

        $userId = $session->get(self::SESSION_USER_ID_KEY);
        if ($userId === null) {
            return false;
        }

        // if this is a guest user then just return it now
        $userClass = $this->auth->getUserClass();
        if ($userId <= 0) {
            return new $userClass($userId);
        }

        // look up the registered user
        $user = $userClass::where('id', $userId)->first();
        if (!$user) {
            return false;
        }

        // refresh the active session
        if ($session->isStarted()) {
            // check if the session valid
            $sid = $session->getId();
            if (!$this->sessionIsValid($sid)) {
                return false;
            }

            $this->refreshSession($sid);
        }

        // check if the user is 2FA verified
        if ($session->get(self::SESSION_2FA_VERIFIED_KEY)) {
            $user->markTwoFactorVerified();
        }

        return $user->markSignedIn();
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
        return ActiveSession::where('id', $sid)
                ->where('valid', false)
                ->count() == 0;
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

        $this->getDatabase()
            ->update('ActiveSessions')
            ->where('id', $sid)
            ->values([
                'expires' => $expires,
                'updated_at' => Utility::unixToDb(time())
            ])
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
        $this->getDatabase()
            ->delete('ActiveSessions')
            ->where('id', $sid)
            ->execute();

        return true;
    }

    /**
     * @return QueryBuilder
     */
    private function getDatabase()
    {
        return $this->app['database']->getDefault();
    }

    /**
     * @return SessionInterface
     */
    private function getSession()
    {
        return $this->app['symfony_session'];
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
        if (!$cookie) {
            return false;
        }

        $user = $cookie->verify($req, $this->auth);
        if (!$user) {
            $this->destroyRememberMeCookie($req, $res);

            return false;
        }

        $signedInUser = $this->auth->signInUser($user, 'remember_me');

        // generate a new remember me cookie for the next time, using
        // the same series
        $new = new RememberMeCookie($user->email(),
            $req->agent(),
            $cookie->getSeries());
        $this->sendRememberMeCookie($user, $new, $res);

        // mark this session as remembered (could be useful to know)
        $this->getSession()->set(self::SESSION_REMEMBERED_KEY, true);
        $req->setSession(self::SESSION_REMEMBERED_KEY, true);

        return $signedInUser;
    }

    /**
     * Gets the decoded remember me cookie from the request.
     *
     * @param Request $req
     *
     * @return RememberMeCookie|false
     */
    private function getRememberMeCookie(Request $req)
    {
        $encoded = $req->cookies($this->rememberMeCookieName());
        if (!$encoded) {
            return false;
        }

        return RememberMeCookie::decode($encoded);
    }

    /**
     * Stores a remember me session cookie on the response.
     *
     * @param UserInterface    $user
     * @param RememberMeCookie $cookie
     * @param Response         $res
     */
    private function sendRememberMeCookie(UserInterface $user, RememberMeCookie $cookie, Response $res)
    {
        // send the cookie with the same properties as the session cookie
        $sessionCookie = session_get_cookie_params();
        $res->setCookie($this->rememberMeCookieName(),
            $cookie->encode(),
            $cookie->getExpires(time()),
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        // save the cookie in the DB
        $cookie->persist($user);
    }

    /**
     * Destroys the remember me cookie.
     *
     * @param Request $req
     * @param Response $res
     *
     * @return self
     */
    private function destroyRememberMeCookie(Request $req, Response $res)
    {
        $cookie = $this->getRememberMeCookie($req);
        if ($cookie) {
            $cookie->destroy();
        }

        $sessionCookie = session_get_cookie_params();
        $res->setCookie($this->rememberMeCookieName(),
            '',
            time() - 86400,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        return $this;
    }

    private function rememberMeCookieName()
    {
        if (!$this->rememberMeCookieName) {
            $this->rememberMeCookieName = $this->getSession()->getName().'-remember';
        }

        return $this->rememberMeCookieName;
    }
}
