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

    'ydpay' => [
        'enabled' => env('YDPAY_ENABLED', false),
        'base_url' => env('YDPAY_BASE_URL', 'https://gateway.ydpay.vip'),
        'merchant_id' => env('YDPAY_MERCHANT_ID'),
        'secret' => env('YDPAY_SECRET'),
        'deposit_enabled' => env('YDPAY_DEPOSIT_ENABLED', false),
        'withdraw_enabled' => env('YDPAY_WITHDRAW_ENABLED', false),
        'deposit_gateway_id' => env('YDPAY_DEPOSIT_GATEWAY_ID'),
        'withdraw_gateway_id' => env('YDPAY_WITHDRAW_GATEWAY_ID'),
        'callback_ips' => array_filter(array_map('trim', explode(',', env('YDPAY_CALLBACK_IPS', '38.22.88.53')))),
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
