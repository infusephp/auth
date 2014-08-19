<?php

return  [
  'site' => [
    'salt' => 'replacewithrandomstring',
  ],
  'modules' => [
    'middleware' => [
      'auth'
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
    'enabled' => false,
    'adapter' => 'database',
    'lifetime' => 86400
  ],
];