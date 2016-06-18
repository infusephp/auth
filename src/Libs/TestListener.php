<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */
namespace App\Auth\Libs;

use Exception;
use Infuse\Test;
use PHPUnit_Framework_AssertionFailedError;
use PHPUnit_Framework_Test;
use PHPUnit_Framework_TestListener;
use PHPUnit_Framework_TestSuite;

/**
 * @codeCoverageIgnore
 */
class TestListener implements PHPUnit_Framework_TestListener
{
    /**
     * @staticvar array
     */
    public static $userParams = [
        'user_email' => 'test@example.com',
        'user_password' => ['testpassword', 'testpassword'],
        'first_name' => 'Bob',
        'ip' => '127.0.0.1',
    ];

    public function __construct()
    {
        /* Set up a test user */

        $userModel = Test::$app['auth']->getUserClass();
        $user = new $userModel();

        $params = self::$userParams;
        if (property_exists($user, 'testUser')) {
            $params = array_replace($params, $user::$testUser);
        }

        /* Delete any existing test users */

        $existingUser = $userModel::where('user_email', $params['user_email'])
            ->first();
        if ($existingUser) {
            $existingUser->grantAllPermissions()->delete();
        }

        /* Create and sign in the test user */

        $user->create($params);
        Test::$app['user'] = new $userModel($user->id(), true);
    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function addRiskyTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function startTest(PHPUnit_Framework_Test $test)
    {
        Test::$app['user']->demoteToNormalUser();
    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
    }

    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
    }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
    }
}
