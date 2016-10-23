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
use Infuse\Request;
use Infuse\Response;

class InMemoryStorage extends AbstractStorage
{
    /**
     * @var UserInterface|false
     */
    private $user = false;

    public function signIn(UserInterface $user, Request $req, Response $res)
    {
        $this->user = $user;

        return true;
    }

    public function remember(UserInterface $user, Request $req, Response $res)
    {
        return true;
    }

    public function getAuthenticatedUser(Request $req, Response $res)
    {
        return $this->user;
    }

    public function signOut(Request $req, Response $res)
    {
        $this->user = false;

        return true;
    }
}
