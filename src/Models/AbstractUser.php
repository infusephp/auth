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
use Infuse\Auth\Interfaces\UserInterface;
use Infuse\HasApp;
use Infuse\Utility as U;
use InvalidArgumentException;
use Pulsar\ACLModel;
use Pulsar\Model;
use Pulsar\ModelEvent;

abstract class AbstractUser extends ACLModel implements UserInterface
{
    use HasApp;

    protected static $properties = [
        'email' => [
            'validate' => 'email',
            'required' => true,
            'unique' => true,
        ],
        'password' => [
            'validate' => 'matching|password_php:8',
            'required' => true,
        ],
        'first_name' => [
            'validate' => 'string:1',
        ],
        'last_name' => [],
        'ip' => [
            'required' => true,
        ],
        'enabled' => [
            'type' => Model::TYPE_BOOLEAN,
            'validate' => 'boolean',
            'required' => true,
            'default' => true,
        ],
    ];

    protected static $autoTimestamps;
    protected static $casts = [];
    protected static $validations = [];
    protected static $protected = [];
    protected static $defaults = [];

    /**
     * @var array
     */
    public static $usernameProperties = ['email'];

    /**
     * @var array
     */
    protected static $protectedFields = ['email', 'password'];

    /**
     * @var bool
     */
    protected $signedIn = false;

    /**
     * @var bool
     */
    protected $is2faVerified = false;

    /**
     * @var bool
     */
    protected $superUser = false;

    /**
     * @var bool
     */
    private $_changedPassword;

    /**
     * UserLink
     */
    private $_temporaryLink;

    protected function initialize()
    {
        parent::initialize();
        static::updated([get_called_class(), 'passwordChanged']);
    }

    protected function hasPermission($permission, Model $requester)
    {
        // always allow new user registrations
        if ($permission == 'create') {
            return true;
        }

        // users can only edit themselves
        if ($permission === 'edit' && $requester instanceof self && $requester->id() == $this->id()) {
            return true;
        }

        // otherwise, defer to admin permissions
        return $requester->isAdmin();
    }

    ///////////////////////////////
    // Hooks
    ///////////////////////////////

    protected function preSetHook(&$data)
    {
        // check if we are trying to edit any protected fields,
        // i.e. password or email address, that require the current
        // password to be changed
        $passwordRequired = false;

        foreach ($data as $key => $value) {
            if (static::hasProperty($key)) {
                if (in_array($key, static::$protectedFields)) {
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

        // verify the given current password, when required
        $app = $this->getApp();
        if ($passwordRequired && !$this->can('skip-password-required', $app['user'])) {
            $password = array_value($data, 'current_password');
            $strategy = $app['auth']->getStrategy('traditional');
            if (!$strategy->verifyPassword($this, $password)) {
                $this->getErrors()->add('invalid_password');

                return false;
            }
        }

        $this->_changedPassword = isset($data['password']) && !$this->isTemporary();

        return true;
    }

    /**
     * Sends the user a notification and records a security event
     * if the password was changed.
     *
     * @param ModelEvent $event
     */
    public static function passwordChanged(ModelEvent $event)
    {
        $user = $event->getModel();

        if (!$user->_changedPassword) {
            return;
        }

        $app = $user->getApp();
        $auth = $app['auth'];
        $req = $auth->getRequest();
        $ip = $req->ip();
        $userAgent = $req->agent();

        // record the reset password request event
        $event = new AccountSecurityEvent();
        $event->user_id = $user->id();
        $event->type = AccountSecurityEvent::CHANGE_PASSWORD;
        $event->ip = $ip;
        $event->user_agent = $userAgent;
        $event->save();

        // changing the password signs the user out, everywhere
        $auth->signOutAllSessions($user);
        $user->markSignedOut();

        if ($app['user'] && $app['user']->id() == $user->id()) {
            $auth->logout();
        }

        // send the user an email about it
        $user->sendEmail('password-changed', ['ip' => $ip]);
    }

    /////////////////////////////////////
    // UserInterface
    /////////////////////////////////////

    public function email()
    {
        return $this->email;
    }

    public function isTemporary()
    {
        if ($this->_temporaryLink) {
            return true;
        }

        return UserLink::where('user_id', $this->id())
            ->where('type', UserLink::TEMPORARY)
            ->count() > 0;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function enable()
    {
        $this->enabled = true;
        $result = $this->grantAllPermissions()->save();
        $this->enforcePermissions();

        return $result;
    }

    public function isVerified($withinTimeWindow = true)
    {
        $timeWindow = ($withinTimeWindow) ? time() - UserLink::$verifyTimeWindow : time();

        return UserLink::where('user_id', $this->id())
            ->where('type', UserLink::VERIFY_EMAIL)
            ->where('created_at', U::unixToDb($timeWindow), '<=')
            ->count() == 0;
    }

    public function isSignedIn()
    {
        return $this->signedIn;
    }

    public function markSignedIn()
    {
        $this->signedIn = true;

        return $this;
    }

    public function markSignedOut()
    {
        $this->signedIn = false;

        return $this;
    }

    public function isTwoFactorVerified()
    {
        return $this->is2faVerified;
    }

    public function markTwoFactorVerified()
    {
        $this->is2faVerified = true;

        return $this;
    }

    public function getHashedPassword()
    {
        if ($this->id() > 0) {
            return $this->ignoreUnsaved()->password;
        }

        return $this->password;
    }

    public function sendEmail($template, array $message = [])
    {
        $app = $this->getApp();

        $message['tags'] = array_merge(
            [$template],
            (array) array_value($message, 'tags'));
        $params = array_replace($this->getMailerParams(), $message);

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
        case 'invite':
            $params['subject'] = 'You have been invited to join '.$app['config']->get('app.title');
            $params['sign_up_link'] = false;
            if ($link = $this->getTemporaryLink()) {
                $params['sign_up_link'] = $link->url();
            }
        break;
        }

        $message = array_replace($params, $message);

        $app['mailer']->queueEmail($template, $message);

        return true;
    }

    /////////////////////////////////////
    // Getters
    /////////////////////////////////////

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

        return GroupMember::where('group', $group)
            ->where('user_id', $this->id())
            ->count() == 1;
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
        $hash = md5(strtolower(trim($this->email)));

        return "https://secure.gravatar.com/avatar/$hash?s=$size&d=mm";
    }

    ///////////////////////////////////
    // Super User Permissions
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

    ///////////////////////////////////
    // Registration
    ///////////////////////////////////

    /**
     * Sets the temporary link attached to the user.
     *
     * @param UserLink $link
     *
     * @return $this
     */
    function setTemporaryLink(UserLink $link)
    {
        $this->_temporaryLink = $link;
        return $this;
    }

    /**
     * Clears the temporary link attached to the user.
     *
     * @return $this
     */
    function clearTemporaryLink()
    {
        $this->_temporaryLink = null;
        return $this;
    }

    /**
     * Gets the temporary link from user registration.
     *
     * @return UserLink|null
     */
    function getTemporaryLink()
    {
        return $this->_temporaryLink;
    }

    ///////////////////////////////////
    // Utilities
    ///////////////////////////////////

    /**
     * Gets the mailer parameters when sending email to this user.
     *
     * @return array
     */
    protected function getMailerParams()
    {
        $app = $this->getApp();

        return [
            'base_url' => $app['base_url'],
            'siteEmail' => $app['config']->get('app.email'),
            'email' => $this->email,
            'username' => $this->name(true),
            'to' => [
                [
                    'email' => $this->email,
                    'name' => $this->name(true),
                ],
            ],
        ];
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
        if ($this->id() <= 0) {
            return false;
        }

        if ($this->isAdmin()) {
            return false;
        }

        // the current user can only delete their own account
        $app = $this->getApp();
        if ($app['user']->id() != $this->id()) {
            return false;
        }

        // Verify the supplied the password.
        $verified = $app['auth']->getStrategy('traditional')
                                ->verifyPassword($this, $password);
        if (!$verified) {
            return false;
        }

        return $this->grantAllPermissions()->delete();
    }
}
