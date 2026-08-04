<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the remote Shulesoft Billing Platform, mirroring safarichat's
 * proven BillingService::createSubscriptionInvoice() /
 * BillingApiController::getWalletUCN() pattern — same platform, same
 * organization (org 1, "Shulesoft Company Limited"), same request/response
 * shapes. Creates an invoice, then extracts whichever payment options
 * (UCN / Stripe / Flutterwave) the platform returns for it.
 *
 * Auth: tries the OAuth client-credentials token (ShulesoftAuthService)
 * first, falling back to the static 'billing.access_token' if OAuth isn't
 * configured — see ShulesoftAuthService's docblock for why OAuth is the
 * real path now.
 */
class ShulesoftBillingClient
{
    public function isConfigured(): bool
    {
        return !empty(config('services.shulesoft_billing.client_id')) || !empty(config('services.billing.access_token'));
    }

    /**
     * @param array{name:string,email:string,phone:string} $customer
     * @return array{success:bool,invoice_id:?string,error:?string}
     */
    public function createInvoice(array $customer, int $pricePlanId, float $amount, string $description, string $currency = 'USD'): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'invoice_id' => null, 'error' => 'not_configured'];
        }

        $payload = [
            'organization_id' => (int) config('services.billing.organization_id', 1),
            'customer' => $customer,
            'products' => [
                ['price_plan_id' => $pricePlanId, 'amount' => $amount],
            ],
            'description' => $description,
            'currency' => $currency,
            'status' => 'issued',
        ];

        $response = $this->request('POST', '/invoices', $payload);

        if (!$response['success']) {
            return ['success' => false, 'invoice_id' => null, 'error' => $response['error']];
        }

        $invoiceId = $response['data']['data']['invoice']['id']
            ?? $response['data']['data']['id']
            ?? $response['data']['invoice']['id']
            ?? null;

        if (!$invoiceId) {
            Log::error('ShulesoftBillingClient: invoice created but no id found in response', ['body' => $response['data']]);
            return ['success' => false, 'invoice_id' => null, 'error' => 'no_invoice_id'];
        }

        return ['success' => true, 'invoice_id' => (string) $invoiceId, 'error' => null];
    }

    /**
     * Extracts UCN / Stripe / Flutterwave payment options for an invoice,
     * trying the same three response shapes safarichat's getWalletUCN()
     * handles (the platform's response shape has varied across their
     * versions).
     *
     * @return array{ucn:?string,stripe_link:?string,flutterwave_link:?string}
     */
    public function getPaymentGateways(string $invoiceId): array
    {
        $result = ['ucn' => null, 'stripe_link' => null, 'flutterwave_link' => null];

        $response = $this->request('GET', "/invoices/{$invoiceId}/payment-gateways");
        if (!$response['success']) {
            return $result;
        }

        $data = $response['data']['data'] ?? $response['data'] ?? [];

        // Shape 1: directly on the response.
        $result['ucn'] = $data['ucn'] ?? $data['control_number'] ?? null;

        // Shape 2: price_plans[0].payment_gateways[] with gateway_name/references/payment_link.
        $gateways = $data['price_plans'][0]['payment_gateways'] ?? $data['payment_gateways'] ?? [];
        foreach ($gateways as $gateway) {
            $name = $gateway['gateway_name'] ?? null;
            if ($name === 'Universal Control Number' && isset($gateway['references'])) {
                $result['ucn'] ??= $gateway['references'];
            }
            if ($name === 'Stripe' && isset($gateway['payment_link'])) {
                $result['stripe_link'] ??= $gateway['payment_link'];
            }
            if ($name === 'Flutterwave' && isset($gateway['payment_link'])) {
                $result['flutterwave_link'] ??= $gateway['payment_link'];
            }
        }

        // Shape 3: payment_links.{ucn,stripe,flutterwave} on either level.
        $links = $data['price_plans'][0]['payment_links'] ?? $data['payment_links'] ?? [];
        $result['ucn'] ??= $links['ucn'] ?? null;
        $result['stripe_link'] ??= $links['stripe'] ?? null;
        $result['flutterwave_link'] ??= $links['flutterwave'] ?? null;

        return $result;
    }

    private const PREMIUM_PRODUCT_CODE = 'talent-premium';
    private const PREMIUM_PRODUCT_CACHE_KEY = 'talent_premium_product_price_plans';

    /**
     * Gets (or, on first call, creates) the "Talent Subscription" product on
     * the billing platform and returns its two price plan ids — one per
     * currency, since Tanzanian and international candidates are charged
     * differently (see PremiumController). Mirrors safarichat's
     * BillingApiController::createOrGetCreditsProduct() pattern exactly:
     * usage/wallet product type (rate=1 so amount==quantity — this app
     * never reads quantity/wallet balance back from the platform, since
     * Candidate::premium_until is the actual source of truth for access,
     * set locally by PaymentService::unlockPremium() once a webhook
     * confirms payment). Result is cached forever; call
     * clearPremiumProductCache() to force a re-check/re-create.
     *
     * @return array{tzs_price_plan_id:?int, usd_price_plan_id:?int}
     */
    public function getOrCreateTalentPremiumProduct(): array
    {
        $cached = Cache::get(self::PREMIUM_PRODUCT_CACHE_KEY);
        if ($cached && $cached['tzs_price_plan_id'] && $cached['usd_price_plan_id']) {
            return $cached;
        }

        $existing = $this->request('GET', '/products/' . self::PREMIUM_PRODUCT_CODE);
        if ($existing['success']) {
            $plans = $this->extractPricePlanIds($existing['data']['data'] ?? []);
            if ($plans['tzs_price_plan_id'] && $plans['usd_price_plan_id']) {
                Cache::forever(self::PREMIUM_PRODUCT_CACHE_KEY, $plans);
                return $plans;
            }
        }

        $currencyIds = config('services.shulesoft_billing.currency_ids', ['USD' => 1, 'TZS' => 2]);

        $created = $this->request('POST', '/products', [
            'organization_id' => (int) config('services.billing.organization_id', 1),
            'product_type_id' => 3, // usage/wallet
            'name' => 'Talent Subscription',
            'product_code' => self::PREMIUM_PRODUCT_CODE,
            'description' => 'ShuleSoft Talent Network — Premium annual subscription',
            'unit' => 'Subscription',
            'active' => true,
            'price_plans' => [
                ['name' => 'Talent Subscription — Tanzania (Annual)', 'currency_id' => $currencyIds['TZS'], 'rate' => 1],
                ['name' => 'Talent Subscription — International (Annual)', 'currency_id' => $currencyIds['USD'], 'rate' => 1],
            ],
        ]);

        if (!$created['success']) {
            Log::error('ShulesoftBillingClient: failed to create Talent Subscription product', ['body' => $created['data']]);
            return ['tzs_price_plan_id' => null, 'usd_price_plan_id' => null];
        }

        $plans = $this->extractPricePlanIds($created['data']['data'] ?? []);
        if ($plans['tzs_price_plan_id'] && $plans['usd_price_plan_id']) {
            Cache::forever(self::PREMIUM_PRODUCT_CACHE_KEY, $plans);
        }

        return $plans;
    }

    public function clearPremiumProductCache(): void
    {
        Cache::forget(self::PREMIUM_PRODUCT_CACHE_KEY);
    }

    /**
     * @return array{tzs_price_plan_id:?int, usd_price_plan_id:?int}
     */
    private function extractPricePlanIds(array $productData): array
    {
        $result = ['tzs_price_plan_id' => null, 'usd_price_plan_id' => null];

        foreach ($productData['price_plans'] ?? [] as $plan) {
            match ($plan['currency'] ?? null) {
                'TZS' => $result['tzs_price_plan_id'] = $plan['id'] ?? null,
                'USD' => $result['usd_price_plan_id'] = $plan['id'] ?? null,
                default => null,
            };
        }

        return $result;
    }

    /**
     * @return array{success:bool,data:?array,status:?int,error:?string}
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $baseUrl = rtrim(config('services.billing.api_url'), '/');
        $token = ShulesoftAuthService::getAccessToken() ?? config('services.billing.access_token');

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('services.billing.timeout', 30))
                ->{strtolower($method)}($baseUrl . $path, $payload);
        } catch (\Throwable $e) {
            Log::error('ShulesoftBillingClient: network error', ['error' => $e->getMessage(), 'path' => $path]);
            return ['success' => false, 'data' => null, 'status' => null, 'error' => $e->getMessage()];
        }

        if (!$response->successful()) {
            Log::error('ShulesoftBillingClient: request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['success' => false, 'data' => null, 'status' => $response->status(), 'error' => 'http_' . $response->status()];
        }

        return ['success' => true, 'data' => $response->json(), 'status' => $response->status(), 'error' => null];
    }
}
