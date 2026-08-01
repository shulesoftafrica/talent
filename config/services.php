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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Multi-channel (email/SMS) sender used by App\Services\Notifications\UnifiedNotificationClient.
    // Shared with shulesoft_newversion — see https://notifications.shulesoft.africa/docs/getting-started
    'notification' => [
        'base_url' => env('NOTIFICATION_BASE_URL', 'https://notifications.shulesoft.africa'),
        'api_token' => env('NOTIFICATION_API_TOKEN'),
        'bearer_token' => env('UNIFIED_API_BEARER_TOKEN'),
    ],

    // Shulesoft Billing Platform — same remote service + credentials
    // safarichat uses (see App\Services\Billing\ShulesoftBillingClient and
    // App\Services\Billing\PaymentService — PaymentService::purchase() is
    // the one function every paid feature should call).
    //
    // price_plans is keyed by "product" (the `kind` on a VerificationOrder /
    // payment order row). Add a new ENV var + line here whenever a new paid
    // feature is introduced — until its ID is set, PaymentService::purchase()
    // degrades gracefully (order created with status=awaiting_configuration,
    // no invoice call) instead of erroring.
    'billing' => [
        'api_url' => env('BILLING_API_URL', 'https://api.safaribank.africa/api/v1'),
        'access_token' => env('BILLING_ACCESS_TOKEN'),
        'organization_id' => env('BILLING_ORGANIZATION_ID', '1'),
        'webhook_secret' => env('BILLING_WEBHOOK_SECRET'),
        'timeout' => env('BILLING_API_TIMEOUT', 30),

        'price_plans' => [
            'verification' => env('BILLING_VERIFICATION_PRICE_PLAN_ID'),
            'premium' => env('BILLING_PREMIUM_PRICE_PLAN_ID'),
        ],

        // Placeholder monthly price — not a confirmed business figure, just
        // enough for the checkout flow to be complete and testable; confirm
        // the real price before this goes live.
        'premium_monthly_price' => env('BILLING_PREMIUM_MONTHLY_PRICE', 9.99),
    ],

    // Academy LMS — base URL used to deep-link recommended courses from the
    // Career Plan modal to the course page where the candidate enrols.
    'academy' => [
        'url' => env('ACADEMY_URL', 'http://localhost/academy'),
    ],

];
