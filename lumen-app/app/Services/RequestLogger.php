<?php

namespace App\Services;

class RequestLogger
{
    public function __construct(
        private readonly string $serviceName = 'monolith'
    ) {
    }

    public function log(string $event, array $context = []): void
    {
        $payload = array_merge([
            'timestamp' => gmdate('c'),
            'service' => $this->serviceName,
            'event' => $event,
        ], $context);

        error_log((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
