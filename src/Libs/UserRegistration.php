<?php


namespace Infuse\Auth\Libs;
use Infuse\Auth\Exception\AuthException;
use Infuse\Auth\Interfaces\UserInterface;
use Infuse\Auth\Models\UserLink;
use Infuse\Utility;

/**
 * Handles user registration.
 */
class UserRegistration
{
    /**
     * @var AuthManager
     */
    private $auth;

    /**
     * @param AuthManager $auth
     */
    public function __construct(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Registers a new user.
     *
     * @param array $values values to set on user account
     * @param bool  $verifiedEmail true if the email has been verified
     *
     * @throws AuthException when the user cannot be created.
     *
     * @return UserInterface newly created user
     */
    public function registerUser(array $values, $verifiedEmail = false)
    {
        $email = array_value($values, 'email');
        $tempUser = $this->getTemporaryUser($email);

        // upgrade temporary account
        if ($tempUser && $this->upgradeTemporaryUser($tempUser, $values)) {
            return $tempUser;
        }

        $userClass = $this->auth->getUserClass();
        $user = new $userClass();

        if (!$user->create($values)) {
            throw new AuthException('Could not create user account: '.implode(', ', $user->getErrors()->all()));
        }

        if (!$verifiedEmail) {
            $this->auth->sendVerificationEmail($user);
        } else {
            // send the user a welcome message
            $user->sendEmail('welcome');
        }

        return $user;
    }

    /**
     * Gets a temporary user from an email address if one exists.
     *
     * @param string $email email address
     *
     * @return UserInterface|false
     */
    public function getTemporaryUser($email)
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $userClass = $this->auth->getUserClass();
        $user = $userClass::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        if (!$user->isTemporary()) {
            return false;
        }

        return $user;
    }

    /**
     * Creates a temporary user. Useful for creating invites.
     *
     * @param array $parameters user data
     *
     * @throws AuthException when the user cannot be created.
     *
     * @return UserInterface temporary user
     */
    public function createTemporaryUser($parameters)
    {
        $email = trim(strtolower(array_value($parameters, 'email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Invalid email address');
        }

        $insertArray = array_replace($parameters, ['enabled' => false]);

        // create the temporary user
        $userClass = $this->auth->getUserClass();
        $user = new $userClass();
        $driver = $userClass::getDriver();
        $created = $driver->createModel($user, $insertArray);

        if (!$created) {
            throw new AuthException('Could not create temporary user');
        }

        // get the new user ID
        $id = [];
        foreach ($userClass::getIDProperties() as $k) {
            $id[] = $driver->getCreatedID($user, $k);
        }

        $user = $userClass::find($id);

        // create the temporary link
        $link = new UserLink();
        $link->user_id = $user->id();
        $link->type = UserLink::TEMPORARY;
        $link->saveOrFail();
        $user->setTemporaryLink($link);

        return $user;
    }

    /**
     * Upgrades the user from temporary to a fully registered account.
     *
     * @param UserInterface $user
     * @param array $values properties to set on user model
     *
     * @throws AuthException when the upgrade fails.
     *
     * @return $this
     */
    public function upgradeTemporaryUser(UserInterface $user, $values = [])
    {
        if (!$user->isTemporary()) {
            throw new AuthException('Cannot upgrade a non-temporary account');
        }

        $values = array_replace($values, [
            'created_at' => Utility::unixToDb(time()),
            'enabled' => true,
        ]);

        $user->grantAllPermissions();

        if (!$user->set($values)) {
            $user->enforcePermissions();

            throw new AuthException('Could not upgrade temporary account: '.implode($user->getErrors()->all()));
        }

        // remove temporary and unverified links
        $app = $user->getApp();
        $app['database']->getDefault()
            ->delete('UserLinks')
            ->where('user_id', $user->id())
            ->where(function ($query) {
                return $query->where('type', UserLink::TEMPORARY)
                    ->orWhere('type', UserLink::VERIFY_EMAIL);
            })
            ->execute();
        $user->clearTemporaryLink();

        // send the user a welcome message
        $user->sendEmail('welcome');

        $user->enforcePermissions();

        return $this;
    }
}