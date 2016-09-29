<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
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

        $this->assertTrue($storage->signIn(10, $req, $res));

        $user = $storage->getAuthenticatedUser($req, $res);
        $this->assertInstanceOf('App\Users\Models\User', $user);
        $this->assertEquals(10, $user->id());

        $this->assertTrue($storage->remember($user, $req, $res));

        $this->assertTrue($storage->signOut($req, $res));

        $this->assertFalse($storage->getAuthenticatedUser($req, $res));
    }
}
