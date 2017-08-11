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
use Infuse\Auth\Exception\AuthException;
use Infuse\Auth\Libs\AuthManager;
use Infuse\Auth\Libs\UserInvites;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;
use PHPUnit\Framework\TestCase;

class UserInvitesTest extends TestCase
{
    public static $user;

    public static function setUpBeforeClass()
    {
        Test::$app['database']->getDefault()
            ->delete('Users')
            ->where('email', 'test@example.com')
            ->execute();
    }

    public static function tearDownAfterClass()
    {
        if (self::$user) {
            self::$user->grantAllPermissions()->delete();
        }
    }

    function testInvite()
    {
        $invites = $this->getUserInvites();
        $user = $invites->invite('test@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->isTemporary());
    }

    function testInviteExistingUser()
    {
        Test::$app['database']->getDefault()
            ->delete('UserLinks')
            ->execute();

        $invites = $this->getUserInvites();
        $user = $invites->invite('test@example.com');

        $this->assertInstanceOf(User::class, $user);
        self::$user = $user;
        $this->assertFalse($user->isTemporary());
        $this->assertEquals(self::$user->id(), $user->id());
    }

    function testInviteFail()
    {
        $this->expectException(AuthException::class);

        $invites = $this->getUserInvites();
        $invites->invite('', []);
    }

    private function getUserInvites()
    {
        $auth = new AuthManager();
        $auth->setApp(Test::$app)
            ->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
            ->setResponse(new Response());

        return new UserInvites($auth);
    }
}