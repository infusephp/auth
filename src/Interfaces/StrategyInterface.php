<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Interfaces;

use Infuse\Request;
use Infuse\Response;

interface StrategyInterface
{
    /**
     * Gets the ID of this authentication strategy.
     *
     * @return string
     */
    public function getId();

    /**
     * Handles a user authentication request.
     *
     * @param Request  $req
     * @param Response $res
     *
     * @throws \Infuse\Auth\Exception\AuthException when unable to authenticate the user.
     *
     * @return \Infuse\Auth\Interfaces\UserInterface|Response
     */
    public function authenticate(Request $req, Response $res);
}
