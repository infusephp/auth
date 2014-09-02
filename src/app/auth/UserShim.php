<?php

namespace app\users\models;

use app\auth\models\AbstractUser;

class UserShim extends AbstractUser
{
    /**
	 * Gets the user's name
	 *
	 * @param boolean $full when true gets full name
	 *
	 * @return string
	 */
    public function name($full = false)
    {
        return $this->first_name;
    }
}
