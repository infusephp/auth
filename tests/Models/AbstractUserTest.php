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
use Mockery\Adapter\Phpunit\MockeryTestCase;

class AbstractUserTest extends MockeryTestCase
{
    public static $user;
    public static $user2;
    public static $ogUserId;

    public static function setUpBeforeClass()
    {
        self::$ogUserId = Test::$app['user']->id();

        $db = Test::$app['database']->getDefault();
        $db->delete('Users')
            ->where('email', ['test@example.com', 'test2@example.com', 'test3@example.com'])
            ->execute();

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

    function testCreateFail()
    {
        $user = new User();
        $this->assertFalse($user->save());
    }

    function testCreate()
    {
        self::$user = new User();
        $this->assertTrue(self::$user->create([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]));

        self::$user2 = new User();
        $this->assertTrue(self::$user2->create([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test2@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]));
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
     * @depends testCreate
     */
    public function testEdit()
    {
        self::$user->ip = '127.0.0.2';
        $this->assertTrue(self::$user->save());
    }

    /**
     * @depends testCreate
     */
    public function testEditProtectedFieldFail()
    {
        Test::$app['auth']->signInUser(self::$user);
        $this->assertFalse(self::$user->set(['password' => 'testpassword2', 'email' => '']));
    }

    /**
     * @depends testCreate
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
        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.change_password')
            ->count();
        $this->assertEquals(1, $n);

        // should sign user out everywhere
        $n = ActiveSession::where('id', 'sesh_1234')
            ->where('valid', false)
            ->count();
        $this->assertEquals(1, $n);

        $n = PersistentSession::where('user_id', self::$user->id())->count();
        $this->assertEquals(0, $n);

        $this->assertEquals(-1, Test::$app['user']->id());
        $this->assertFalse(self::$user->isSignedIn());
    }

    /**
     * @depends testCreate
     */
    public function testEditPasswordAdmin()
    {
        Test::$app['user']->promoteToSuperUser();

        $this->assertTrue(self::$user->set([
            'password' => 'testpassword',
            'email' => '', ]));
    }

    /**
     * @depends testCreate
     */
    public function testName()
    {
        $this->assertEquals('Bob', self::$user->name());
    }

    /**
     * @depends testCreate
     */
    public function testIsTemporary()
    {
        $this->assertFalse(self::$user->isTemporary());
    }

    /**
     * @depends testCreate
     */
    public function testIsVerified()
    {
        $link = new UserLink();
        $link->user_id = self::$user->id();
        $link->type = UserLink::VERIFY_EMAIL;
        $link->saveOrFail();

        $this->assertFalse(self::$user->isVerified(false));
        $this->assertTrue(self::$user->isVerified(true));
        $this->assertTrue(self::$user2->isVerified());

        $link->delete();

        $this->assertTrue(self::$user->isVerified());
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
     * @depends testCreate
     */
    public function testIsAdmin()
    {
        $this->assertFalse(self::$user->isAdmin());
    }

    /**
     * @depends testCreate
     */
    public function testGroups()
    {
        $this->assertEquals(['everyone'], self::$user->groups());
    }

    /**
     * @depends testCreate
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
     * @depends testCreate
     */
    public function testProfilePicture()
    {
        $this->assertEquals('https://secure.gravatar.com/avatar/55502f40dc8b7c769880b10874abc9d0?s=200&d=mm', self::$user->profilePicture());
    }

    /**
     * @depends testCreate
     */
    public function testSendEmail()
    {
        $this->assertTrue(self::$user->sendEmail('welcome'));
        $this->assertTrue(self::$user->sendEmail('verify-email', ['verify' => 'test']));
        $this->assertTrue(self::$user->sendEmail('forgot-password', ['forgot' => 'test', 'ip' => 'test']));
    }

    /**
     * @depends testCreate
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
}
