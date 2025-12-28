<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'dsn' => env('RABBITMQ_DSN', 'amqp://guest:guest@rabbitmq:5672/%2f'),
            'factory_class' => Enqueue\AmqpLib\AmqpConnectionFactory::class,
            'host' => env('RABBITMQ_HOST', 'rabbitmq'),
            'port' => env('RABBITMQ_PORT', 5672),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'login' => env('RABBITMQ_LOGIN', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'options' => [
                'exchange' => [
                    'name' => 'posts_exchange',
                    'type' => 'topic',
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                ],
                'queues' => [
                    'posts_feed' => [
                        'name' => 'posts_feed_queue',
                        'passive' => false,
                        'durable' => true,
                        'exclusive' => false,
                        'auto_delete' => false,
                        'routing_keys' => ['posts.feed.*'],
                    ],
                ],
            ],
        ],
    ],

    'failed' => [
        'driver' => 'database-uuids',
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
