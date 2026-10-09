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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'mobile_money' => [
        'webhook_secret' => env('MOBILE_MONEY_WEBHOOK_SECRET'),
    ],

    // Agrégateur Mobile Money retenu (Jèko). En-têtes d'authentification
    // confirmés (X-API-KEY / X-API-KEY-ID) ; base_url, store_id et le
    // format exact des webhooks restent à documenter avant toute
    // implémentation réelle de JekoGateway.
    'jeko' => [
        'api_key' => env('JEKO_API_KEY'),
        'api_key_id' => env('JEKO_API_KEY_ID'),
        'store_id' => env('JEKO_STORE_ID'),
        'base_url' => env('JEKO_BASE_URL'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

];
