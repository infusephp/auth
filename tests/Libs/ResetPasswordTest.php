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
use Infuse\Auth\Libs\ResetPassword;
use Infuse\Auth\Models\UserLink;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class ResetPasswordTest extends PHPUnit_Framework_TestCase
{
    public static $user;
    public static $ogUserId;

    public static function setUpBeforeClass()
    {
        Test::$app['db']->delete('Users')
            ->where('email', 'test@example.com')
            ->execute();

        self::$user = User::registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test@example.com',
            'password' => ['testpassword', 'testpassword'],
            'ip' => '127.0.0.1',
        ]);

        self::$ogUserId = Test::$app['user']->id();
    }

    public static function tearDownAfterClass()
    {
        foreach ([self::$user] as $u) {
            if ($u) {
                $u->grantAllPermissions()->delete();
            }
        }
    }

    public function assertPreConditions()
    {
        $this->assertInstanceOf('App\Users\Models\User', self::$user);
    }

    public function assertPostConditions()
    {
        $app = Test::$app;
        if (!$app['user']->isSignedIn()) {
            $app['user'] = new User(self::$ogUserId, true);
        }
    }

    public function testBuildLink()
    {
        $sequence = $this->getSequence();
        $link = $sequence->buildLink(self::$user->id());
        $this->assertInstanceOf('Infuse\Auth\Models\UserLink', $link);
        $this->assertTrue($link->exists());
    }

    public function testBuildLinkFail()
    {
        $this->setExpectedException('Exception');
        $sequence = $this->getSequence();
        $sequence->buildLink(123412341234);
    }

    public function testGetUserFromTokenInvalid()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        $reset = $this->getSequence();
        $reset->getUserFromToken('blah');
    }

    public function testGetUserFromToken()
    {
        $reset = $this->getSequence();

        $link = $reset->buildLink(self::$user->id());

        $user = $reset->getUserFromToken($link->link);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    /**
     * @depends testGetUserFromToken
     */
    public function testGetUserFromTokenExpired()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        $reset = $this->getSequence();

        $link = $reset->buildLink(self::$user->id());
        $link->created_at = '-10 years';
        $this->assertTrue($link->save());

        $reset->getUserFromToken($link->link);
    }

    public function testStep1ValidationFailed()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid email address.');

        $reset = $this->getSequence();
        $reset->step1('invalidemail', '127.0.0.1');
    }

    public function testStep1NoEmailMatch()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'We could not find a match for that email address.');

        $reset = $this->getSequence();
        $reset->step1('nomatch@example.com', '127.0.0.1');
    }

    public function testStep1()
    {
        $reset = $this->getSequence();
        $this->assertTrue($reset->step1('test@example.com', '127.0.0.1'));
        $this->assertEquals(1, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));

        // repeat calls should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $this->assertTrue($reset->step1('test@example.com', '127.0.0.1'));
        }

        $this->assertEquals(1, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));
    }

    public function testStep2Invalid()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'This link has expired or is invalid.');

        $reset = $this->getSequence();
        $reset->step2('blah', ['password', 'password'], '127.0.0.1');
    }

    public function testStep2BadPassword()
    {
        $this->setExpectedException('Infuse\Auth\Exception\AuthException', 'Please enter a valid password.');

        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $reset = $this->getSequence();

        $link = $reset->buildLink(self::$user->id());

        $reset->step2($link->link, ['f', 'f'], '127.0.0.1');
    }

    public function testStep2()
    {
        Test::$app['db']->delete('UserLinks')
            ->where('link_type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user->id())
            ->execute();

        $reset = $this->getSequence();

        $link = $reset->buildLink(self::$user->id());

        $oldUserPassword = self::$user->password;
        $this->assertTrue($reset->step2($link->link, ['testpassword2', 'testpassword2'], '127.0.0.1'));
        self::$user->refresh();
        $this->assertNotEquals($oldUserPassword, self::$user->password);
        $this->assertEquals(0, UserLink::totalRecords([
            'link_type' => UserLink::FORGOT_PASSWORD,
            'user_id' => self::$user->id(), ]));
    }

    private function getSequence()
    {
        $auth = new Auth();
        $auth->setApp(Test::$app)
             ->setRequest(new Request([], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']))
             ->setResponse(new Response());

        return new ResetPassword($auth);
    }
}
