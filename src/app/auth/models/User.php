<?php

namespace app\auth\models;

/**
 * DO NOT USE THIS CLASS
 * This is a hack for the tests to generate a table for the users
 */
class User extends AbstractUser
{
	/** 
	 * Gets the user's name
	 *
	 * @param boolean $full when true gets full name
	 *
	 * @return string
	 */
	function name( $full = false )
	{
		return $this->first_name;
	}
}