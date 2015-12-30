<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth\Models;

use App\Auth\Libs\Auth;
use Infuse\Model;
use Infuse\Model\ACLModel;
use Infuse\Utility as U;

class UserLink extends ACLModel
{
    const LINK_FORGOT_PASSWORD = 0;
    const LINK_VERIFY_EMAIL = 1;
    const LINK_TEMPORARY = 2;

    public static $scaffoldApi;
    protected static $autoTimestamps;

    protected static $ids = ['uid', 'link'];

    protected static $properties = [
        'uid' => [
            'type' => Model::TYPE_NUMBER,
            'required' => true,
            'relation' => Auth::USER_MODEL,
        ],
        'link' => [
            'required' => true,
            'validate' => 'string:32',
        ],
        'link_type' => [
            'type' => Model::TYPE_NUMBER,
            'validate' => 'enum:0,1,2',
            'required' => true,
            'admin_type' => 'enum',
            'admin_enum' => [
                self::FORGOT_PASSWORD => 'Forgot Password',
                self::VERIFY_EMAIL => 'Verify E-mail',
                self::TEMPORARY => 'Temporary',
            ],
        ],
    ];

    public static $verifyTimeWindow = 86400; // one day

    public static $forgotLinkTimeframe = 1800; // 30 minutes

    protected function hasPermission($permission, Model $requester)
    {
        if ($permission == 'create') {
            return true;
        }

        return $requester->isAdmin();
    }

    protected function preCreateHook(&$data)
    {
        // can only create user links for the current user
        $user = $this->app['user'];
        if ($data['uid'] != $user->id() && !$this->can('create-with-mismatched-uid', $user)) {
            $this->app['errors']->push(['error' => ERROR_NO_PERMISSION]);

            return false;
        }

        if (!isset($data['link'])) {
            $data['link'] = strtolower(U::guid(false));
        }

        return true;
    }

    /**
     * Gets the URL for this link.
     *
     * @return string|false
     */
    public function url()
    {
        if ($this->link_type === self::FORGOT_PASSWORD) {
            return $this->app['base_url'].'users/forgot/'.$this->link;
        }

        return false;
    }

    /**
     * Clears out expired user links.
     *
     * @return bool
     */
    public static function garbageCollect()
    {
        return !!self::$injectedApp['db']->delete('UserLinks')
            ->where('link_type', self::FORGOT_PASSWORD)
            ->where('created_at', U::unixToDb(time() - self::$forgotLinkTimeframe), '<')
            ->execute();
    }
}
