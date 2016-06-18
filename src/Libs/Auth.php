<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth\Libs;

use App\Auth\Exception\AuthException;
use App\Auth\Models\PersistentSession;
use App\Auth\Models\UserLink;
use App\Auth\Models\UserLoginHistory;
use Infuse\HasApp;
use Pulsar\Model;
use Infuse\Request;
use Infuse\Response;
use Infuse\Utility as U;

if (!defined('GUEST')) {
    define('GUEST', -1);
}

class Auth
{
    use HasApp;

    const DEFAULT_USER_MODEL = 'App\Users\Models\User';

    const ERROR_BAD_USERNAME = 'auth.bad_username';
    const ERROR_BAD_EMAIL = 'auth.bad_email';
    const ERROR_BAD_PASSWORD = 'auth.bad_password';
    const ERROR_LOGIN_NO_MATCH = 'auth.login_no_match';
    const ERROR_LOGIN_TEMPORARY = 'auth.login_temporary';
    const ERROR_LOGIN_DISABLED = 'auth.login_disabled';
    const ERROR_LOGIN_UNVERIFIED = 'auth.login_unverified';
    const ERROR_FORGOT_EMAIL_NO_MATCH = 'auth.forgot_email_no_match';
    const ERROR_FORGOT_TOKEN_INVALID = 'auth.forgot_token_invalid';

    private static $messages = [
        self::ERROR_BAD_USERNAME => 'Please enter a valid username.',
        self::ERROR_BAD_EMAIL => 'Please enter a valid email address.',
        self::ERROR_BAD_PASSWORD => 'Please enter a valid password.',
        self::ERROR_LOGIN_NO_MATCH => 'We could not find a match for that email address and password.',
        self::ERROR_LOGIN_TEMPORARY => 'It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.',
        self::ERROR_LOGIN_DISABLED => 'Sorry, your account has been disabled.',
        self::ERROR_LOGIN_UNVERIFIED => 'You must verify your account with the email that was sent to you before you can log in.',
        self::ERROR_FORGOT_EMAIL_NO_MATCH => 'We could not find a match for that email address.',
        self::ERROR_FORGOT_TOKEN_INVALID => 'This link has expired or is invalid.',
    ];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * Sets the request object.
     *
     * @param Request $request
     *
     * @return self
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Gets the request object.
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Sets the response object.
     *
     * @param Response $response
     *
     * @return self
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Gets the response object.
     *
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the user model class.
     *
     * @return string
     */
    public function getUserClass()
    {
        if ($model = $this->app['config']->get('users.model')) {
            return $model;
        }

        return self::DEFAULT_USER_MODEL;
    }

    /////////////////////////
    // LOGIN
    /////////////////////////

    /**
     * Gets the currently authenticated user using the
     * available authentication strategies.
     *
     * @return User
     */
    public function getAuthenticatedUser()
    {
        if ($user = $this->authenticateSession()) {
            return $user;
        }

        if ($user = $this->authenticatePersistentSession()) {
            return $user;
        }

        // change session user id back to guest if we thought
        // user was someone else
        if ($this->request->session('user_id') != GUEST) {
            $this->changeSessionUserID(GUEST);
        }

        $userModel = $this->getUserClass();

        return new $userModel(GUEST, false);
    }

    /**
     * Performs a traditional username/password login and
     * creates a signed in user.
     *
     * @param string $username   username
     * @param string $password   password
     * @param bool   $persistent make the session persistent
     *
     * @throws AuthException when the user cannot be signed in.
     *
     * @return bool success
     */
    public function login($username, $password, $persistent = false)
    {
        $user = $this->getUserWithCredentials($username, $password);

        $this->app['user'] = $this->signInUser($user->id(), 'web');

        if ($persistent) {
            self::storePersistentCookie($user->id(), $user->user_email);
        }

        return true;
    }

    /**
     * Logs the authenticated user out.
     *
     * @return bool success
     */
    public function logout()
    {
        // empty the session cookie
        $sessionCookie = session_get_cookie_params();
        $this->response->setCookie(
            session_name(),
            '',
            time() - 86400,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            $sessionCookie['httponly']);

        // destroy the session variables
        $this->request->destroySession();

        // actually destroy the session now
        session_destroy();

        // delete persistent session cookie
        $this->response->setCookie(
            'persistent',
            '',
            time() - 86400,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        $this->changeSessionUserID(GUEST);

        $userModel = $this->getUserClass();
        $this->app['user'] = new $userModel(GUEST, false);

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
     * @return User matching user
     */
    public function getUserWithCredentials($username, $password)
    {
        if (empty($username)) {
            $error = self::ERROR_BAD_USERNAME;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        if (empty($password)) {
            $error = self::ERROR_BAD_PASSWORD;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        // build the query string for the username
        $usernameWhere = $this->buildUsernameWhere($username);

        // encrypt password
        $password = $this->encrypt($password);

        // look the user up with the matching username/password combo
        $userModel = $this->getUserClass();
        $user = $userModel::where($usernameWhere)
            ->where('user_password', $password)
            ->first();

        if (!$user) {
            $error = self::ERROR_LOGIN_NO_MATCH;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        if ($user->isTemporary()) {
            $error = self::ERROR_LOGIN_TEMPORARY;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        if (!$user->enabled) {
            $error = self::ERROR_LOGIN_DISABLED;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        if (!$user->isVerified()) {
            $error = self::ERROR_LOGIN_UNVERIFIED;
            $message = $this->app['locale']->t($error, ['user_id' => $user->id()], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        // success!
        return $user;
    }

    /**
     * Returns a logged in user for a given user ID.
     * This method is used for signing in via any
     * provider (traditional, oauth, fb, twitter, etc.).
     *
     * @param int $userId
     * @param int $type   an integer flag to denote the login type
     *
     * @return User authenticated user model
     */
    public function signInUser($userId, $type = 'web')
    {
        $userModel = $this->getUserClass();
        $user = new $userModel($userId, true);

        // update the session with the user's id
        $this->changeSessionUserID($userId);

        // create a login history entry
        $history = new UserLoginHistory();
        $history->create([
            'user_id' => $userId,
            'type' => $type,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->agent(),
        ]);

        return $user;
    }

    /////////////////////////
    // TEMPORARY USERS
    /////////////////////////

    /**
     * Gets a temporary user from an email address if one exists.
     *
     * @param string $email email address
     *
     * @return User|false
     */
    public function getTemporaryUser($email)
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $userModel = $this->getUserClass();
        $user = $userModel::where('user_email', $email)->first();

        if (!$user || !$user->isTemporary()) {
            return false;
        }

        return $user;
    }

    /**
     * Upgrades the user from temporary to a fully registered account.
     *
     * @param User  $user user
     * @param array $data user data
     *
     * @return bool true if successful
     */
    public function upgradeTemporaryAccount(Model $user, $data)
    {
        if (!$user->isTemporary()) {
            return true;
        }

        $updateArray = array_replace($data, [
            'created_at' => U::unixToDb(time()),
            'enabled' => 1, ]);

        $success = false;

        $user->grantAllPermissions();
        if ($user->set($updateArray)) {
            // remove temporary and unverified links
            $this->app['db']->delete('UserLinks')
                ->where('user_id', $user->id())
                ->where('(link_type = '.UserLink::TEMPORARY.' OR link_type = '.UserLink::VERIFY_EMAIL.')')
                ->execute();

            // send the user a welcome message
            $user->sendEmail('welcome');

            $success = true;
        }
        $user->enforcePermissions();

        return $success;
    }

    /////////////////////////
    // EMAIL VERIFICATION
    /////////////////////////

    /**
     * Sends a verification email to a user.
     *
     * @param Model $user
     *
     * @return bool
     */
    public function sendVerificationEmail(Model $user)
    {
        $params = [
            'user_id' => $user->id(),
            'link_type' => UserLink::VERIFY_EMAIL,
        ];

        // delete previous verify links
        $this->app['db']->delete('UserLinks')
            ->where($params)
            ->execute();

        // create new verification link
        $link = new UserLink();
        $link->create($params);

        // email it
        return $user->sendEmail('verify-email',
            ['verify' => $link->link]);
    }

    /**
     * Processes a verify email hash.
     *
     * @param string $token verification hash
     *
     * @return User|false
     */
    public function verifyEmailWithToken($token)
    {
        $link = UserLink::where('link', $token)
            ->where('link_type', UserLink::VERIFY_EMAIL)
            ->first();

        if (!$link) {
            return false;
        }

        $userModel = $this->getUserClass();
        $user = new $userModel($link->user_id);

        // enable the user
        $user->enabled = true;
        $user->grantAllPermissions()->save();
        $user->enforcePermissions();

        // delete the verify link
        $link->delete();

        // send a welcome email
        $user->sendEmail('welcome');

        return $user;
    }

    /**
     * @deprecated
     */
    public function verifyEmailWithLink($token)
    {
        return $this->verifyEmailWithToken($token);
    }

    /////////////////////////
    // FORGOT PASSWORD
    /////////////////////////

    /**
     * Looks up a user from a given forgot token.
     *
     * @param string $token
     *
     * @throws AuthException when the token is invalid.
     *
     * @return User
     */
    public function getUserFromForgotToken($token)
    {
        $link = UserLink::where('link', $token)
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', U::unixToDb(time() - UserLink::$forgotLinkTimeframe), '>')
            ->first();

        if (!$link) {
            $error = self::ERROR_FORGOT_TOKEN_INVALID;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        $userModel = $this->getUserClass();

        return new $userModel($link->user_id);
    }

    /**
     * The first step in the forgot password sequence.
     *
     * @param string $email email address
     * @param string $ip    ip address making the request
     *
     * @throws AuthException when the step cannot be completed.
     *
     * @return bool success?
     */
    public function forgotStep1($email, $ip)
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = self::ERROR_BAD_EMAIL;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        $userModel = $this->getUserClass();
        $user = $userModel::where('user_email', $email)
            ->first();

        if (!$user || $user->isTemporary()) {
            $error = self::ERROR_FORGOT_EMAIL_NO_MATCH;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        // make sure there are no other forgot links
        $oldLinks = UserLink::totalRecords([
            'user_id' => $user->id(),
            'link_type' => UserLink::FORGOT_PASSWORD,
            'created_at > "'.U::unixToDb(time() - UserLink::$forgotLinkTimeframe).'"', ]);

        if ($oldLinks > 0) {
            return true;
        }

        $link = new UserLink();
        $link->user_id = $user->id();
        $link->link_type = UserLink::FORGOT_PASSWORD;
        $link->save();

        // finally send the user the reset password link
        return $user->sendEmail('forgot-password', [
            'ip' => $ip,
            'forgot' => $link->link, ]);
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
     * @return bool success
     */
    public function forgotStep2($token, array $password, $ip)
    {
        $user = $this->getUserFromForgotToken($token);

        // Update the password
        $user->user_password = $password;
        $success = $user->grantAllPermissions()->save();
        $user->enforcePermissions();

        if (!$success) {
            $error = self::ERROR_BAD_PASSWORD;
            $message = $this->app['locale']->t($error, [], false, self::$messages[$error]);
            throw new AuthException($message);
        }

        $this->app['db']->delete('UserLinks')
            ->where('user_id', $user->id())
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->execute();

        $user->sendEmail('password-changed',
            ['ip' => $ip]);

        return true;
    }

    /////////////////////////
    // UTILITY FUNCTIONS
    /////////////////////////

    /**
     * Encrypts a password.
     *
     * @param string $password
     *
     * @return string encrypted password
     */
    public function encrypt($password)
    {
        return U::encryptPassword($password, $this->app['config']->get('app.salt'));
    }

    /////////////////////////
    // PRIVATE FUNCTIONS
    /////////////////////////

    /**
     * Builds a query string for matching the username.
     *
     * @param string $username username to match
     *
     * @return string
     */
    private function buildUsernameWhere($username)
    {
        $userModel = $this->getUserClass();

        $conditions = array_map(
            function ($prop, $username) { return $prop." = '".$username."'"; },
            $userModel::$usernameProperties,
            array_fill(0, count($userModel::$usernameProperties),
            addslashes($username)));

        return '('.implode(' OR ', $conditions).')';
    }

    /**
     * Attempts to authenticate the user
     * using the session strategy.
     *
     * @return User|false
     */
    private function authenticateSession()
    {
        // check if the user's session is already logged in and valid
        if ($this->request->session('user_agent') == $this->request->agent()) {
            $userModel = $this->getUserClass();
            $user = new $userModel($this->request->session('user_id'), true);

            if ($user->exists()) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Attempts to authenticate the user
     * using the persistent session strategy.
     *
     * @return User|false
     */
    private function authenticatePersistentSession()
    {
        // check for persistent sessions
        if ($cookie = $this->request->cookies('persistent')) {
            // decode the cookie
            $cookieParams = json_decode(base64_decode($cookie));

            if ($cookieParams) {
                $userModel = $this->getUserClass();
                $user = $userModel::where('user_email', $cookieParams->user_email)
                    ->first();

                if ($user) {
                    // encrypt series and token for matching with the db
                    $seriesEnc = $this->encrypt($cookieParams->series);
                    $tokenEnc = $this->encrypt($cookieParams->token);

                    // first, make sure all of the parameters match, except the token
                    // we match the token separately in case all of the other information matches,
                    // which means an older session is being used, and then we run away
                    $select = $this->app['db']->select('token')
                        ->from('PersistentSessions')
                        ->where('user_email', $cookieParams->user_email)
                        ->where('created_at', U::unixToDb(time() - PersistentSession::$sessionLength), '>')
                        ->where('series', $seriesEnc);

                    $tokenDB = $select->scalar();

                    if ($select->rowCount() == 1 && $cookieParams->agent == $this->request->agent()) {
                        // if there is a match, sign the user in
                        if ($tokenDB == $tokenEnc) {
                            // remove the token
                            $this->app['db']->delete('PersistentSessions')
                                ->where('user_email', $cookieParams->user_email)
                                ->where('series', $seriesEnc)
                                ->where('token', $tokenEnc)
                                ->execute();

                            $user = $this->signInUser($user->id(), 'persistent');

                            // generate a new cookie for the next time
                            self::storePersistentCookie($user->id(), $cookieParams->user_email, $cookieParams->series);

                            // mark this session as persistent (useful for security checks)
                            $this->request->setSession('persistent', true);

                            return $user;
                        } else {
                            // same series, but different token.
                            // the user is trying to use an older token
                            // most likely an attack, so flush all sessions
                            $this->app['db']->delete('PersistentSessions')
                                ->where('user_email', $cookieParams->user_email)
                                ->execute();
                        }
                    }
                }
            }

            // delete persistent session cookie
            $sessionCookie = session_get_cookie_params();
            $this->response->setCookie(
                'persistent',
                '',
                time() - 86400,
                $sessionCookie['path'],
                $sessionCookie['domain'],
                $sessionCookie['secure'],
                true);
        }

        return false;
    }

    /**
     * Changes the user ID for the session.
     *
     * @param int $userId
     */
    private function changeSessionUserID($userId)
    {
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
        $this->request->setSession([
            'user_id' => $userId,
            'user_agent' => $this->request->agent(), ]);
    }

    /**
     * Generates a random 32-digit token for persistent sessions.
     *
     * @return string
     */
    private function generateToken()
    {
        $str = '';
        for ($i = 0; $i < 16; ++$i) {
            $str .= base_convert(mt_rand(1, 36), 10, 36);
        }

        return $str;
    }

    /**
     * Stores a persistent session cookie on the response.
     *
     * @param int    $userId
     * @param string $email
     * @param string $series
     * @param string $token
     */
    private function storePersistentCookie($userId, $email, $series = null, $token = null)
    {
        if (!$series) {
            $series = $this->generateToken();
        }

        if (!$token) {
            $token = $this->generateToken();
        }

        $sessionCookie = session_get_cookie_params();
        $this->response->setCookie(
            'persistent',
            base64_encode(json_encode([
                'user_email' => $email,
                'series' => $series,
                'token' => $token,
                'agent' => $this->request->agent(), ])),
            time() + PersistentSession::$sessionLength,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        $config = $this->app['config'];
        $session = new PersistentSession();
        $session->create([
            'user_email' => $email,
            'series' => $this->encrypt($series),
            'token' => $this->encrypt($token),
            'user_id' => $userId,
        ]);
    }
}
