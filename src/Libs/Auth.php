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
use Infuse\Auth\Libs\Storage\SessionStorage;
use Infuse\Auth\Libs\Storage\StorageInterface;
use Infuse\Auth\Models\UserLink;
use Infuse\Auth\Models\UserLoginHistory;
use Infuse\HasApp;
use Infuse\Request;
use Infuse\Response;
use Infuse\Utility as U;
use InvalidArgumentException;
use Pulsar\Model;

if (!defined('GUEST')) {
    define('GUEST', -1);
}

class Auth
{
    use HasApp;

    const DEFAULT_USER_MODEL = 'App\Users\Models\User';

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var array
     */
    private $availableStrategies = [
        'traditional' => 'Infuse\Auth\Libs\Strategy\TraditionalStrategy',
    ];

    /**
     * @var array
     */
    private $strategies = [];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var PasswordRest
     */
    private $reset;

    /**
     * Gets the user model class.
     *
     * @return string
     */
    public function getUserClass()
    {
        return $this->app['config']->get('users.model', self::DEFAULT_USER_MODEL);
    }

    /**
     * Registers an authentication strategy.
     *
     * @param string $id
     * @param string $class
     *
     * @return self
     */
    public function registerStrategy($id, $class)
    {
        $this->availableStrategies[$id] = $class;

        return $this;
    }

    /**
     * Gets an authentication strategy.
     *
     * @param string $id
     *
     * @throws InvalidArgumentException if the strategy does not exist
     *
     * @return Infuse\Auth\Strategy\StrategyInterface
     */
    public function getStrategy($id)
    {
        if (isset($this->strategies[$id])) {
            return $this->strategies[$id];
        }

        if (!isset($this->availableStrategies[$id])) {
            throw new InvalidArgumentException("Auth strategy '$id' does not exist or has not been registered.");
        }

        $class = $this->availableStrategies[$id];
        $strategy = new $class($this);
        $this->strategies[$id] = $strategy;

        return $strategy;
    }

    /**
     * Sets the session storage adapter.
     *
     * @param StorageInterface $storage
     *
     * @return self
     */
    public function setStorage(StorageInterface $storage)
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Gets the session storage adapter.
     *
     * @return StorageInterface
     */
    public function getStorage()
    {
        if (!$this->storage) {
            $this->storage = new SessionStorage($this);
        }

        return $this->storage;
    }

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
     * Handles a user authentication request.
     *
     * @param string $strategy strategy identifier
     *
     * @throws Infuse\Auth\Exception\AuthException when unable to authenticate the user.
     *
     * @return User|Response
     */
    public function authenticate($strategy)
    {
        return $this->getStrategy($strategy)
                    ->authenticate($this->request, $this->response);
    }

    /**
     * Gets the currently authenticated user.
     *
     * @return User
     */
    public function getAuthenticatedUser()
    {
        $user = $this->getStorage()
                     ->getAuthenticatedUser($this->request, $this->response);

        // if no current user then sign in a guest
        if (!$user) {
            return $this->signInUser(GUEST);
        }

        return $user;
    }

    /**
     * Signs in a user using the traditional strategy.
     *
     * @param string $username username
     * @param string $password password
     * @param bool   $remember whether to enable remember me on this session
     *
     * @throws AuthException when the user cannot be signed in.
     *
     * @return bool success
     */
    public function login($username, $password, $remember = false)
    {
        return $this->getStrategy('traditional')
                    ->login($username, $password, $remember);
    }

    /**
     * Logs the authenticated user out.
     *
     * @return bool success
     */
    public function logout()
    {
        $result = $this->getStorage()
                       ->signOut($this->request, $this->response);

        $this->signInUser(GUEST);

        return $result;
    }

    /**
     * Builds a signed in user object for a given user ID and signs
     * the user into the session storage. This method should be used
     * by authentication strategies to build a signed in session once
     * a user is authenticated.
     *
     * @param int    $userId
     * @param string $strategy
     * @param bool   $remember whether to enable remember me on this session
     *
     * @return User authenticated user model
     */
    public function signInUser($userId, $strategy = 'web', $remember = false)
    {
        // build the user model
        $userModel = $this->getUserClass();
        $signedIn = $userId > 0;
        $user = new $userModel($userId, $signedIn);

        // sign in the user with the session storage
        $storage = $this->getStorage();

        $storage->signIn($userId, $this->request, $this->response);

        if ($remember) {
            $storage->remember($user, $this->request, $this->response);
        }

        // record the login event
        if ($userId > 0) {
            $history = new UserLoginHistory();
            $history->user_id = $userId;
            $history->type = $strategy;
            $history->ip = $this->request->ip();
            $history->user_agent = $this->request->agent();
            $history->save();
        }

        $this->app['user'] = $user;

        return $user;
    }

    /////////////////////////
    // REGISTRATION
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

        if (!$user) {
            return false;
        }

        if (!$user->isTemporary()) {
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
     * @throws InvalidArgumentException when trying to upgrade a non-temporary account.
     *
     * @return bool true if successful
     */
    public function upgradeTemporaryAccount(Model $user, $data)
    {
        if (!$user->isTemporary()) {
            throw new InvalidArgumentException('Cannot upgrade a non-temporary account');
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

    /////////////////////////
    // FORGOT PASSWORD
    /////////////////////////

    /**
     * Gets a reset password instance.
     *
     * @param ResetPassword $reset
     *
     * @return self
     */
    public function setPasswordReset(ResetPassword $reset)
    {
        $this->reset = $reset;

        return $this;
    }

    /**
     * Gets a reset password instance.
     *
     * @return ResetPassword
     */
    public function getPasswordReset()
    {
        if (!$this->reset) {
            $this->reset = new ResetPassword($this);
        }

        return $this->reset;
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
    public function getUserFromForgotToken($token)
    {
        return $this->getPasswordReset()
                    ->getUserFromToken($token);
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
    public function forgotStep1($email, $ip)
    {
        return $this->getPasswordReset()
                    ->step1($email, $ip);
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
    public function forgotStep2($token, array $password, $ip)
    {
        return $this->getPasswordReset()
                    ->step2($token, $password, $ip);
    }
}
