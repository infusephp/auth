<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Users\Models;

use App\Auth\Models\AbstractUser;

class User extends AbstractUser
{
    /**
     * Gets the user's name.
     *
     * @param bool $full when true gets full name
     *
     * @return string
     */
    public function name($full = false)
    {
        return $this->first_name;
    }
}
