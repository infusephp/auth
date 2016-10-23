<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

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
    'auth' => 'Infuse\Auth\Services\Auth',
    'db' => 'JAQB\Services\Database',
    'errors' => 'Infuse\Auth\Services\ErrorStack',
    'mailer' => 'Infuse\Email\MailerService',
    'model_driver' => 'Infuse\Auth\Services\ModelDriver',
    'pdo' => 'Infuse\Services\Pdo',
    'queue_driver' => 'Infuse\Services\QueueDriver',
  ],
  'models' => [
    'driver' => 'Pulsar\Driver\DatabaseDriver',
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
