<x-candidate-shell :candidate="$candidate" active="profile" :title="__('payment.title')" :subtitle="__('payment.subtitle')">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">
            {{ session('status') }}
        </div>
    @endif

    @if (session('payment_pending'))
        <div class="mb-4 rounded-lg bg-ttn-amber-bg px-4 py-3 text-[13px] font-semibold text-ttn-amber-text">
            {{ session('payment_pending') }}
        </div>
    @endif

    <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="font-display text-[15px] font-bold mb-4">{{ __('payment.summary') }}</div>
        <div class="flex flex-col gap-2.5 text-[13px]">
            <div class="flex justify-between gap-3">
                <span class="text-ttn-text2">{{ __('payment.product') }}</span>
                <span class="font-semibold text-right">{{ $productLabel }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-ttn-text2">{{ __('payment.amount') }}</span>
                <span class="font-bold">{{ $order->currency }} {{ number_format($order->total_amount, $order->currency === 'TZS' ? 0 : 2) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-ttn-text2">{{ __('payment.invoice_number') }}</span>
                <span class="font-semibold">{{ $order->billing_invoice_id ?? ('TN-' . $order->id) }}</span>
            </div>
            <div class="flex justify-between items-center gap-3">
                <span class="text-ttn-text2">{{ __('payment.status') }}</span>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold whitespace-nowrap {{ $order->status === 'paid' ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-amber-bg text-ttn-amber-text' }}">
                    {{ $order->status === 'paid' ? __('payment.status_paid') : __('payment.status_pending') }}
                </span>
            </div>
        </div>
    </div>

    @if ($order->status !== 'paid')
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
            <div class="font-display text-[15px] font-bold mb-1">{{ __('payment.methods') }}</div>

            @if (!$order->billing_ucn && !$order->billing_stripe_link && !$order->billing_flutterwave_link)
                <div class="text-[13px] text-ttn-text2">{{ __('payment.preparing') }}</div>
            @else
                <div class="text-[12.5px] text-ttn-text2 mb-4">{{ __('payment.methods_intro') }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5" x-data="{ copied: false }">
                    @if ($order->billing_ucn)
                        <div class="rounded-xl border border-ttn-border p-4 flex flex-col items-center text-center">
                            <div class="text-2xl mb-2">🏦</div>
                            <div class="text-[13.5px] font-bold mb-0.5">{{ __('payment.ucn_title') }}</div>
                            <div class="text-[11.5px] text-ttn-text2 mb-3.5">{{ __('payment.ucn_subtitle') }}</div>
                            <div class="w-full rounded-lg border-2 border-dashed border-ttn-primary bg-ttn-primary-light px-3 py-3 mb-3.5">
                                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1">{{ __('payment.ucn_send_to') }}</div>
                                <div class="font-display text-lg font-extrabold text-ttn-primary-dark tracking-wide mb-2" x-ref="ucnNumber">{{ $order->billing_ucn }}</div>
                                <button
                                    type="button"
                                    @click="navigator.clipboard.writeText('{{ $order->billing_ucn }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="rounded-md bg-ttn-primary px-3 py-1.5 text-[11.5px] font-bold text-white cursor-pointer"
                                >
                                    <span x-show="!copied">📋 {{ __('payment.ucn_copy') }}</span>
                                    <span x-show="copied" x-cloak>✓ {{ __('payment.ucn_copied') }}</span>
                                </button>
                            </div>
                            <div class="text-[11.5px] text-ttn-text2">{{ __('payment.ucn_amount', ['amount' => $order->currency . ' ' . number_format($order->total_amount, $order->currency === 'TZS' ? 0 : 2)]) }}</div>
                        </div>
                    @endif
                    @if ($order->billing_stripe_link)
                        <div class="rounded-xl border border-ttn-border p-4 flex flex-col items-center text-center">
                            <div class="text-2xl mb-2">💳</div>
                            <div class="text-[13.5px] font-bold mb-0.5">{{ __('payment.stripe_title') }}</div>
                            <div class="text-[11.5px] text-ttn-text2 mb-3.5">{{ __('payment.stripe_subtitle') }}</div>
                            <div class="w-full rounded-lg bg-ttn-subtle px-3 py-3 mb-3.5">
                                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1">{{ __('payment.amount_label') }}</div>
                                <div class="font-display text-base font-extrabold">{{ $order->currency }} {{ number_format($order->total_amount, $order->currency === 'TZS' ? 0 : 2) }}</div>
                            </div>
                            <a href="{{ $order->billing_stripe_link }}" target="_blank" rel="noopener" class="w-full rounded-lg bg-ttn-primary px-4 py-2.5 text-center text-[13px] font-bold text-white">{{ __('payment.stripe_cta') }}</a>
                        </div>
                    @endif
                    @if ($order->billing_flutterwave_link)
                        <div class="rounded-xl border border-ttn-border p-4 flex flex-col items-center text-center">
                            <div class="text-2xl mb-2">📱</div>
                            <div class="text-[13.5px] font-bold mb-0.5">{{ __('payment.flutterwave_title') }}</div>
                            <div class="text-[11.5px] text-ttn-text2 mb-3.5">{{ __('payment.flutterwave_subtitle') }}</div>
                            <div class="w-full rounded-lg bg-ttn-subtle px-3 py-3 mb-3.5">
                                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1">{{ __('payment.amount_label') }}</div>
                                <div class="font-display text-base font-extrabold">{{ $order->currency }} {{ number_format($order->total_amount, $order->currency === 'TZS' ? 0 : 2) }}</div>
                            </div>
                            <a href="{{ $order->billing_flutterwave_link }}" target="_blank" rel="noopener" class="w-full rounded-lg bg-ttn-primary px-4 py-2.5 text-center text-[13px] font-bold text-white">{{ __('payment.flutterwave_cta') }}</a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6">
            <div class="font-display text-[14px] font-bold mb-1">{{ __('payment.waiting_title') }}</div>
            <div class="text-[12px] text-ttn-text2 mb-4">{{ __('payment.last_updated', ['time' => $order->updated_at->diffForHumans()]) }}</div>
            <div class="flex flex-col sm:flex-row gap-2.5">
                <a href="{{ route('candidate.payment.show', $order) }}" class="flex-1 rounded-lg border border-ttn-border px-4 py-3 text-center text-sm font-bold text-ttn-text2">{{ __('payment.refresh_status') }}</a>
                <form method="POST" action="{{ route('candidate.payment.finish', $order) }}" class="flex-1">
                    @csrf
                    <button class="w-full rounded-lg bg-ttn-primary px-4 py-3 text-sm font-bold text-white cursor-pointer">{{ __('payment.finish') }}</button>
                </form>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-ttn-border bg-ttn-primary-light p-4 sm:p-6 text-center">
            <div class="font-display text-[15px] font-bold text-ttn-primary-dark mb-1">{{ __('payment.confirmed_title') }}</div>
            <div class="text-[13px] text-ttn-primary-dark mb-4">{{ __('payment.confirmed_body') }}</div>
            <form method="POST" action="{{ route('candidate.payment.finish', $order) }}">
                @csrf
                <button class="rounded-lg bg-ttn-primary px-5 py-2.5 text-sm font-bold text-white cursor-pointer">{{ __('payment.continue') }}</button>
            </form>
        </div>
    @endif
</x-candidate-shell>
