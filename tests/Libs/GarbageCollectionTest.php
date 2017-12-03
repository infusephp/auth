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
use Mockery\Adapter\Phpunit\MockeryTestCase;

class GarbageCollectionTest extends MockeryTestCase
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
