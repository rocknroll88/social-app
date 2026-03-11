<?php

return [
    'storage' => env('DIALOG_STORAGE', 'sql'),
    'redis_prefix' => env('DIALOG_REDIS_PREFIX', 'dialog'),
    'redis_message_prefix' => env('DIALOG_REDIS_MESSAGE_PREFIX', 'dialog:message:'),
    'recipient_cache_ttl_sec' => (int) env('DIALOG_RECIPIENT_CACHE_TTL_SEC', 3600),
];
