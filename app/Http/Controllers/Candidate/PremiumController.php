<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\Billing\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function show(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $pendingOrder = $candidate->verificationOrders()->where('kind', 'premium')->where('status', 'pending')->latest()->first();

        return view('candidate.premium', [
            'candidate' => $candidate,
            'price' => (float) config('services.billing.premium_monthly_price', 9.99),
            'pendingOrder' => $pendingOrder,
        ]);
    }

    /**
     * Always creates (or reuses) an order and sends the candidate to the
     * shared Payment Page — even when the billing price plan isn't
     * configured yet, so that "not ready" is a pending state shown on the
     * payment page itself, never a dead-end button here.
     */
    public function store(): RedirectResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();
        $price = (float) config('services.billing.premium_monthly_price', 9.99);

        if ($candidate->is_premium) {
            return redirect()->route('candidate.premium.show')->with('status', 'You already have Premium.');
        }

        $order = $this->payments->purchase(
            candidate: $candidate,
            product: 'premium',
            amount: $price,
            description: 'ShuleSoft Talent Network — Premium Subscription',
            meta: ['months' => 1],
        );

        if ($order->status === 'failed') {
            return redirect()->route('candidate.premium.show')->withErrors(['checkout' => 'Could not start checkout right now. Please try again shortly.']);
        }

        return redirect()->route('candidate.payment.show', $order);
    }
}
