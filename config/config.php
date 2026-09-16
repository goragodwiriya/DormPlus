<?php

declare (strict_types = 1);

return [
    'app' => [
        'name' => 'DormPlus',
        'environment' => getenv('APP_ENV') ?: 'development',
        'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
        'timezone' => 'Asia/Bangkok',
        'base_url' => getenv('APP_URL') ?: 'http://localhost:8000'
    ],

    'database' => [
        'path' => getenv('DB_PATH')
            ?: dirname(__DIR__).'/database/database.sqlite'
    ],

    'session' => [
        'name' => 'dormplus_session',
        'lifetime' => 7200,
        'secure' => filter_var(getenv('SESSION_SECURE') ?: false, FILTER_VALIDATE_BOOL),
        'same_site' => 'Lax'
    ],

    'cors' => [
        'allowed_origins' => array_filter([
            getenv('APP_URL') ?: 'http://localhost:8000'
        ])
    ],

    'uploads' => [
        'directory' => dirname(__DIR__).'/uploads',
        'max_size' => 5 * 1024 * 1024,
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp'
        ]
    ],

    'contracts' => [
        'expiration_warning_days' => 30
    ]
];