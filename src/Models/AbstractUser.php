<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Models;

use Infuse\Application;
use Infuse\Utility as U;
use Pulsar\ACLModel;
use Pulsar\Model;

abstract class AbstractUser extends ACLModel
{
    protected static $properties = [
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

    public static $scaffoldApi;
    protected static $autoTimestamps;

    /**
     * @staticvar array
     */
    public static $usernameProperties = ['user_email'];

    /**
     * @staticvar array
     */
    protected static $protectedFields = ['user_email', 'user_password'];

    /**
     * @var bool
     */
    protected $signedIn = false;

    /**
     * @var bool
     */
    protected $superUser = false;

    /**
     * Creates a new user.
     *
     * @param int  $id
     * @param bool $isSignedIn when true, signifies that the user is signed in
     */
    public function __construct($id = false, $isSignedIn = false)
    {
        parent::__construct($id);

        if ($isSignedIn && $this->id() > 0) {
            $this->signedIn = true;
        }
    }

    protected function hasPermission($permission, Model $requester)
    {
        // allow user registrations
        if ($permission == 'create' && !$requester->isSignedIn()) {
            return true;
        } elseif (in_array($permission, ['edit']) && $requester->id() == $this->id()) {
            return true;
        }

        return $requester->isAdmin();
    }

    ///////////////////////////////
    // HOOKS
    ///////////////////////////////

    protected function preSetHook(&$data)
    {
        $app = $this->getApp();

        if (!is_array($data)) {
            $data = [$data => $value];
        }

        $params = [];
        $protectedFields = static::$protectedFields;

        // check if the current password is accurate
        $password = array_value($data, 'current_password');
        $encryptedPassword = $app['auth']->encrypt($password);

        $passwordValidated = $encryptedPassword == $this->user_password;

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

        if ($passwordRequired && !$passwordValidated && !$this->can('skip-password-required', $app['user'])) {
            $app['errors']->push('invalid_password');

            return false;
        }

        return true;
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
            'user_id' => $this->id(),
            'link_type' => UserLink::TEMPORARY, ]) > 0;
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
            'user_id' => $this->id(),
            'link_type' => UserLink::VERIFY_EMAIL,
            'created_at <= "'.U::unixToDb($timeWindow).'"', ]) == 0;
    }

    /**
     * Checks if the user is signed in.
     *
     * @return bool
     */
    public function isSignedIn()
    {
        return $this->signedIn;
    }

    /**
     * @deprecated
     */
    public function isLoggedIn()
    {
        return $this->isSignedIn();
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
        return $this->isSuperUser() || $this->isMemberOf('admin');
    }

    /**
     * Gets the groups this user is a member of.
     *
     * @return array groups
     */
    public function groups()
    {
        $return = ['everyone'];

        $groups = GroupMember::where('user_id', $this->id())
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
            'user_id' => $this->id(), ]) == 1;
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
     * WARNING: do not forget to remove super user permissions when done with demoteToNormalUser()
     * or else the permissions system will be rendered moot until the request/app exits
     *
     * @return self
     */
    public function promoteToSuperUser()
    {
        $this->superUser = true;

        return $this;
    }

    /**
     * Demotes the current user back to a normal user.
     *
     * @return self
     */
    public function demoteToNormalUser()
    {
        $this->superUser = false;

        return $this;
    }

    /**
     * Checks if this user is a super user.
     *
     * @return bool
     */
    public function isSuperUser()
    {
        return $this->superUser;
    }

    /**
     * @deprecated
     */
    public function enableSU()
    {
        return $this->promoteToSuperUser();
    }

    /**
     * @deprecated
     */
    public function disableSU()
    {
        return $this->demoteToNormalUser();
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
        $app = Application::getDefault();
        $tempUser = $app['auth']->getTemporaryUser(array_value($data, 'user_email'));

        // upgrade temporary account
        if ($tempUser &&
            $app['auth']->upgradeTemporaryAccount($tempUser, $data)) {
            return $tempUser;
        }

        $user = new static();

        if ($user->create($data)) {
            if (!$verifiedEmail) {
                $app['auth']->sendVerificationEmail($user);
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
        $email = trim(strtolower(array_value($data, 'user_email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
        $link->create([
            'user_id' => $user->id(),
            'link_type' => UserLink::TEMPORARY, ]);

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
        $app = $this->getApp();

        $params = array_replace([
            'base_url' => $app['base_url'],
            'siteEmail' => $app['config']->get('app.email'),
            'email' => $this->user_email,
            'username' => $this->name(true),
            'to' => [[
                'email' => $this->user_email,
                'name' => $this->name(true), ]],
            'tags' => array_merge([$template], (array) array_value($message, 'tags')),
        ], $message);

        switch ($template) {
        case 'welcome':
            $params['subject'] = 'Welcome to '.$app['config']->get('app.title');
        break;
        case 'verify-email':
            $params['subject'] = 'Please verify your email address';
            $params['verify_link'] = "{$params['base_url']}users/verifyEmail/{$params['verify']}";
        break;
        case 'forgot-password':
            $params['subject'] = 'Password change request on '.$app['config']->get('app.title');
            $params['forgot_link'] = "{$params['base_url']}users/forgot/{$params['forgot']}";
        break;
        case 'password-changed':
            $params['subject'] = 'Your password was changed on '.$app['config']->get('app.title');
        break;
        }

        $message = array_replace($params, $message);

        $app['mailer']->queueEmail($template, $message);

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
        $app = $this->getApp();

        // Check for the password.
        // Only the current user can delete their account using this method
        $password = $app['auth']->encrypt($password);
        if (!$this->exists() ||
            $this->isAdmin() ||
            $app['user']->id() != $this->id() ||
            $password != $this->user_password) {
            return false;
        }

        return $this->grantAllPermissions()->delete();
    }
}
