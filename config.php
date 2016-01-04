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
  'services' => [
    'auth' => 'App\Auth\Services\Auth',
    'db' => 'Infuse\Services\Database',
    'model_driver' => 'Infuse\Services\ModelDriver',
    'pdo' => 'Infuse\Services\Pdo',
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
