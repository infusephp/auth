<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @link http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

use Infuse\Auth\Libs\Storage\InMemoryStorage;
use Infuse\Auth\Services\Auth;
use Infuse\Email\MailerService;
use Infuse\Queue\Driver\SynchronousDriver;
use Infuse\Services\QueueDriver;
use Infuse\Services\Redis;
use JAQB\Services\ConnectionManager;
use Pulsar\Driver\DatabaseDriver;
use Pulsar\Services\ErrorStack;
use Pulsar\Services\ModelDriver;

/* This configuration is used to run the tests */

return  [
    'app' => [
        'salt' => 'replacewithrandomstring',
    ],
    'auth' => [
        'storage' => InMemoryStorage::class,
    ],
    'dirs' => [
        'views' => __DIR__.'/tests/views',
    ],
    'services' => [
        'database' => ConnectionManager::class,
        'model_driver' => ModelDriver::class,
        'auth' => Auth::class,
        'mailer' => MailerService::class,
        'queue_driver' => QueueDriver::class,
        'redis' => Redis::class
    ],
    'models' => [
        'driver' => DatabaseDriver::class,
    ],
    'queue' => [
        'driver' => SynchronousDriver::class,
    ],
    'database' => [
        'test' => [
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'mydb',
            'user' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        ],
    ],
    'sessions' => [
        'enabled' => true,
    ],
    'email' => [
        'type' => 'nop',
    ],
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379
    ],
    'cache' => [
        'namespace' => 'authtest'
    ]
];
