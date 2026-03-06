<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
    //    'https://adminli.kuydinas.id',
    //     'https://userli.kuydinas.id',
    //     'https://kuydinasadminv5.vercel.app',
    //     'https://adminvercel.kuydinas.id',
        'http://127.0.0.1:5173',

        
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];