<?php

/**
 * @package infuse\framework
 * @author Jared King <j@jaredtking.com>
 * @link http://jaredtking.com
 * @version 0.1.16
 * @copyright 2013 Jared King
 * @license MIT
 */
 
namespace app\auth\models;

use infuse\Database;
use infuse\Model;

use app\auth\libs\Auth;

class PersistentSession extends Model
{
	public static $scaffoldApi;
	public static $autoTimestamps;

	public static $properties = [
		'token' => [
			'type' => 'id',
			'mutable' => true,
			'required' => true,
			'auto_increment' => false,
			'db_type' => 'char',
			'length' => 128
		],
		'user_email' => [
			'type' => 'text',
			'validate' => 'email'
		],
		'series' => [
			'type' => 'text',
			'db_type' => 'char',
			'length' => 128,
			'required' => true,
			'validate' => 'string:128',
			'admin_hidden_property' => true
		],
		'uid' => [
			'type' => 'id',
			'auto_increment' => false,
			'relation' => Auth::USER_MODEL
		],
	];
	
	public static $sessionLength = 7776000; // 3 months
	
	static function idProperty()
	{
		return 'token';
	}

	protected function hasPermission( $permission, Model $requester )
	{
		return $requester->isAdmin();
	}

	/**
	 * Clears out expired user links
	 */
	static function garbageCollect()
	{
		return Database::delete(
			'PersistentSessions',
			[ 'created_at < ' . (time() - self::$sessionLength) ] );
	}
}