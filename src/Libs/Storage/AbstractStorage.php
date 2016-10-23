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

use Infuse\Auth\Interfaces\StorageInterface;
use Infuse\Auth\Libs\Auth;
use Infuse\HasApp;

abstract class AbstractStorage implements StorageInterface
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
}
