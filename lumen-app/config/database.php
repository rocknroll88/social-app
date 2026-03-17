<?php

$masterHost = env('DB_MASTER_HOST', env('DB_HOST', '127.0.0.1'));
$readHosts = array_filter([
    env('DB_SLAVE1_HOST'),
    env('DB_SLAVE2_HOST'),
]);

if (filter_var(env('DB_READ_FALLBACK_TO_MASTER', true), FILTER_VALIDATE_BOOLEAN)) {
    $readHosts[] = $masterHost;
}

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver'   => 'pgsql',
            'write' => [
                'host' => [
                    $masterHost,
                ],
            ],
            'read' => [
                'host' => $readHosts,
            ],
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'social'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'secret'),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
        ],
    ],

    'migrations' => 'migrations',
];
