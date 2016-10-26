<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use App\Users\Models\User;
use Infuse\Auth\Libs\Auth;
use Infuse\Auth\Models\AccountSecurityEvent;
use Infuse\Auth\Models\ActiveSession;
use Infuse\Auth\Models\GroupMember;
use Infuse\Auth\Models\PersistentSession;
use Infuse\Auth\Models\UserLink;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class AbstractUserTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $user2;
    public static $ogUserId;

    public static function setUpBeforeClass()
    {
        self::$ogUserId = Test::$app['user']->id();

        $db = Test::$app['db'];
        foreach (['test@example.com', 'test2@example.com', 'test3@example.com'] as $email) {
            $db->delete('Users')
                ->where('email', $email)
                ->execute();
        }

        Test::$app['auth']->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
            ->setResponse(new Response());
    }

    public static function tearDownAfterClass()
    {
        foreach ([self::$user, self::$user2] as $u) {
            if ($u) {
                $u->grantAllPermissions();
                $u->delete();
            }
        }
    }

    public function assertPostConditions()
    {
        $app = Test::$app;
        if ($app['user']->id() != self::$ogUserId) {
            $app['user'] = new User(self::$ogUserId);
            $app['user']->markSignedIn();
        }
    }

    public function testRegisterUserFail()
    {
        $this->assertFalse(User::registerUser([]));
    }

    public function testRegisterUser()
    {
        self::$user = User::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);

        $this->assertInstanceOf('App\Users\Models\User', self::$user);
        $this->assertGreaterThan(0, self::$user->id());

        self::$user2 = User::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test2@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ], true);

        $this->assertInstanceOf('App\Users\Models\User', self::$user2);
        $this->assertGreaterThan(0, self::$user2->id());
    }

    public function testPermissions()
    {
        $testUser = Test::$app['user'];

        $user = new User(-1);
        $this->assertTrue($user->can('create', $user));
        $this->assertTrue($user->can('edit', $user));
        $this->assertFalse($user->can('skip-password-required', $user));

        $this->assertTrue($user->can('create', $testUser));
        $this->assertFalse($user->can('edit', $testUser));
        $this->assertFalse($user->can('skip-password-required', $testUser));

        $this->assertFalse($user->can('whatever', $testUser));
    }

    /**
     * @depends testRegisterUser
     */
    public function testEdit()
    {
        self::$user->ip = '127.0.0.2';
        $this->assertTrue(self::$user->save());
    }

    /**
     * @depends testRegisterUser
     */
    public function testEditProtectedFieldFail()
    {
        Test::$app['auth']->signInUser(self::$user);
        $this->assertFalse(self::$user->set(['password' => 'testpassword2', 'email' => '']));
    }

    /**
     * @depends testRegisterUser
     */
    public function testEditPassword()
    {
        Test::$app['auth']->signInUser(self::$user);

        // create sessions
        $session = new ActiveSession();
        $session->id = 'sesh_1234';
        $session->user_id = self::$user->id();
        $session->ip = '127.0.0.1';
        $session->user_agent = 'Firefox';
        $session->expires = strtotime('+1 month');
        $this->assertTrue($session->save());

        $persistent = new PersistentSession();
        $persistent->user_id = self::$user->id();
        $persistent->series = self::$user->email;
        $persistent->series = str_repeat('a', 128);
        $persistent->token = str_repeat('a', 128);
        $this->assertTrue($persistent->save());

        Test::$app['auth']->signInUser(self::$user);

        // change the password
        $this->assertTrue(self::$user->set([
            'current_password' => 'testpassword',
            'password' => 'testpassword2',
            'email' => '', ]));

        // should create a security event
        $this->assertEquals(1, AccountSecurityEvent::totalRecords([
            'user_id' => self::$user->id(),
            'type' => 'user.change_password', ]));

        // should sign user out everywhere
        $this->assertEquals(1, ActiveSession::totalRecords([
            'id' => 'sesh_1234',
            'valid' => false,
        ]));

        $this->assertEquals(0, PersistentSession::totalRecords([
            'user_id' => self::$user->id(),
        ]));

        $this->assertEquals(-1, Test::$app['user']->id());
        $this->assertFalse(self::$user->isSignedIn());
    }

    /**
     * @depends testRegisterUser
     */
    public function testEditPasswordAdmin()
    {
        Test::$app['user']->promoteToSuperUser();

        $this->assertTrue(self::$user->set([
            'password' => 'testpassword',
            'email' => '', ]));
    }

    /**
     * @depends testRegisterUser
     */
    public function testName()
    {
        $this->assertEquals('Bob', self::$user->name());
    }

    /**
     * @depends testRegisterUser
     */
    public function testIsTemporary()
    {
        $this->assertFalse(self::$user->isTemporary());
    }

    /**
     * @depends testRegisterUser
     */
    public function testIsVerified()
    {
        $this->assertFalse(self::$user->isVerified(false));
        $this->assertTrue(self::$user->isVerified(true));

        $link = UserLink::where('user_id', self::$user->id())
            ->where('type', UserLink::VERIFY_EMAIL)
            ->first()
            ->delete();

        $this->assertTrue(self::$user->isVerified());
        $this->assertTrue(self::$user2->isVerified());
    }

    public function testIsSignedIn()
    {
        $user = new User(10);
        $this->assertFalse($user->isSignedIn());
        $this->assertEquals($user, $user->markSignedIn());
        $this->assertTrue($user->isSignedIn());
        $this->assertEquals($user, $user->markSignedOut());
        $this->assertFalse($user->isSignedIn());
    }

    public function testIsTwoFactorVerified()
    {
        $user = new User(10);
        $this->assertFalse($user->isTwoFactorVerified());
        $this->assertEquals($user, $user->markTwoFactorVerified());
        $this->assertTrue($user->isTwoFactorVerified());
    }

    /**
     * @depends testRegisterUser
     */
    public function testIsAdmin()
    {
        $this->assertFalse(self::$user->isAdmin());
    }

    /**
     * @depends testRegisterUser
     */
    public function testGroups()
    {
        $this->assertEquals(['everyone'], self::$user->groups());
    }

    /**
     * @depends testRegisterUser
     */
    public function testIsMemberOf()
    {
        $this->assertTrue(self::$user->isMemberOf('everyone'));
        $member = new GroupMember();
        $member->create(['user_id' => self::$user->id(), 'group' => 'group']);
        $this->assertTrue(self::$user->isMemberOf('group'));
        $this->assertFalse(self::$user->isMemberOf('random'));
    }

    /**
     * @depends testRegisterUser
     */
    public function testProfilePicture()
    {
        $this->assertEquals('https://secure.gravatar.com/avatar/55502f40dc8b7c769880b10874abc9d0?s=200&d=mm', self::$user->profilePicture());
    }

    /**
     * @depends testRegisterUser
     */
    public function testSendEmail()
    {
        $this->assertTrue(self::$user->sendEmail('welcome'));
        $this->assertTrue(self::$user->sendEmail('verify-email', ['verify' => 'test']));
        $this->assertTrue(self::$user->sendEmail('forgot-password', ['forgot' => 'test', 'ip' => 'test']));
    }

    /**
     * @depends testRegisterUser
     */
    public function testDeleteConfirm()
    {
        $this->assertFalse(self::$user->deleteConfirm('testpassword'));

        Test::$app['auth']->signInUser(self::$user);

        $this->assertTrue(self::$user->deleteConfirm('testpassword'));
    }

    public function testIsAdminSuperUser()
    {
        $user = new User(-20);
        $this->assertFalse($user->isAdmin());

        $user->promoteToSuperUser();
        $this->assertTrue($user->isAdmin());

        $user->demoteToNormalUser();
        $this->assertFalse($user->isAdmin());
    }

    public function testRegisterUserTemporary()
    {
        $this->assertFalse(User::createTemporary([]));

        $this->assertFalse(User::getTemporaryUser('test@example.com'));

        self::$user = User::createTemporary([
            'email' => 'test3@example.com',
            'password' => '',
            'first_name' => '',
            'last_name' => '',
            'ip' => '',
            'enabled' => true,
        ]);

        $this->assertInstanceOf('App\Users\Models\User', self::$user);
        $this->assertTrue(self::$user->isTemporary());

        $user = User::getTemporaryUser('test3@example.com');
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());

        $upgradedUser = User::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test3@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);

        $this->assertInstanceOf('App\Users\Models\User', $upgradedUser);
        $this->assertEquals(self::$user->id(), $upgradedUser->id());
        self::$user->load();
        $this->assertFalse(self::$user->isTemporary());
    }

    public function testUpgradeTemporaryAccountFail()
    {
        $this->setExpectedException('InvalidArgumentException');

        self::$user->upgradeTemporaryAccount([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1', ]);
    }
}
