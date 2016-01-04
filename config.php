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
  'dirs' => [
    'views' => __DIR__.'/tests/views',
  ],
  'services' => [
    'auth' => 'App\Auth\Services\Auth',
    'db' => 'Infuse\Services\Database',
    'mailer' => 'App\Email\MailerService',
    'model_driver' => 'Infuse\Services\ModelDriver',
    'pdo' => 'Infuse\Services\Pdo',
    'queue_driver' => 'Infuse\Services\QueueDriver',
  ],
  'models' => [
    'driver' => 'Pulsar\Driver\DatabaseDriver',
  ],
  'queue' => [
    'driver' => 'Infuse\Queue\Driver\SynchronousDriver',
  ],
  'modules' => [
    'middleware' => [
      'auth',
      'email',
    ],
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
];
