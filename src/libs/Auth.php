<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace app\auth\libs;

use Infuse\Model;
use Infuse\Utility as U;
use Infuse\Validate;
use App;
use app\auth\models\PersistentSession;
use app\auth\models\UserLink;
use app\auth\models\UserLoginHistory;

if (!defined('GUEST')) {
    define('GUEST', -1);
}
if (!defined('SUPER_USER')) {
    define('SUPER_USER', -2);
}
if (!defined('USER_LINK_FORGOT_PASSWORD')) {
    define('USER_LINK_FORGOT_PASSWORD', 0);
}
if (!defined('USER_LINK_VERIFY_EMAIL')) {
    define('USER_LINK_VERIFY_EMAIL', 1);
}
if (!defined('USER_LINK_TEMPORARY')) {
    define('USER_LINK_TEMPORARY', 2);
}

class Auth
{
    const USER_MODEL = '\\app\\users\\models\\User';

    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

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
        if ($this->app['req']->session('user_id') != GUEST) {
            $this->changeSessionUserID(GUEST);
        }

        $userModel = self::USER_MODEL;

        return new $userModel(GUEST, false);
    }

    /////////////////////////
    // LOGIN
    /////////////////////////

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
        $req = $this->app['req'];
        $res = $this->app['res'];

        // empty the session cookie
        $sessionCookie = session_get_cookie_params();
        $res->setCookie(
            session_name(),
            '',
            time() - 86400,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            $sessionCookie['httponly']);

        // destroy the session variables
        $req->destroySession();

        // actually destroy the session now
        session_destroy();

        // delete persistent session cookie
        $res->setCookie(
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
            $errorStack->push(['error' => 'user_bad_username']);

            return false;
        }

        if (empty($password)) {
            $errorStack->push(['error' => 'user_bad_password']);

            return false;
        }

        $userModel = self::USER_MODEL;

        // build the query string for the username
        $usernameWhere = '('.implode(' OR ', array_map(function ($prop, $username) {
            return $prop." = '".$username."'";
        }, $userModel::$usernameProperties, array_fill(0, count($userModel::$usernameProperties), addslashes($username)))).')';

        // look the user up
        $user = $userModel::where([
                $usernameWhere,
                'user_password' => U::encrypt_password($password, $this->app['config']->get('site.salt')), ])
            ->first();

        if ($user) {
            $user->load();

            if ($user->isTemporary()) {
                $errorStack->push(['error' => 'user_login_temporary']);

                return false;
            } elseif (!$user->enabled) {
                $errorStack->push(['error' => 'user_login_disabled']);

                return false;
            } elseif (!$user->isVerified()) {
                $errorStack->push([
                    'error' => 'user_login_unverified',
                    'params' => [
                        'uid' => $user->id(), ], ]);

                return false;
            }

            // success!
            return $user;
        } else {
            $errorStack->push(['error' => 'user_login_no_match']);

            return false;
        }
    }

    /**
     * Returns a logged in user for a given uid.
     * This method is used for signing in via any
     * provider (traditional, oauth, fb, twitter, etc.).
     *
     * @param int $uid
     * @param int $type an integer flag to denote the login type
     *
     * @return User authenticated user model
     */
    public function signInUser($uid, $type = 'web')
    {
        $userModel = self::USER_MODEL;
        $user = new $userModel($uid, true);

        // update the session with the user's id
        $this->changeSessionUserID($uid);

        // create a login history entry
        $history = new UserLoginHistory();
        $history->grantAllPermissions();
        $history->create([
            'uid' => $uid,
            'type' => $type,
            'ip' => $this->app['req']->ip(),
            'user_agent' => $this->app['req']->agent(), ]);

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
        if ($user = $userModel::where(['user_email' => $email])->first()) {
            if ($user->isTemporary()) {
                return $user;
            }
        }

        return false;
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
            $this->app['db']->delete('UserLinks')->where([
                    'uid' => $user->id(),
                    '(link_type = '.USER_LINK_TEMPORARY.' OR link_type = '.USER_LINK_VERIFY_EMAIL.')', ])
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

    public function sendVerificationEmail(Model $user)
    {
        // delete previous verify links
        $this->app['db']->delete('UserLinks')->where([
            'uid' => $user->id(),
            'link_type' => USER_LINK_VERIFY_EMAIL, ])->execute();

        // create new verification link
        $link = new UserLink();
        $link->grantAllPermissions();
        if (!$link->create([
            'uid' => $user->id(),
            'link_type' => USER_LINK_VERIFY_EMAIL, ])) {
            return false;
        }

        // e-mail it
        return $user->sendEmail('verify-email', ['verify' => $link->link]);
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

        $link = UserLink::where([
                'link' => $verifyLink,
                'link_type' => USER_LINK_VERIFY_EMAIL, ])
            ->first();

        if ($link) {
            $userModel = self::USER_MODEL;
            $user = new $userModel($link->uid);

            // enable the user
            $user->grantAllPermissions();
            $user->set('enabled', 1);
            $user->enforcePermissions();

            // delete the verify link
            $link->grantAllPermissions();
            $link->delete();

            // send a welcome e-mail
            $user->sendEmail('welcome');

            return $user;
        }

        return false;
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
        $link = UserLink::where([
                'link' => $token,
                'link_type' => USER_LINK_FORGOT_PASSWORD,
                'created_at > "'.U::unixToDb(time() - UserLink::$forgotLinkTimeframe).'"', ])
            ->first();

        if ($link) {
            $userModel = self::USER_MODEL;

            return new $userModel($link->uid);
        } else {
            $this->app['errors']->push(['error' => 'user_forgot_expired_invalid']);
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
                'error' => VALIDATION_FAILED,
                'params' => [
                    'field' => 'email',
                    'field_name' => 'Email', ], ]);

            return false;
        }

        $userModel = self::USER_MODEL;
        $user = $userModel::where(['user_email' => $email])
            ->first();

        if (!$user || $user->isTemporary()) {
            $errorStack->push(['error' => 'user_forgot_email_no_match']);

            return false;
        }

        $user->load();

        // make sure there are no other forgot links
        $oldLinks = UserLink::totalRecords([
            'link_type' => USER_LINK_FORGOT_PASSWORD,
            'created_at > "'.U::unixToDb(time() - UserLink::$forgotLinkTimeframe).'"', ]);

        if ($oldLinks > 0) {
            return true;
        }

        $link = new UserLink();
        $link->grantAllPermissions();
        $success = $link->create([
            'uid' => $user->id(),
            'link_type' => USER_LINK_FORGOT_PASSWORD, ]);

        // send the user the forgot link
        if ($success) {
            $user->sendEmail(
                'forgot-password',
                [
                    'ip' => $ip,
                    'forgot' => $link->link, ]);
        }

        return $success;
    }

    /**
     * Step 2 in the forgot password process. Resets the password with a valid token.
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

        if ($user) {
            // Password cannot be empty
            if (strlen(implode($password)) == 0) {
                return false;
            }

            // Update the password
            $user->grantAllPermissions();
            $success = $user->set('user_password', $password);
            $user->enforcePermissions();

            if ($success) {
                $this->app['db']->delete('UserLinks')->where([
                    'uid' => $user->id(),
                    'link_type' => USER_LINK_FORGOT_PASSWORD, ])->execute();

                $user->sendEmail('password-changed', [
                    'ip' => $ip, ]);
            }

            return $success;
        }

        return false;
    }

    /////////////////////////
    // PRIVATE FUNCTIONS
    /////////////////////////

    /**
     * Attempts to authenticate the user
     * using the session strategy.
     *
     * @return User
     */
    private function authenticateSession()
    {
        $req = $this->app['req'];

        // check if the user's session is already logged in and valid
        if ($req->session('user_agent') == $req->agent()) {
            $userModel = self::USER_MODEL;
            $user = new $userModel($req->session('user_id'), true);

            if ($user->exists()) {
                $user->load();

                return $user;
            }
        }

        return false;
    }

    /**
     * Attempts to authenticate the user
     * using the persistent session strategy.
     */
    private function authenticatePersistentSession()
    {
        $req = $this->app['req'];

        // check for persistent sessions
        if ($cookie = $req->cookies('persistent')) {
            // decode the cookie
            $cookieParams = json_decode(base64_decode($cookie));

            if ($cookieParams) {
                $userModel = self::USER_MODEL;
                $user = $userModel::where(['user_email' => $cookieParams->user_email])
                    ->first();

                if ($user) {
                    // encrypt series and token for matching with the db
                    $seriesEnc = U::encrypt_password($cookieParams->series, $this->app['config']->get('site.salt'));
                    $tokenEnc = U::encrypt_password($cookieParams->token, $this->app['config']->get('site.salt'));

                    // first, make sure all of the parameters match, except the token
                    // we match the token separately in case all of the other information matches,
                    // which means an older session is being used, and then we run away
                    $select = $this->app['db']->select('token')
                        ->from('PersistentSessions')->where([
                            'user_email' => $cookieParams->user_email,
                            'created_at > "'.U::unixToDb(time() - PersistentSession::$sessionLength).'"',
                            'series' => $seriesEnc, ]);
                    $tokenDB = $select->scalar();

                    if ($select->rowCount() == 1 && $cookieParams->agent == $req->agent()) {
                        // if there is a match, sign the user in
                        if ($tokenDB == $tokenEnc) {
                            // remove the token
                            $this->app['db']->delete('PersistentSessions')->where([
                                'user_email' => $cookieParams->user_email,
                                'series' => $seriesEnc,
                                'token' => $tokenEnc, ])->execute();

                            $user = $this->signInUser($user->id(), 'persistent');

                            // generate a new cookie for the next time
                            self::storePersistentCookie($user->id(), $cookieParams->user_email, $cookieParams->series);

                            // mark this session as persistent (useful for security checks)
                            $req->setSession('persistent', true);

                            $user->load();

                            return $user;
                        } else {
                            // same series, but different token.
                            // the user is trying to use an older token
                            // most likely an attack, so flush all sessions
                            $this->app['db']->delete('PersistentSessions')
                                ->where('user_email', $cookieParams->user_email)->execute();
                        }
                    }
                }
            }

            // delete persistent session cookie
            $sessionCookie = session_get_cookie_params();
            $this->app['res']->setCookie(
                'persistent',
                '',
                time() - 86400,
                $sessionCookie['path'],
                $sessionCookie['domain'],
                $sessionCookie['secure'],
                true);
        }
    }

    private function changeSessionUserID($uid)
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
        $req = $this->app['req'];
        $req->setSession([
            'user_id' => $uid,
            'user_agent' => $req->agent(), ]);
    }

    private function generateToken()
    {
        $str = '';
        for ($i = 0; $i < 16; ++$i) {
            $str .= base_convert(mt_rand(1, 36), 10, 36);
        }

        return $str;
    }

    private function storePersistentCookie($uid, $email, $series = null, $token = null)
    {
        if (!$series) {
            $series = $this->generateToken();
        }

        if (!$token) {
            $token = $this->generateToken();
        }

        $req = $this->app['req'];

        $sessionCookie = session_get_cookie_params();
        $this->app['res']->setCookie(
            'persistent',
            base64_encode(json_encode([
                'user_email' => $email,
                'series' => $series,
                'token' => $token,
                'agent' => $req->agent(), ])),
            time() + PersistentSession::$sessionLength,
            $sessionCookie['path'],
            $sessionCookie['domain'],
            $sessionCookie['secure'],
            true);

        $config = $this->app['config'];
        $session = new PersistentSession();
        $session->grantAllPermissions();
        $session->create([
            'user_email' => $email,
            'series' => U::encrypt_password($series, $config->get('site.salt')),
            'token' => U::encrypt_password($token, $config->get('site.salt')),
            'uid' => $uid, ]);
    }
}
