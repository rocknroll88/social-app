<?php

return [
    'base_url' => env('CHAT_SERVICE_BASE_URL', 'http://chat-service:8081'),
    'timeout_sec' => (float) env('CHAT_SERVICE_TIMEOUT_SEC', 3.0),
];
