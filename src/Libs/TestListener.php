<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Infuse\Auth\Libs;

use Exception;
use Infuse\Test;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test as PHPUnitTest;
use PHPUnit\Framework\TestListener as PHPUnitTestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

/**
 * @codeCoverageIgnore
 */
class TestListener implements PHPUnitTestListener
{
    /**
     * @var array
     */
    public static $userParams = [
        'email' => 'test@example.com',
        'password' => ['testpassword', 'testpassword'],
        'first_name' => 'Bob',
        'ip' => '127.0.0.1',
    ];

    public function __construct()
    {
        /* Set up a test user */

        $userClass = Test::$app['auth']->getUserClass();
        $user = new $userClass();

        $params = self::$userParams;
        if (property_exists($user, 'testUser')) {
            $params = array_replace($params, $user::$testUser);
        }

        /* Delete any existing test users */

        $existingUser = $userClass::where('email', $params['email'])
            ->first();
        if ($existingUser) {
            $existingUser->grantAllPermissions()->delete();
        }

        /* Create and sign in the test user */

        $user->create($params);
        $user->markSignedIn();
        Test::$app['user'] = $user;
    }

    public function addError(PHPUnitTest $test, Exception $e, $time)
    {
    }

    public function addFailure(PHPUnitTest $test, AssertionFailedError $e, $time)
    {
    }

    public function addIncompleteTest(PHPUnitTest $test, Exception $e, $time)
    {
    }

    public function addRiskyTest(PHPUnitTest $test, Exception $e, $time)
    {
    }

    public function addSkippedTest(PHPUnitTest $test, Exception $e, $time)
    {
    }

    public function addWarning(PHPUnitTest $test, Warning $e, $time)
    {
    }

    public function startTest(PHPUnitTest $test)
    {
        Test::$app['user']->demoteToNormalUser();
    }

    public function endTest(PHPUnitTest $test, $time)
    {
    }

    public function startTestSuite(TestSuite $suite)
    {
    }

    public function endTestSuite(TestSuite $suite)
    {
    }
}
