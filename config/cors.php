<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_unique([
        env('FRONTEND_URL'),
        env('ADMIN_FRONTEND_URL'),
        'https://kuydinasclientv5.vercel.app',
        'https://kuydinas-admin-v5-five.vercel.app',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://localhost:5173',
        'https://kuymin.kuydinas.id',
        'https://tryout.kuydinas.id',
    ]), fn ($origin) => filled($origin))),

    'allowed_origins_patterns' => [
        '/^https?:\/\/localhost(?::\d+)?$/',
        '/^https?:\/\/127\.0\.0\.1(?::\d+)?$/',
        '/^https?:\/\/(?:[a-z0-9-]+\.)?kuydinas\.id$/i',
        '/^https:\/\/.*\.vercel\.app$/i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
