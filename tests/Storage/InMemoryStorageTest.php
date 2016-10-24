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
use Infuse\Auth\Libs\Storage\InMemoryStorage;
use Infuse\Request;
use Infuse\Response;
use Infuse\Test;

class InMemoryStorageTest extends PHPUnit_Framework_TestCase
{
    public function testStorage()
    {
        $storage = new InMemoryStorage(Test::$app['auth']);

        $req = new Request();
        $res = new Response();

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));

        $user = new User(10);
        $this->assertTrue($storage->signIn($user, $req, $res));

        $this->assertTrue($storage->twoFactorVerified($user, $req, $res));

        $user2 = $storage->getAuthenticatedUser($req, $res);
        $this->assertEquals($user, $user2);

        $this->assertTrue($storage->remember($user, $req, $res));

        $this->assertTrue($storage->signOut($req, $res));

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }
}
