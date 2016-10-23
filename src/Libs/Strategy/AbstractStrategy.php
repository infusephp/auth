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

use Infuse\Auth\Interfaces\StrategyInterface;
use Infuse\Auth\Interfaces\UserInterface;
use Infuse\Auth\Libs\Auth;
use Infuse\HasApp;

abstract class AbstractStrategy implements StrategyInterface
{
    use HasApp;

    /**
     * @var Auth
     */
    protected $auth;

    /**
     * @param Auth $auth
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        $this->setApp($auth->getApp());
    }

    /**
     * Helper method to start a new user session from this strategy.
     *
     * @param UserInterface $user
     * @param bool          $remember whether to enable remember me on this session
     *
     * @return User
     */
    protected function signInUser(UserInterface $user, $remember = false)
    {
        return $this->auth->signInUser($user, $this->getId(), $remember);
    }
}
