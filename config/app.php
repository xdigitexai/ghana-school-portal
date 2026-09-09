<?php

return [
    'name' => env('APP_NAME', 'Ghana School Portal'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Africa/Accra',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_GH',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => ['driver' => 'file', 'store' => 'database'],
];
