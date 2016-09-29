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

use Infuse\Request;
use Infuse\Response;
use Pulsar\Model;

interface StorageInterface
{
    /**
     * Gets the authenticated user for the current session.
     *
     * @param Request  $req
     * @param Response $res
     *
     * @return Model|false
     */
    public function getAuthenticatedUser(Request $req, Response $res);

    /**
     * Starts a new user session.
     *
     * @param Model    $user
     * @param Request  $req
     * @param Response $res
     *
     * @return bool
     */
    public function signIn(Model $user, Request $req, Response $res);

    /**
     * Saves a remember me token on the user's session.
     *
     * @param Model    $user
     * @param Request  $req
     * @param Response $res
     *
     * @return bool
     */
    public function remember(Model $user, Request $req, Response $res);

    /**
     * Signs out the current user session.
     *
     * @param Request  $req
     * @param Response $res
     *
     * @return bool
     */
    public function signOut(Request $req, Response $res);
}
