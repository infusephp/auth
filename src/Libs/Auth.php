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
use Infuse\Auth\Interfaces\StorageInterface;
use Infuse\Auth\Interfaces\TwoFactorInterface;
use Infuse\Auth\Interfaces\UserInterface;
use Infuse\Auth\Libs\Storage\SessionStorage;
use Infuse\Auth\Models\AccountSecurityEvent;
use Infuse\Auth\Models\UserLink;
use Infuse\HasApp;
use Infuse\Request;
use Infuse\Response;
use InvalidArgumentException;

class Auth
{
    use HasApp;

    const DEFAULT_USER_MODEL = 'App\Users\Models\User';
    const GUEST_USER_ID = -1;

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
     * @var TwoFactorInterface
     */
    private $twoFactor;

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
     * Sets the two-factor authentication strategy.
     *
     * @param TwoFactorInterface $strategy
     *
     * @return self
     */
    public function setTwoFactorStrategy(TwoFactorInterface $strategy)
    {
        $this->twoFactor = $strategy;

        return $this;
    }

    /**
     * Gets the two-factor authentication strategy.
     *
     * @return TwoFactorInterface|null
     */
    public function getTwoFactorStrategy()
    {
        return $this->twoFactor;
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
     * @return UserInterface|Response
     */
    public function authenticate($strategy)
    {
        return $this->getStrategy($strategy)
                    ->authenticate($this->request, $this->response);
    }

    /**
     * Gets the currently authenticated user.
     *
     * @return UserInterface
     */
    public function getAuthenticatedUser()
    {
        $user = $this->getStorage()
                     ->getAuthenticatedUser($this->request, $this->response);

        // if no current user then sign in a guest
        if (!$user) {
            $user = $this->signInUser($this->getGuestUser());
        }

        $this->app['user'] = $user;

        return $user;
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

        $this->signInUser($this->getGuestUser());

        return $result;
    }

    /**
     * Builds a signed in user object for a given user ID and signs
     * the user into the session storage. This method should be used
     * by authentication strategies to build a signed in session once
     * a user is authenticated.
     *
     * @param UserInterface $user
     * @param string        $strategy
     * @param bool          $remember whether to enable remember me on this session
     *
     * @return UserInterface authenticated user model
     */
    public function signInUser(UserInterface $user, $strategy = 'web', $remember = false)
    {
        // sign in the user with the session storage
        $storage = $this->getStorage();
        $storage->signIn($user, $this->request, $this->response);

        // use a remember me session (lasts longer)
        if ($remember) {
            $storage->remember($user, $this->request, $this->response);
        }

        if ($user->id() > 0) {
            // mark the user model as signed in
            $user->signIn();

            // record the login event
            $event = new AccountSecurityEvent();
            $event->user_id = $user->id();
            $event->type = AccountSecurityEvent::LOGIN;
            $event->ip = $this->request->ip();
            $event->user_agent = $this->request->agent();
            $event->auth_strategy = $strategy;
            $event->save();
        } else {
            // mark the user model as not signed in
            $user->signOut();
        }

        $this->app['user'] = $user;

        return $user;
    }

    /**
     * Gets a guest user model.
     *
     * @return UserInterface
     */
    private function getGuestUser()
    {
        $userClass = $this->getUserClass();

        return new $userClass(self::GUEST_USER_ID);
    }

    /**
     * Invalidates all sessions for a given user.
     *
     * @param UserInterface $user
     *
     * @return bool
     */
    public function signOutAllSessions(UserInterface $user)
    {
        $db = $this->app['db'];

        // invalidate any active sessions
        $db->update('ActiveSessions')
           ->values(['valid' => 0])
           ->where('user_id', $user->id())
           ->execute();

        // invalidate any remember me sessions
        $db->delete('PersistentSessions')
           ->where('user_id', $user->id())
           ->execute();

        return true;
    }

    /////////////////////////
    // EMAIL VERIFICATION
    /////////////////////////

    /**
     * Sends a verification email to a user.
     *
     * @param UserInterface $user
     *
     * @return bool
     */
    public function sendVerificationEmail(UserInterface $user)
    {
        $params = [
            'user_id' => $user->id(),
            'type' => UserLink::VERIFY_EMAIL,
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
     * @return UserInterface|false
     */
    public function verifyEmailWithToken($token)
    {
        $link = UserLink::where('link', $token)
            ->where('type', UserLink::VERIFY_EMAIL)
            ->first();

        if (!$link) {
            return false;
        }

        $userClass = $this->getUserClass();
        $user = new $userClass($link->user_id);

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
     * @return UserInterface
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
     *
     * @throws AuthException when the step cannot be completed.
     *
     * @return bool
     */
    public function forgotStep1($email)
    {
        $ip = $this->request->ip();
        $userAgent = $this->request->agent();

        return $this->getPasswordReset()
                    ->step1($email, $ip, $userAgent);
    }

    /**
     * Step 2 in the forgot password process. Resets the password
     * given a valid token.
     *
     * @param string $token    token
     * @param array  $password new password
     *
     * @throws AuthException when the step cannot be completed.
     *
     * @return bool
     */
    public function forgotStep2($token, array $password)
    {
        $ip = $this->request->ip();

        return $this->getPasswordReset()
                    ->step2($token, $password, $ip);
    }
}
