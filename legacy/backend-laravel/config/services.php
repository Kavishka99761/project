<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    | Placeholders for optional external services. None are required to run the
    | EDU-SMART API; they exist so the framework's mail/notification drivers can
    | be configured later without touching code.
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sentence_transformers' => [
        'url' => env('SENTENCE_TRANSFORMERS_URL', 'http://127.0.0.1:8001'),
        'timeout' => env('SENTENCE_TRANSFORMERS_TIMEOUT', 60),
    ],

];
