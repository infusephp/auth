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
use Pulsar\Model;
use Infuse\Utility as U;

class UserLink extends Model
{
    const FORGOT_PASSWORD = 0;
    const VERIFY_EMAIL = 1;
    const TEMPORARY = 2;

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

    protected function preCreateHook(&$data)
    {
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
