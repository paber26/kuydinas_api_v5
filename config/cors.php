<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://kuydinasclientv5.vercel.app',
        'https://kuydinas-admin-v5-five.vercel.app',
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'https://kuymin.kuydinas.id',
        'https://tryout.kuydinas.id',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];