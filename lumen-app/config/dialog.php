<?php

return [
    'storage' => env('DIALOG_STORAGE', 'sql'),
    'redis_prefix' => env('DIALOG_REDIS_PREFIX', 'dialog'),
    'redis_message_prefix' => env('DIALOG_REDIS_MESSAGE_PREFIX', 'dialog:message:'),
    'counter_redis_prefix' => env('DIALOG_COUNTER_REDIS_PREFIX', 'dialog:counter'),
    'counter_cache_ttl_sec' => (int) env('DIALOG_COUNTER_CACHE_TTL_SEC', 3600),
    'recipient_cache_ttl_sec' => (int) env('DIALOG_RECIPIENT_CACHE_TTL_SEC', 3600),
];
