<?php

namespace App\Http\Controllers;

use App\Models\BillingWebhookEvent;
use App\Models\VerificationOrder;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives payment notifications from the Shulesoft Billing Platform and
 * unlocks whatever the paid order was for, via PaymentService::fulfill().
 * Follows the same shape as safarichat's BillingWebhookController
 * (see BILLING_WORKFLOW.md §6.3 / §7): signature verification, idempotency,
 * and a transaction per event.
 *
 * Expected payload (matches what ShulesoftBillingClient::createInvoice()
 * requests trigger on the platform side):
 * {
 *   "event": "payment.success" | "payment.failed" | "subscription.renewed" | ...,
 *   "event_id": "evt_...",              // preferred idempotency key
 *   "data": { "invoice_id": "...", "status": "paid" },
 *   "payment": { "transaction_id": "...", "amount": 9.99, "status": "completed" }
 * }
 */
class BillingWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        Log::info('BillingWebhookController: received', ['payload' => $request->all(), 'ip' => $request->ip()]);

        if (!$this->validateSignature($request)) {
            Log::warning('BillingWebhookController: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $invoiceId = $this->resolveInvoiceId($request);
        $idempotencyKey = $invoiceId ?? $request->input('event_id') ?? $request->input('payment.transaction_id');

        if (!$idempotencyKey) {
            Log::warning('BillingWebhookController: no invoice_id/event_id/transaction_id in payload — cannot process', ['payload' => $request->all()]);
            return response()->json(['success' => true, 'message' => 'Nothing to match — acknowledged']);
        }

        // Idempotency: a prior successful delivery for this exact
        // (key, event) pair means the platform is retrying — ack without reprocessing.
        $existing = BillingWebhookEvent::where('idempotency_key', $idempotencyKey)
            ->where('event_type', $event)
            ->first();

        if ($existing && $existing->processing_status === 'success') {
            Log::info('BillingWebhookController: duplicate delivery, already processed', ['idempotency_key' => $idempotencyKey, 'event' => $event]);
            return response()->json(['success' => true, 'message' => 'Already processed (idempotency)']);
        }

        $webhookEvent = BillingWebhookEvent::updateOrCreate(
            ['idempotency_key' => $idempotencyKey, 'event_type' => $event],
            [
                'payload' => $request->all(),
                'signature' => $request->header('X-Webhook-Signature'),
                'source_ip' => $request->ip(),
                'processing_status' => 'processing',
                'error_message' => null,
                'processed_at' => null,
            ]
        );

        $order = $invoiceId ? VerificationOrder::where('billing_invoice_id', $invoiceId)->first() : null;

        if (!$order) {
            $webhookEvent->update(['processing_status' => 'unresolved', 'error_message' => 'No matching order for invoice_id', 'processed_at' => now()]);
            Log::warning('BillingWebhookController: no matching order', ['invoice_id' => $invoiceId, 'event' => $event]);
            return response()->json(['success' => true, 'message' => 'No matching order — recorded for review']);
        }

        $webhookEvent->update(['verification_order_id' => $order->id]);

        try {
            $this->route($event, $order);

            $webhookEvent->update(['processing_status' => 'success', 'processed_at' => now()]);

            Log::info('BillingWebhookController: processed', ['event' => $event, 'order_id' => $order->id, 'order_kind' => $order->kind]);

            return response()->json(['success' => true, 'order_id' => $order->id, 'status' => $order->fresh()->status]);
        } catch (\Throwable $e) {
            $webhookEvent->update(['processing_status' => 'failed', 'error_message' => $e->getMessage(), 'processed_at' => now()]);
            Log::error('BillingWebhookController: handler failed', ['event' => $event, 'order_id' => $order->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'Internal error'], 500);
        }
    }

    /**
     * Talent doesn't have a recurring-subscription concept on the platform
     * side (premium is a flat monthly re-purchase) — so every "this money
     * arrived" event maps to the same fulfill(), and every "this
     * didn't/won't happen" event maps to markFailed(). Unrecognized events
     * are logged and acknowledged, never silently dropped.
     */
    private function route(?string $event, VerificationOrder $order): void
    {
        $successEvents = ['payment.success', 'subscription.created', 'subscription.renewed', 'subscription.upgraded'];
        $failureEvents = ['payment.failed', 'subscription.cancelled', 'subscription.expired'];

        if (in_array($event, $successEvents, true)) {
            $this->payments->fulfill($order);
            return;
        }

        if (in_array($event, $failureEvents, true)) {
            $this->payments->markFailed($order);
            return;
        }

        Log::warning('BillingWebhookController: unrecognized event type — acknowledged, not applied', ['event' => $event]);
    }

    private function resolveInvoiceId(Request $request): ?string
    {
        $value = $request->input('data.invoice_id')
            ?? $request->input('data.invoice.id')
            ?? $request->input('invoice_id')
            ?? $request->input('payment.invoice_id');

        return $value !== null ? (string) $value : null;
    }

    /**
     * HMAC-SHA256 of the raw request body against BILLING_WEBHOOK_SECRET,
     * same as safarichat. If no secret is configured, the check is skipped
     * with a warning (local/dev only — always set BILLING_WEBHOOK_SECRET
     * before pointing a real webhook at this endpoint).
     */
    private function validateSignature(Request $request): bool
    {
        $secret = config('services.billing.webhook_secret');

        if (!$secret) {
            Log::warning('BillingWebhookController: BILLING_WEBHOOK_SECRET not configured — skipping signature check');
            return true;
        }

        $signature = $request->header('X-Webhook-Signature');
        if (!$signature) {
            Log::warning('BillingWebhookController: secret is configured but request has no X-Webhook-Signature header');
            return false;
        }

        $rawSignature = str_contains($signature, '=')
            ? substr($signature, strpos($signature, '=') + 1)
            : $signature;

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $rawSignature);
    }
}
