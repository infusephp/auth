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

class InMemoryStorage extends AbstractStorage
{
    /**
     * @var Model|false
     */
    private $user = false;

    public function getAuthenticatedUser(Request $req, Response $res)
    {
        return $this->user;
    }

    public function signIn($userId, Request $req, Response $res)
    {
        $userClass = $this->auth->getUserClass();
        $this->user = new $userClass($userId, true);

        return true;
    }

    public function signOut(Request $req, Response $res)
    {
        $this->user = false;

        return true;
    }

    public function remember(Model $user, Request $req, Response $res)
    {
        return true;
    }
}
