<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace app\auth\models;

use Infuse\Model;
use Infuse\Model\ACLModel;
use Infuse\Utility as U;
use Infuse\Validate;

abstract class AbstractUser extends ACLModel
{
    /////////////////////////////////////
    // Model Properties
    /////////////////////////////////////

    public static $scaffoldApi;
    protected static $autoTimestamps;

    protected static $ids = ['uid'];

    protected static $properties = [
        'uid' => [
            'type' => Model::TYPE_NUMBER,
            'mutable' => false,
            'admin_hidden_property' => true,
        ],
        'user_email' => [
            'validate' => 'email',
            'required' => true,
            'unique' => true,
            'title' => 'Email',
            'admin_html' => '<a href="mailto:{user_email}">{user_email}</a>',
        ],
        'user_password' => [
            'validate' => 'matching|password:8',
            'required' => true,
            'title' => 'Password',
            'admin_type' => 'password',
        ],
        'first_name' => [
            'validate' => 'string:1',
        ],
        'last_name' => [],
        'ip' => [
            'required' => true,
            'admin_html' => '<a href="http://www.infobyip.com/ip-{ip}.html" target="_blank">{ip}</a>',
        ],
        'enabled' => [
            'type' => Model::TYPE_BOOLEAN,
            'validate' => 'boolean',
            'required' => true,
            'default' => true,
        ],
    ];

    public static $usernameProperties = ['user_email'];

    /////////////////////////////////////
    // Protected Class Variables
    /////////////////////////////////////

    protected $logged_in = false;
    protected $isSu = false;
    protected $oldUid = false;
    protected static $protectedFields = ['user_email', 'user_password'];

    /**
     * Creates a new user.
     *
     * @param int  $id
     * @param bool $isLoggedIn when true, signifies that the user is logged in
     */
    public function __construct($id = false, $isLoggedIn = false)
    {
        parent::__construct($id);

        if ($isLoggedIn && $this->_id > 0) {
            $this->logged_in = true;
        }
    }

    protected function hasPermission($permission, Model $requester)
    {
        // allow user registrations
        if ($permission == 'create' && !$requester->isLoggedIn()) {
            return true;
        } elseif (in_array($permission, ['edit']) && $requester->id() == $this->_id) {
            return true;
        }

        return $requester->isAdmin();
    }

    ///////////////////////////////
    // HOOKS
    ///////////////////////////////

    protected function preSetHook(&$data)
    {
        if (!is_array($data)) {
            $data = [$data => $value];
        }

        $params = [];
        $protectedFields = static::$protectedFields;

        // check if the current password is accurate
        $passwordValidated = false;

        $encryptedPassword = U::encrypt_password(
            U::array_value($data, 'current_password'),
            $this->app['config']->get('site.salt'));

        if ($encryptedPassword == $this->user_password) {
            $passwordValidated = true;
        }

        $passwordRequired = false;

        foreach ($data as $key => $value) {
            if (static::hasProperty($key)) {
                if (in_array($key, $protectedFields)) {
                    // protected fields, i.e. passwords, are not allowed
                    // to be set to empty values
                    if (strlen(implode((array) $value)) == 0) {
                        unset($data[$key]);
                        continue;
                    }

                    $passwordRequired = true;
                }

                $data[$key] = $value;
            }
        }

        if ($passwordRequired && !$passwordValidated && !$this->can('skip-password-required', $this->app['user'])) {
            $errorStack = $this->app['errors'];
            $errorStack->push(['error' => 'invalid_password']);

            return false;
        }

        return true;
    }

    protected function preDeleteHook()
    {
        $this->deleteCache = $this->toArray();

        return true;
    }

    protected function postDeleteHook()
    {
        // nuke all related models
        $nuke = [
            'GroupMembers',
            'PersistentSessions',
            'UserLoginHistories',
            'UserLinks', ];

        foreach ($nuke as $tablename) {
            $this->app['db']->delete($tablename)->where('uid', $this->_id)->execute();
        }
    }

    /////////////////////////////////////
    // GETTERS
    /////////////////////////////////////

    /**
     * Get's the user's name.
     * WARNING this method should be overridden.
     *
     * @param bool $full get full name if true
     *
     * @return string name
     */
    abstract public function name($full = false);

    /**
     * Gets the temporary string if the user is temporary.
     *
     * @return link|false true if temporary
     */
    public function isTemporary()
    {
        return UserLink::totalRecords([
            'uid' => $this->_id,
            'link_type' => USER_LINK_TEMPORARY, ]) > 0;
    }

    /**
     * Checks if the account has been verified.
     *
     * @param bool $withinTimeWindow when true, allows a time window before the account is considered unverified
     *
     * @return bool
     */
    public function isVerified($withinTimeWindow = true)
    {
        $timeWindow = ($withinTimeWindow) ? time() - UserLink::$verifyTimeWindow : time();

        return UserLink::totalRecords([
            'uid' => $this->_id,
            'link_type' => USER_LINK_VERIFY_EMAIL,
            'created_at <= "'.U::unixToDb($timeWindow).'"', ]) == 0;
    }

    /**
     * Checks if the user is logged in.
     *
     * @return bool true if logged in
     */
    public function isLoggedIn()
    {
        return $this->logged_in;
    }

    /**
     * Checks if the user is an admin.
     *
     * @todo not implemented
     *
     * @return bool true if admin
     */
    public function isAdmin()
    {
        return $this->isSu || $this->isMemberOf('admin');
    }

    /**
     * Gets the groups this user is a member of.
     *
     * @return array groups
     */
    public function groups()
    {
        $return = ['everyone'];

        $groups = GroupMember::where(['uid' => $this->_id])
            ->sort('`group` ASC')
            ->all();

        foreach ($groups as $group) {
            $return[] = $group->group;
        }

        return $return;
    }

    /**
     * Checks if the user is a member of a group.
     *
     * @param string $group
     *
     * @return bool true if member
     */
    public function isMemberOf($group)
    {
        if ($group == 'everyone') {
            return true;
        }

        return GroupMember::totalRecords([
            'group' => $group,
            'uid' => $this->_id, ]) == 1;
    }

    /**
     * Generates the URL for the user's profile picture.
     *
     * Gravatar is used for profile pictures. To accomplish this we need to generate a hash of the user's email.
     *
     * @param int $size size of the picture (it is square, usually)
     *
     * @return string url
     */
    public function profilePicture($size = 200)
    {
        // use Gravatar
        $hash = md5(strtolower(trim($this->user_email)));

        return "https://secure.gravatar.com/avatar/$hash?s=$size&d=mm";
    }

    ///////////////////////////////////
    // SUPER USER PERMISSIONS
    ///////////////////////////////////

    /**
     * Elevates the current user to super user status. This grants all permissions
     * to everything. BE CAREFUL. Typically, this is reserved for cron jobs that need
     * to work with models belonging to other users.
     *
     * WARNING: do not forget to remove super user permissions when done with disableSU()
     * or else the permissions system will be rendered moot until the request/app exits
     */
    public function enableSU()
    {
        if ($this->isSu) {
            return;
        }

        $this->isSu = true;
        $this->oldUid = $this->_id;
        $this->_id = SUPER_USER;
    }

    /**
     * Removes super user permission.
     */
    public function disableSU()
    {
        if (!$this->isSu) {
            return;
        }

        $this->isSu = false;
        $this->_id = $this->oldUid;
        $this->oldUid = false;
    }

    ///////////////////////////////////
    // ACCOUNT CREATION
    ///////////////////////////////////

    /**
     * Registers a new user.
     *
     * @param array $data          user data
     * @param bool  $verifiedEmail true if the email has been verified
     *
     * @return bool success
     */
    public static function registerUser(array $data, $verifiedEmail = false)
    {
        $tempUser = self::$injectedApp['auth']->getTemporaryUser(U::array_value($data, 'user_email'));

        // upgrade temporary account
        if ($tempUser &&
            self::$injectedApp['auth']->upgradeTemporaryAccount($tempUser, $data)) {
            return $tempUser;
        }

        $user = new static();

        if ($user->create($data)) {
            if (!$verifiedEmail) {
                self::$injectedApp['auth']->sendVerificationEmail($user);
            } else {
                // send the user a welcome message
                $user->sendEmail('welcome');
            }

            return $user;
        }

        return false;
    }

    /**
     * Creates a temporary user. Useful for creating invites.
     *
     * @param array $data user data
     *
     * @return User temporary user
     */
    public static function createTemporary($data)
    {
        $email = U::array_value($data, 'user_email');
        if (!Validate::is($email, 'email')) {
            return false;
        }

        $insertArray = array_replace($data, ['enabled' => 0]);

        // create the temporary user
        $user = new static();
        $driver = self::getDriver();
        $created = $driver->createModel($user, $insertArray);

        if (!$created) {
            return false;
        }

        // get the new user ID
        $id = [];
        foreach (static::getIDProperties() as $k) {
            $id[] = $driver->getCreatedID($user, $k);
        }

        $user = new static($id);

        // create the temporary link
        $link = new UserLink();
        $link->grantAllPermissions()
             ->create([
                'uid' => $user->id(),
                'link_type' => USER_LINK_TEMPORARY, ]);

        return $user;
    }

    ///////////////////////////////////
    // UTILITIES
    ///////////////////////////////////

    /**
     * Sends the user an email.
     *
     * @param string $template template name
     * @param array  $message  message details
     *
     * @return bool success
     */
    public function sendEmail($template, $message = [])
    {
        $params = array_replace([
            'base_url' => $this->app['base_url'],
            'siteEmail' => $this->app['config']->get('site.email'),
            'email' => $this->user_email,
            'username' => $this->name(true),
            'to' => [[
                'email' => $this->user_email,
                'name' => $this->name(true), ]],
            'tags' => array_merge([$template], (array) U::array_value($message, 'tags')),
        ], $message);

        switch ($template) {
        case 'welcome':
            $params['subject'] = 'Welcome to '.$this->app['config']->get('site.title');
        break;
        case 'verify-email':
            $params['subject'] = 'Please verify your email address';
            $params['verify_link'] = "{$params['base_url']}users/verifyEmail/{$params['verify']}";
        break;
        case 'forgot-password':
            $params['subject'] = 'Password change request on '.$this->app['config']->get('site.title');
            $params['forgot_link'] = "{$params['base_url']}users/forgot/{$params['forgot']}";
        break;
        case 'password-changed':
            $params['subject'] = 'Your password was changed on '.$this->app['config']->get('site.title');
        break;
        }

        $message = array_replace($params, $message);

        $this->app['mailer']->queueEmail($template, $message);

        return true;
    }

    /**
     * Deletes the user account permanently (DANGER!).
     *
     * @param string $password
     *
     * @return bool success
     */
    public function deleteConfirm($password)
    {
        $this->app['errors']->setCurrentContext('user.delete');

        // Check for the password.
        // Only the current user can delete their account using this method
        if ($this->exists() &&
            !$this->isAdmin() &&
            $this->app['user']->id() == $this->_id &&
            U::encrypt_password($password, $this->app['config']->get('site.salt')) == $this->user_password) {
            $this->grantAllPermissions();

            return $this->delete();
        }

        return false;
    }
}
