<?php

return  [
  'site' => [
    'salt' => 'replacewithrandomstring',
  ],
  'modules' => [
    'middleware' => [
      'auth',
      'email'
    ],
    'all' => [
    	'auth'
    ]
  ],
  'database' => [
    'type' => 'mysql',
    'user' => 'travis',
    'password' => '',
    'host' => '127.0.0.1',
    'name' => 'mydb',
  ],
  'sessions' => [
    'enabled' => true,
    'adapter' => 'database',
    'lifetime' => 86400
  ],
];