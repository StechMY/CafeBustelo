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
    ],

    'qc168' => [
        'enabled' => env('QC168_ENABLED', false),
        'base_url' => env('QC168_BASE_URL'),
        'oc_key' => env('QC168_OC_KEY'),
        'secret' => env('QC168_SECRET'),
        'merchant_uid' => env('QC168_MERCHANT_UID'),
        'currency' => env('QC168_CURRENCY', 'vnd'),
        'country' => env('QC168_COUNTRY', 'vn'),
        'payment' => env('QC168_DEPOSIT_PAYMENT', env('QC168_PAYMENT')),
        'deposit_payment' => env('QC168_DEPOSIT_PAYMENT', env('QC168_PAYMENT')),
        'withdraw_payment' => env('QC168_WITHDRAW_PAYMENT', env('QC168_PAYMENT')),
        'timeout' => (int) env('QC168_TIMEOUT', 20),
        'connect_timeout' => (int) env('QC168_CONNECT_TIMEOUT', 10),
    ],

];
