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

    // Agrégateur Mobile Money retenu (Jèko). Voir App\Services\MobileMoney\JekoGateway
    // pour l'implémentation complète (création de paiement, vérification de
    // statut, vérification de signature webhook).
    'jeko' => [
        'api_key' => env('JEKO_API_KEY'),
        'api_key_id' => env('JEKO_API_KEY_ID'),
        'store_id' => env('JEKO_STORE_ID'),
        'webhook_secret' => env('JEKO_WEBHOOK_SECRET'),
        'base_url' => env('JEKO_BASE_URL', 'https://api.jeko.africa'),
        // Liens profonds (deep links) de l'application mobile vers lesquels
        // Jèko redirige après paiement : pas besoin d'hébergement web public
        // pour ceux-ci (seul le webhook a besoin d'une URL HTTPS publique).
        'success_url' => env('JEKO_SUCCESS_URL', 'afrolia://paiement/succes'),
        'error_url' => env('JEKO_ERROR_URL', 'afrolia://paiement/echec'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

];
