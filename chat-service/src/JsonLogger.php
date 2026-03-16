<?php

declare(strict_types=1);

namespace ChatService;

class JsonLogger
{
    public function __construct(
        private readonly string $serviceName
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
