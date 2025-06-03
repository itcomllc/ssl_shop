<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    'square' => [
        'application_id' => env('SQUARE_APPLICATION_ID'),
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
        'ssl_plan_variation_id' => env('SQUARE_SSL_PLAN_VARIATION_ID'),
    ],

    'gogetssl' => [
        'username' => env('GOGETSSL_USERNAME'),
        'password' => env('GOGETSSL_PASSWORD'),
        'base_url' => env('GOGETSSL_BASE_URL', 'https://my.gogetssl.com/api'),
        'partner_code' => env('GOGETSSL_PARTNER_CODE'),
        'timeout' => env('GOGETSSL_TIMEOUT', 30),
        'sandbox' => env('GOGETSSL_SANDBOX', false),
    ],
];
