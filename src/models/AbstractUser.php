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
use infuse\Utility as U;
use infuse\Validate;

abstract class AbstractUser extends Model
{
    /////////////////////////////////////
    // Model Properties
    /////////////////////////////////////

    public static $scaffoldApi;
    public static $autoTimestamps;

    static $properties = [
        'uid' =>  [
            'type' => 'number',
            'mutable' => false,
            'admin_hidden_property' => true
        ],
        'user_email' => [
            'type' => 'string',
            'validate' => 'email',
            'required' => true,
            'unique' => true,
            'title' => 'E-mail',
            'admin_html' => '<a href="mailto:{user_email}">{user_email}</a>',
        ],
        'user_password' => [
            'type' => 'string',
            'validate' => 'matching|password:8',
            'required' => true,
            'title' => 'Password',
            'admin_type' => 'password'
        ],
        'first_name' => [
            'type' => 'string',
            'validate' => 'string:1'
        ],
        'last_name' => [
            'type' => 'string'
        ],
        'ip' => [
            'type' => 'string',
            'required' => true,
            'admin_html' => '<a href="http://www.infobyip.com/ip-{ip}.html" target="_blank">{ip}</a>'
        ],
        'enabled' => [
            'type' => 'boolean',
            'validate' => 'boolean',
            'required' => true,
            'default' => true
        ]
    ];

    public static $usernameProperties = [ 'user_email' ];

    /////////////////////////////////////
    // Protected Class Variables
    /////////////////////////////////////

    protected $logged_in = false;
    protected $isSu = false;
    protected $oldUid = false;
    protected static $protectedFields = [ 'user_email', 'user_password' ];

    /**
	 * Creates a new user
	 *
	 * @param int $id
	 * @param boolean $isLoggedIn when true, signifies that the user is logged in
	 */
    public function __construct($id = false, $isLoggedIn = false)
    {
        parent::__construct( $id );

        if( $isLoggedIn && $this->_id > 0 )
            $this->logged_in = true;
    }

    public static function idProperty()
    {
        return 'uid';
    }

    protected function hasPermission($permission, Model $requester)
    {
        // allow user registrations
        if( $permission == 'create' && !$requester->isLoggedIn() )

            return true;
        elseif( in_array( $permission, [ 'edit' ] ) && $requester->id() == $this->_id )
            return true;

        return $requester->isAdmin();
    }

    ///////////////////////////////
    // HOOKS
    ///////////////////////////////

    protected function preSetHook(&$data)
    {
        if( !is_array( $data ) )
            $data = [ $data => $value ];

        $params = [];
        $protectedFields = static::$protectedFields;

        // check if the current password is accurate
        $passwordValidated = false;

        $encryptedPassword = U::encrypt_password(
            U::array_value( $data, 'current_password' ),
            $this->app[ 'config' ]->get( 'site.salt' ) );

        if( $encryptedPassword == $this->user_password )
            $passwordValidated = true;

        $passwordRequired = false;

        foreach ($data as $key => $value) {
            if ( static::hasProperty( $key ) ) {
                if ( in_array( $key, $protectedFields ) ) {
                    // protected fields, i.e. passwords, are not allowed
                    // to be set to empty values
                    if ( strlen( implode( (array) $value ) ) == 0 ) {
                        unset( $data[ $key ] );
                        continue;
                    }

                    $passwordRequired = true;
                }

                $data[ $key ] = $value;
            }
        }

        if ( $passwordRequired && !$passwordValidated && !$this->can( 'skip-password-required', $this->app[ 'user' ] ) ) {
            $errorStack = $this->app[ 'errors' ];
            $errorStack->push( [ 'error' => 'invalid_password' ] );

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
            'UserLinks' ];

        foreach( $nuke as $tablename )
            Database::delete( $tablename, [ 'uid' => $this->_id ] );
    }

    /////////////////////////////////////
    // GETTERS
    /////////////////////////////////////

    /**
	* Get's the user's name.
	* WARNING this method should be overridden
	*
	* @param boolean $full get full name if true
	*
	* @return string name
	*/
    abstract public function name( $full = false );

    /**
	* Gets the temporary string if the user is temporary
	*
	* @return link|false true if temporary
	*/
    public function isTemporary()
    {
        return UserLink::totalRecords( [
            'uid' => $this->_id,
            'link_type' => USER_LINK_TEMPORARY ] ) > 0;
    }

    /**
	* Checks if the account has been verified
	*
	* @param boolean $withinTimeWindow when true, allows a time window before the account is considered unverified
	*
	* @return boolean
	*/
    public function isVerified($withinTimeWindow = true)
    {
        $timeWindow = ( $withinTimeWindow ) ? time() - UserLink::$verifyTimeWindow : time();

        return UserLink::totalRecords( [
            'uid' => $this->_id,
            'link_type' => USER_LINK_VERIFY_EMAIL,
            'created_at <= ' . $timeWindow ] ) == 0;
    }

    /**
	* Checks if the user is logged in
	* @return boolean true if logged in
	*/
    public function isLoggedIn()
    {
        return $this->logged_in;
    }

    /**
	* Checks if the user is an admin
	* @todo not implemented
	* @return boolean true if admin
	*/
    public function isAdmin()
    {
        return $this->isSu || $this->isMemberOf( 'admin' );
    }

    /**
	* Gets the groups this user is a member of
	*
	* @return array groups
	*/
    public function groups()
    {
        $return = [ 'everyone' ];

        $groups = GroupMember::find( [
            'where' => [
                'uid' => $this->_id ],
            'sort' => '`group ASC' ] );

        foreach( $groups[ 'models' ] as $group )
            $return[] = $groups->group;

        return $return;
    }

    /**
	 * Checks if the user is a member of a group
	 *
	 * @param string $group
	 *
	 * @return boolean true if member
	 */
    public function isMemberOf($group)
    {
        if( $group == 'everyone' )

            return true;

        return GroupMember::totalRecords( [
            'group' => $group,
            'uid' => $this->_id ] ) == 1;
    }

    /**
	 * Generates the URL for the user's profile picture
	 *
	 * Gravatar is used for profile pictures. To accomplish this we need to generate a hash of the user's e-mail.
	 *
	 * @param int $size size of the picture (it is square, usually)
	 *
	 * @return string url
	 */
    public function profilePicture($size = 200)
    {
        // use Gravatar
        $hash = md5( strtolower( trim( $this->user_email ) ) );

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
        if( $this->isSu )

            return;

        $this->isSu = true;
        $this->oldUid = $this->_id;
        $this->_id = SUPER_USER;
    }

    /**
	 * Removes super user permission.
	 */
    public function disableSU()
    {
        if( !$this->isSu )

            return;

        $this->isSu = false;
        $this->_id = $this->oldUid;
        $this->oldUid = false;
    }

    ///////////////////////////////////
    // ACCOUNT CREATION
    ///////////////////////////////////

    /**
	 * Registers a new user
	 *
	 * @param array $data user data
	 * @param boolean $verifiedEmail true if the e-mail has been verified
	 *
	 * @return boolean success
	 */
    public static function registerUser(array $data, $verifiedEmail = false)
    {
        $tempUser = self::$injectedApp[ 'auth' ]->getTemporaryUser( U::array_value( $data, 'user_email' ) );

        // upgrade temporary account
        if( $tempUser &&
            self::$injectedApp[ 'auth' ]->upgradeTemporaryAccount( $tempUser, $data ) )

            return $tempUser;

        $user = new static();

        if ( $user->create( $data ) ) {
            if( !$verifiedEmail )
                self::$injectedApp[ 'auth' ]->sendVerificationEmail( $user );
            else
                // send the user a welcome message
                $user->sendEmail( 'welcome' );

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
        $email = U::array_value( $data, 'user_email' );
        if( !Validate::is( $email, 'email' ) )

            return false;

        $insertArray = array_replace( $data, [
            'created_at' => time(),
            'enabled' => 0 ] );

        // create the temporary user
        if( !Database::insert( static::tablename(), $insertArray ) )

            return false;

        $user = new static( Database::lastInsertID() );

        // create the temporary link
        $link = new UserLink();
        $link->grantAllPermissions();
        $link->create( [
            'uid' => $user->id(),
            'link_type' => USER_LINK_TEMPORARY ] );

        return $user;
    }

    ///////////////////////////////////
    // UTILITIES
    ///////////////////////////////////

    /**
	 * Sends the user an e-mail
	 *
	 * @param string $template template name
	 * @param array $message message details
	 *
	 * @return boolean success
	 */
    public function sendEmail($template, $message = [])
    {
        $email = $this->user_email;

        $message[ 'base_url' ] = $this->app[ 'base_url' ];
        $message[ 'siteEmail' ] = $this->app[ 'config' ]->get( 'site.email' );
        $message[ 'email' ] = $email;
        $message[ 'username' ] = $this->name( true );
        $message[ 'to' ] = [
            [
                'email' => $email,
                'name' => $this->name( true ) ] ];

        switch ($template) {
        case 'welcome':
            $message[ 'subject' ] = 'Welcome to ' . $this->app[ 'config' ]->get( 'site.title' );
        break;
        case 'verify-email':
            $message[ 'subject' ] = 'Please verify your e-mail address';
            $message[ 'verify_link' ] = "{$message['base_url']}users/verifyEmail/{$message['verify']}";
        break;
        case 'forgot-password':
            $message[ 'subject' ] = 'Password change request on ' . $this->app[ 'config' ]->get( 'site.title' );
            $message[ 'forgot_link' ] = "{$message['base_url']}users/forgot/{$message['forgot']}";
        break;
        }

        return $this->app[ 'mailer' ]->queueEmail( $template, $message );
    }

    /**
	 * Deletes the user account permanently (DANGER!)
	 *
	 * @param string $password
	 *
	 * @return boolean success
	 */
    public function deleteConfirm($password)
    {
        $this->app[ 'errors' ]->setCurrentContext( 'user.delete' );

        // Check for the password.
        // Only the current user can delete their account using this method
        if( $this->exists() &&
            !$this->isAdmin() &&
            $this->app[ 'user' ]->id() == $this->_id &&
            U::encrypt_password( $password, $this->app[ 'config' ]->get( 'site.salt' ) ) == $this->user_password )
        {
            $this->grantAllPermissions();

            return $this->delete();
        }

        return false;
    }
}
