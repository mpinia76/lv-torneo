<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Google Analytics 4. Sin GA4_ID no se carga nada (local, pruebas).
    'google_analytics' => [
        'id' => env('GA4_ID'),
    ],

    // Traducción de la historia de los clubes (App\Services\Traductor).
    // Sin clave de DeepL se prueba Google y después MyMemory (ver el servicio).
    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
    ],

    // Opcional: con un mail, MyMemory sube el tope de 5.000 a 50.000 caracteres por día.
    'mymemory' => [
        'email' => env('MYMEMORY_EMAIL'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
