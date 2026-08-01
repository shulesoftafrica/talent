<?php

return [
    'base_url' => env('META_WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    'api_version' => env('META_WHATSAPP_API_VERSION', 'v24.0'),
    'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('META_WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
    'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('META_WHATSAPP_APP_SECRET'),

    'otp_template_name' => env('META_WHATSAPP_OTP_TEMPLATE_NAME', 'otp'),
    'otp_template_language' => env('META_WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'en'),

    'fallback_error_codes' => array_filter(array_map('trim', explode(',', env('META_WHATSAPP_FALLBACK_ERROR_CODES', '')))),

    'settings' => [
        'enable_fallback' => env('META_WHATSAPP_ENABLE_FALLBACK', true),
        'log_requests' => env('META_WHATSAPP_LOG_REQUESTS', true),
        'timeout' => env('META_WHATSAPP_TIMEOUT_SECONDS', 30),
    ],
];
