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
use Infuse\Auth\Libs\AuthManager;
use Infuse\HasApp;

abstract class AbstractStorage implements StorageInterface
{
    use HasApp;

    /**
     * @var AuthManager
     */
    protected $auth;

    /**
     * @param AuthManager $auth
     */
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
        $this->setApp($auth->getApp());
    }
}
