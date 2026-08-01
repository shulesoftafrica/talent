<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\VerificationOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The one shared payment page every paid feature (verification, premium,
 * and whatever comes next) redirects to after App\Services\Billing\PaymentService
 * creates an order/invoice. Payment confirmation is never trusted from the
 * frontend — Finish only ever reads $order->status, which only the billing
 * webhook (App\Http\Controllers\BillingWebhookController) is allowed to
 * flip to 'paid'.
 */
class PaymentController extends Controller
{
    public function show(VerificationOrder $order): View
    {
        $this->authorizeOrder($order);

        return view('candidate.payment', [
            'candidate' => $order->candidate,
            'order' => $order,
            'productLabel' => $this->productLabel($order),
        ]);
    }

    public function finish(VerificationOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        if ($order->status !== 'paid') {
            return redirect()->route('candidate.payment.show', $order)
                ->with('payment_pending', __('payment.pending_message'));
        }

        return match ($order->kind) {
            'premium' => redirect()->route('candidate.jobs')->with('status', __('premium.upgrade') . ' — ' . __('payment.confirmed_body')),
            'verification' => redirect()->route('candidate.verification.show')->with('status', __('payment.confirmed_body')),
            default => redirect()->route('candidate.jobs')->with('status', __('payment.confirmed_body')),
        };
    }

    private function authorizeOrder(VerificationOrder $order): void
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        abort_unless($order->candidate_id === $candidate->id, 404);
    }

    private function productLabel(VerificationOrder $order): string
    {
        return match ($order->kind) {
            'premium' => __('payment.product_premium'),
            'verification' => __('verification.title'),
            default => ucfirst(str_replace('_', ' ', $order->kind)),
        };
    }
}
