<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\Billing\PaymentService;
use App\Services\Billing\ShulesoftBillingClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PremiumController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ShulesoftBillingClient $billing,
    ) {
    }

    public function show(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $pendingOrder = $candidate->verificationOrders()->where('kind', 'premium')->where('status', 'pending')->latest()->first();
        [$price, $currency] = $this->priceFor($candidate);

        return view('candidate.premium', [
            'candidate' => $candidate,
            'price' => $price,
            'currency' => $currency,
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

        if ($candidate->is_premium) {
            return redirect()->route('candidate.premium.show')->with('status', 'You already have Premium.');
        }

        [$price, $currency] = $this->priceFor($candidate);
        $plans = $this->billing->getOrCreateTalentPremiumProduct();
        $pricePlanId = $currency === 'TZS' ? $plans['tzs_price_plan_id'] : $plans['usd_price_plan_id'];

        $order = $this->payments->purchase(
            candidate: $candidate,
            product: 'premium',
            amount: $price,
            description: 'ShuleSoft Talent Network — Premium Subscription (Annual)',
            meta: ['years' => 1],
            currency: $currency,
            pricePlanId: $pricePlanId,
        );

        if ($order->status === 'failed') {
            return redirect()->route('candidate.premium.show')->withErrors(['checkout' => 'Could not start checkout right now. Please try again shortly.']);
        }

        return redirect()->route('candidate.payment.show', $order);
    }

    /**
     * @return array{0: float, 1: string} [amount, currency]
     */
    private function priceFor(Candidate $candidate): array
    {
        return $candidate->isInTanzania()
            ? [(float) config('services.billing.premium_price_tzs', 20000), 'TZS']
            : [(float) config('services.billing.premium_price_usd', 9.90), 'USD'];
    }
}
