<?php

return [
    'enabled' => env('SMS_ENABLED', true),
    'base_url' => env('SMS_BASE_URL', 'https://smsapi.microcloudltd.com'),

    'org_code' => env('SMS_ORG_CODE'),
    'md5_key' => env('SMS_MD5_KEY'),
    'sender_id' => env('SMS_SENDER_ID'),

    'otp_expire_minutes' => (int) env('SMS_OTP_EXPIRE_MINUTES', 5),
    'otp_resend_seconds' => (int) env('SMS_OTP_RESEND_SECONDS', 60),
    'otp_max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),

    'balance_cache_seconds' => 300,
    'balance_last_known_seconds' => 86400,
    'balance_refresh_min_interval' => 30,
    'balance_manual_refresh_cooldown' => 10,
    // USD amounts. Leave unset until the account's operating thresholds are agreed.
    'balance_warning_threshold' => env('SMS_BALANCE_WARNING_THRESHOLD'),
    'balance_critical_threshold' => env('SMS_BALANCE_CRITICAL_THRESHOLD'),
];
