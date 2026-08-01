<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\VerificationType;
use App\Services\Billing\PaymentService;
use App\Services\Verification\VerificationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VerificationCheckoutController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function show(): View
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $items = $this->itemsForCheckout($candidate);

        return view('candidate.verification-checkout', [
            'candidate' => $candidate,
            'purchasable' => $items->where('status', VerificationStatus::WAITING_PAYMENT)->values(),
            'purchased' => $items->where('status', '!=', VerificationStatus::WAITING_PAYMENT)->values(),
        ]);
    }

    /**
     * Never opens upload forms directly — creates the order and sends the
     * candidate to the shared Payment Page. Upload forms only ever appear
     * once the order is paid (checked via VerificationStatus, never a
     * frontend payment response).
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var Candidate $candidate */
        $candidate = Auth::guard('candidate')->user();

        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
        ]);

        $items = $candidate->verificationItems()
            ->whereIn('id', $data['item_ids'])
            ->where('status', VerificationStatus::WAITING_PAYMENT)
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['item_ids' => 'Select at least one item to verify.']);
        }

        $total = $items->sum('price');
        $currency = $items->first()->currency ?? 'USD';

        $order = $this->payments->purchase(
            candidate: $candidate,
            product: 'verification',
            amount: (float) $total,
            description: 'ShuleSoft Talent Network — Candidate Verification',
            meta: ['item_ids' => $items->pluck('id')->all()],
            currency: $currency,
        );

        // Line items — specific to this product, not something the generic
        // purchase() call handles (a future product like "storage upgrade"
        // wouldn't need this table at all).
        foreach ($items as $item) {
            $order->items()->create([
                'candidate_verification_item_id' => $item->id,
                'price' => $item->price,
            ]);
        }

        if ($order->status === 'failed') {
            return back()->withErrors(['checkout' => 'Could not start checkout right now. Please try again shortly.']);
        }

        return redirect()->route('candidate.payment.show', $order);
    }

    /**
     * Ensures a candidate_verification_items row exists for every
     * VerificationType (lazily created on first view, not at signup) and
     * returns them all for the checkout list.
     */
    private function itemsForCheckout(Candidate $candidate)
    {
        $existingTypeIds = $candidate->verificationItems()->pluck('verification_type_id')->all();

        foreach (VerificationType::whereNotIn('id', $existingTypeIds)->get() as $type) {
            $candidate->verificationItems()->create([
                'verification_type_id' => $type->id,
                'status' => VerificationStatus::WAITING_PAYMENT,
                'price' => $type->default_price,
                'currency' => $type->currency,
                'payer' => $type->default_payer,
            ]);
        }

        return $candidate->verificationItems()->with('verificationType')->get()->sortBy('verificationType.sort_order');
    }
}
