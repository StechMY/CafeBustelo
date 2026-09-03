<?php

return [
    'enabled' => env('SMS_ENABLED', true),
    'base_url' => env('SMS_BASE_URL', 'http://121.91.174.90:9090'),

    'appkey' => env('SMS_APP_KEY'),
    'appcode' => env('SMS_APP_CODE'),
    'appsecret' => env('SMS_APP_SECRET'),
    'extend' => env('SMS_EXTEND', ''),

    'otp_expire_minutes' => (int) env('SMS_OTP_EXPIRE_MINUTES', 5),
    'otp_resend_seconds' => (int) env('SMS_OTP_RESEND_SECONDS', 60),
    'otp_max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),

    'balance_cache_seconds' => 300,
    'balance_last_known_seconds' => 86400,
    'balance_refresh_min_interval' => 30,
    'balance_manual_refresh_cooldown' => 10,
    'balance_warning_threshold' => 5000,
    'balance_critical_threshold' => 1000,
];
