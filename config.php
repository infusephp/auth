<?php

/* This configuration is used to run the tests */

return  [
  'site' => [
    'salt' => 'replacewithrandomstring',
  ],
  'modules' => [
    'middleware' => [
      'auth',
      'email'
    ]
  ],
  'database' => [
    'type' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'mydb',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8'
  ],
  'sessions' => [
    'enabled' => true,
    'adapter' => 'database',
    'lifetime' => 86400
  ],
];
