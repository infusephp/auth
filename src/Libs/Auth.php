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

use App\Auth\Models\PersistentSession;
use App\Auth\Models\UserLink;
use App\Auth\Models\UserLoginHistory;
use Infuse\HasApp;
use Pulsar\Model;
use Infuse\Request;
use Infuse\Response;
use Infuse\Utility as U;
use Pulsar\Validate;

if (!defined('GUEST')) {
    define('GUEST', -1);
}

class Auth
{
    use HasApp;

    const USER_MODEL = 'App\Users\Models\User';

    const ERROR_BAD_USERNAME = 'user_bad_username';
    const ERROR_BAD_PASSWORD = 'user_bad_password';
    const ERROR_LOGIN_TEMPORARY = 'user_login_temporary';
    const ERROR_LOGIN_DISABLED = 'user_login_disabled';
    const ERROR_LOGIN_NO_MATCH = 'user_login_no_match';
    const ERROR_LOGIN_UNVERIFIED = 'user_login_unverified';
    const ERROR_FORGOT_EMAIL_NO_MATCH = 'user_forgot_email_no_match';
    const ERROR_FORGOT_EXPIRED_INVALID = 'user_forgot_expired_invalid';

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

        $userModel = self::USER_MODEL;

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
     * @return bool success
     */
    public function login($username, $password, $persistent = false)
    {
        $this->app['errors']->setCurrentContext('auth.login');

        $user = $this->getUserWithCredentials($username, $password);
        if ($user) {
            $this->app['user'] = $this->signInUser($user->id(), 'web');

            if ($persistent) {
                self::storePersistentCookie($user->id(), $user->user_email);
            }

            return true;
        }

        return false;
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

        $userModel = self::USER_MODEL;
        $this->app['user'] = new $userModel(GUEST, false);

        return true;
    }

    /**
     * Fetches the user for a given username/password combination.
     *
     * @param string $username username
     * @param string $password password
     *
     * @return User|false matching user
     */
    public function getUserWithCredentials($username, $password)
    {
        $errorStack = $this->app['errors'];

        if (empty($username)) {
            $errorStack->push(['error' => self::ERROR_BAD_USERNAME]);

            return false;
        }

        if (empty($password)) {
            $errorStack->push(['error' => self::ERROR_BAD_PASSWORD]);

            return false;
        }

        // build the query string for the username
        $usernameWhere = $this->buildUsernameWhere($username);

        // encrypt password
        $password = $this->encrypt($password);

        // look the user up with the matching username/password combo
        $userModel = self::USER_MODEL;
        $user = $userModel::where($usernameWhere)
            ->where('user_password', $password)
            ->first();

        if (!$user) {
            $errorStack->push(['error' => self::ERROR_LOGIN_NO_MATCH]);

            return false;
        }

        if ($user->isTemporary()) {
            $errorStack->push(['error' => self::ERROR_LOGIN_TEMPORARY]);

            return false;
        }

        if (!$user->enabled) {
            $errorStack->push(['error' => self::ERROR_LOGIN_DISABLED]);

            return false;
        }

        if (!$user->isVerified()) {
            $errorStack->push([
                'error' => self::ERROR_LOGIN_UNVERIFIED,
                'params' => [
                    'uid' => $user->id(), ], ]);

            return false;
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
        $userModel = self::USER_MODEL;
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
     * Gets a temporary user from an e-mail address if one exists.
     *
     * @param string $email e-mail address
     *
     * @return User|false
     */
    public function getTemporaryUser($email)
    {
        if (!Validate::is($email, 'email')) {
            return false;
        }

        $userModel = self::USER_MODEL;
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
        if (!$link->create($params)) {
            return false;
        }

        // email it
        return $user->sendEmail('verify-email',
            ['verify' => $link->link]);
    }

    /**
     * Processes a verify e-mail hash.
     *
     * @param string $verifyLink verification hash
     *
     * @return User|false
     */
    public function verifyEmailWithLink($verifyLink)
    {
        $this->app['errors']->setCurrentContext('auth.verify');

        $link = UserLink::where('link', $verifyLink)
            ->where('link_type', UserLink::VERIFY_EMAIL)
            ->first();

        if (!$link) {
            return false;
        }

        $userModel = self::USER_MODEL;
        $user = new $userModel($link->user_id);

        // enable the user
        $user->enabled = true;
        $user->grantAllPermissions()->save();
        $user->enforcePermissions();

        // delete the verify link
        $link->delete();

        // send a welcome e-mail
        $user->sendEmail('welcome');

        return $user;
    }

    /////////////////////////
    // FORGOT PASSWORD
    /////////////////////////

    /**
     * Looks up a user from a given forgot token.
     *
     * @param string $token
     *
     * @return User|false
     */
    public function getUserFromForgotToken($token)
    {
        $link = UserLink::where('link', $token)
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('created_at', U::unixToDb(time() - UserLink::$forgotLinkTimeframe), '>')
            ->first();

        if ($link) {
            $userModel = self::USER_MODEL;

            return new $userModel($link->user_id);
        } else {
            $this->app['errors']->push(['error' => self::ERROR_FORGOT_EXPIRED_INVALID]);
        }

        return false;
    }

    /**
     * The first step in the forgot password sequence.
     *
     * @param string $email e-mail address
     * @param string $ip    ip address making the request
     *
     * @return bool success?
     */
    public function forgotStep1($email, $ip)
    {
        $errorStack = $this->app['errors'];
        $errorStack->setCurrentContext('auth.forgot');

        if (!Validate::is($email, 'email')) {
            $errorStack->push([
                'error' => Model::ERROR_VALIDATION_FAILED,
                'params' => [
                    'field' => 'email',
                    'field_name' => 'Email', ], ]);

            return false;
        }

        $userModel = self::USER_MODEL;
        $user = $userModel::where('user_email', $email)
            ->first();

        if (!$user || $user->isTemporary()) {
            $errorStack->push(['error' => self::ERROR_FORGOT_EMAIL_NO_MATCH]);

            return false;
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
        $success = $link->create([
            'user_id' => $user->id(),
            'link_type' => UserLink::FORGOT_PASSWORD,
        ]);

        if (!$success) {
            return false;
        }

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
     * @return bool success
     */
    public function forgotStep2($token, array $password, $ip)
    {
        $this->app['errors']->setCurrentContext('auth.forgot');

        $user = $this->getUserFromForgotToken($token);

        if (!$user) {
            return false;
        }

        // Password cannot be empty
        if (strlen(implode($password)) == 0) {
            return false;
        }

        // Update the password
        $user->user_password = $password;
        $success = $user->grantAllPermissions()->save();
        $user->enforcePermissions();

        if (!$success) {
            return false;
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
        return U::encrypt_password($password, $this->app['config']->get('app.salt'));
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
        $userModel = self::USER_MODEL;

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
            $userModel = self::USER_MODEL;
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
                $userModel = self::USER_MODEL;
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
