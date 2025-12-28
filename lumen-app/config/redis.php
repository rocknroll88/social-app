<?php

use Illuminate\Support\Str;

return [
    'client' => env('REDIS_CLIENT', 'predis'),

    'default' => env('REDIS_CLUSTER', 'default'),

    'connections' => [
        'default' => [
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],
    ],
];

