<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

use JAQB\Services\Database;
use Pulsar\Driver\DatabaseDriver;
use Pulsar\Services\ErrorStack;
use Pulsar\Services\ModelDriver;

/* This configuration is used to run the tests */

return  [
  'app' => [
    'salt' => 'replacewithrandomstring',
  ],
  'auth' => [
    'storage' => 'Infuse\Auth\Libs\Storage\InMemoryStorage',
  ],
  'dirs' => [
    'views' => __DIR__.'/tests/views',
  ],
  'services' => [
    'db' => Database::class,
    'errors' => ErrorStack::class,
    'model_driver' => ModelDriver::class,
    'auth' => 'Infuse\Auth\Services\Auth',
    'mailer' => 'Infuse\Email\MailerService',
    'pdo' => 'Infuse\Services\Pdo',
    'queue_driver' => 'Infuse\Services\QueueDriver',
  ],
  'models' => [
    'driver' => DatabaseDriver::class,
  ],
  'queue' => [
    'driver' => 'Infuse\Queue\Driver\SynchronousDriver',
  ],
  'database' => [
    'type' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'mydb',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8',
  ],
  'sessions' => [
    'enabled' => true,
  ],
  'email' => [
    'type' => 'nop',
  ],
];
