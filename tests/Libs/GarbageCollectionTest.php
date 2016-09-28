<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
use Infuse\Auth\Jobs\GarbageCollection;
use Infuse\Test;

class GarbageCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testRun()
    {
        $job = new GarbageCollection();
        $job->setApp(Test::$app);
        $this->assertTrue($job->run());

        $run = Mockery::mock();
        $this->assertTrue($job($run));
    }
}
