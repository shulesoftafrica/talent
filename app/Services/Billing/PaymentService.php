<?php

namespace App\Services\Billing;

use App\Models\Candidate;
use App\Models\VerificationOrder;
use App\Services\Verification\VerificationStatus;
use App\Services\Verification\VerificationStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one entry point every paid feature in the Talent Network should use.
 * Mirrors safarichat's BILLING_WORKFLOW.md pattern, scaled down to what this
 * app actually needs: no credits/plan-limit engine, just discrete purchases
 * (verification, premium, and whatever comes next) that unlock something on
 * confirmed payment.
 *
 * To add a new paid feature (e.g. "extra file storage"):
 *   1. Add a price-plan env var + a line in config('services.billing.price_plans').
 *   2. Call PaymentService::purchase($candidate, 'storage_upgrade', $amount, $description, meta: [...]).
 *   3. Add ONE case to fulfill()'s match block that actually unlocks it.
 * That's it — invoice creation, payment-gateway extraction, and webhook
 * plumbing are already handled here.
 */
class PaymentService
{
    public function __construct(
        private readonly ShulesoftBillingClient $billing,
        private readonly VerificationStatusService $statusService,
    ) {
    }

    /**
     * Creates a pending order and (if the product's price plan is
     * configured) an invoice on the billing platform with payment options
     * attached. Always returns an order — check ->status to see whether
     * checkout is actually ready ('pending' with a billing_invoice_id) or
     * degraded ('awaiting_configuration' / 'failed').
     *
     * @param array<string, mixed> $meta Arbitrary payload for this product's fulfillment handler.
     */
    public function purchase(
        Candidate $candidate,
        string $product,
        float $amount,
        string $description,
        array $meta = [],
        string $currency = 'USD',
        ?int $pricePlanId = null,
    ): VerificationOrder {
        $order = VerificationOrder::create([
            'candidate_id' => $candidate->id,
            'kind' => $product,
            'total_amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'meta' => $meta,
        ]);

        $pricePlanId ??= $this->pricePlanIdFor($product);

        if (!$this->billing->isConfigured() || !$pricePlanId) {
            $order->forceFill(['status' => 'awaiting_configuration'])->save();
            return $order;
        }

        $invoice = $this->billing->createInvoice(
            customer: $this->customerPayload($candidate),
            pricePlanId: $pricePlanId,
            amount: $amount,
            description: $description,
            currency: $currency,
        );

        if (!$invoice['success']) {
            $order->forceFill(['status' => 'failed'])->save();
            return $order;
        }

        $gateways = $this->billing->getPaymentGateways($invoice['invoice_id']);

        $order->forceFill([
            'billing_invoice_id' => $invoice['invoice_id'],
            'billing_ucn' => $gateways['ucn'],
            'billing_stripe_link' => $gateways['stripe_link'],
            'billing_flutterwave_link' => $gateways['flutterwave_link'],
        ])->save();

        // For a wallet/usage product, the platform ties the UCN to the
        // candidate's own customer record, not to this one invoice — it's
        // the same number on every purchase they make. Cache it on the
        // candidate so it's available as a stable reference without
        // depending on a fresh API call each time.
        if ($gateways['ucn'] && $candidate->billing_ucn !== $gateways['ucn']) {
            $candidate->forceFill(['billing_ucn' => $gateways['ucn']])->save();
        }

        return $order;
    }

    /**
     * Called by BillingWebhookController once a payment is confirmed.
     * Idempotent — safe to call more than once for the same order (webhook
     * redelivery). Runs the whole unlock in one transaction.
     */
    public function fulfill(VerificationOrder $order): void
    {
        if ($order->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

            match ($order->kind) {
                'premium' => $this->unlockPremium($order),
                'verification' => $this->unlockVerification($order),
                default => $this->unlockGeneric($order),
            };
        });
    }

    public function markFailed(VerificationOrder $order): void
    {
        if ($order->status === 'paid') {
            return; // never downgrade a completed purchase
        }

        $order->forceFill(['status' => 'failed'])->save();
    }

    private function unlockPremium(VerificationOrder $order): void
    {
        $years = (int) ($order->meta['years'] ?? 1);

        /** @var Candidate $candidate */
        $candidate = $order->candidate;

        // Extend from the current expiry if still active (so paying early
        // never shortens what was already purchased), otherwise from now.
        $base = $candidate->is_premium && $candidate->premium_until?->isFuture()
            ? $candidate->premium_until
            : now();

        $candidate->forceFill([
            'is_premium' => true,
            'premium_until' => $base->copy()->addYears($years),
        ])->save();
    }

    private function unlockVerification(VerificationOrder $order): void
    {
        foreach ($order->items()->with('verificationItem')->get() as $orderItem) {
            $item = $orderItem->verificationItem;

            if ($item === null) {
                continue;
            }

            $this->statusService->transition(
                $item,
                VerificationStatus::WAITING_DOCUMENTS,
                'system',
                note: 'Payment confirmed via billing webhook.',
            );
        }
    }

    /**
     * Fallback for any product kind without a bespoke unlock case above —
     * logs so it's visible rather than silently doing nothing. Extend
     * fulfill()'s match block instead of relying on this for a real feature.
     */
    private function unlockGeneric(VerificationOrder $order): void
    {
        Log::warning('PaymentService: no fulfillment handler for product kind', [
            'order_id' => $order->id,
            'kind' => $order->kind,
            'meta' => $order->meta,
        ]);
    }

    private function pricePlanIdFor(string $product): ?int
    {
        $value = config("services.billing.price_plans.{$product}");

        return $value ? (int) $value : null;
    }

    /**
     * @return array{name:string,email:string,phone:string}
     */
    private function customerPayload(Candidate $candidate): array
    {
        return [
            'name' => $candidate->full_name,
            'email' => $candidate->email ?: "candidate-{$candidate->id}@talent.shulesoft.africa",
            'phone' => $candidate->phone,
        ];
    }
}
