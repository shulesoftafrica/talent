<?php

return [

    // Global kill-switch for the verification module — every verification
    // nav item/section/CTA checks this instead of assuming the feature is
    // live. Flip VERIFICATION_ENABLED=1 in .env when the business is ready.
    'verification_enabled' => (bool) env('VERIFICATION_ENABLED', false),

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

    // Single multi-channel (email/SMS/WhatsApp) sender used by
    // App\Services\Notifications\UnifiedNotificationClient — one endpoint,
    // one JSON object per send. See https://notifications.shulesoft.africa/docs/getting-started
    'notification' => [
        'base_url' => env('NOTIFICATION_BASE_URL', 'https://notifications.shulesoft.africa'),
        'bearer_token' => env('NOTIFICATION_BEARER_TOKEN'),
        'schema_name' => env('NOTIFICATION_SCHEMA_NAME', 'talent'),
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
    // no invoice call) instead of erroring. Premium doesn't use this generic
    // single-ID lookup — it has two price plans (TZS/USD), resolved by
    // ShulesoftBillingClient::getOrCreateTalentPremiumProduct() instead.
    'billing' => [
        'api_url' => env('BILLING_API_URL', 'https://api.safaribank.africa/api/v1'),
        'access_token' => env('BILLING_ACCESS_TOKEN'),
        'organization_id' => env('BILLING_ORGANIZATION_ID', '1'),
        'webhook_secret' => env('BILLING_WEBHOOK_SECRET'),
        'timeout' => env('BILLING_API_TIMEOUT', 30),

        'price_plans' => [
            'verification' => env('BILLING_VERIFICATION_PRICE_PLAN_ID'),
        ],

        // Annual price — Tanzanian candidates in TZS, everyone else in USD.
        // See Candidate::isInTanzania() and PremiumController.
        'premium_price_usd' => env('BILLING_PREMIUM_PRICE_USD', 9.90),
        'premium_price_tzs' => env('BILLING_PREMIUM_PRICE_TZS', 20000),
    ],

    // OAuth path for the same Shulesoft Billing Platform above — needed
    // because the static 'billing.access_token' pointed at a
    // personal_access_tokens row that no longer exists (confirmed via
    // direct DB read). Uses a pre-provisioned client (see the platform's
    // own dashboard, org "Shulesoft Company Limited" — Organization →
    // API Credentials); ShulesoftBillingClient tries this first and falls
    // back to the static token if it's ever unavailable.
    'shulesoft_billing' => [
        'api_url' => env('SHULESOFT_API_URL', 'https://api.safaribank.africa/api/v1'),
        'client_id' => env('SHULESOFT_CLIENT_ID'),
        'client_secret' => env('SHULESOFT_CLIENT_SECRET'),
        'organization_id' => env('BILLING_ORGANIZATION_ID', '1'),
        'timeout' => env('BILLING_API_TIMEOUT', 30),
        'connect_timeout' => env('BILLING_API_CONNECT_TIMEOUT', 5),

        // Currency ids on this platform (confirmed via billing.currencies —
        // NOT what the platform's own example docs implied): 1 = USD, 2 = TZS.
        'currency_ids' => ['USD' => 1, 'TZS' => 2],
    ],

    // Academy LMS — base URL used to deep-link recommended courses from the
    // Career Plan modal to the course page where the candidate enrols.
    'academy' => [
        'url' => env('ACADEMY_URL', 'http://localhost/academy'),
    ],

];
