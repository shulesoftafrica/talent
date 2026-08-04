<x-candidate-shell :candidate="$candidate" active="profile" :title="__('premium.title')" :subtitle="__('premium.subtitle')">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-ttn-red-bg px-4 py-3 text-[13px] font-semibold text-ttn-red">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($candidate->is_premium)
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 text-center">
            <div class="font-display text-[15px] font-bold mb-1.5">{{ __('premium.has_premium') }}</div>
            <div class="text-[13px] text-ttn-text2">
                @if ($candidate->premium_until)
                    {{ __('premium.active_until', ['date' => $candidate->premium_until->format('d M Y')]) }}
                @else
                    {{ __('premium.active') }}
                @endif
            </div>
        </div>
    @else
        <div class="rounded-2xl p-4 sm:p-6 text-white" style="background: var(--color-ttn-navy)">
            <div class="font-display text-[15px] font-bold mb-3">{{ __('premium.career_tools') }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                @foreach (__('premium.features') as $feature)
                    <div class="text-xs opacity-85">✓ {{ $feature }}</div>
                @endforeach
            </div>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="font-display text-2xl font-extrabold">{{ $currency === 'TZS' ? 'TZS ' . number_format($price, 0) : '$' . number_format($price, 2) }}<span class="text-xs font-semibold opacity-70">{{ __('premium.per_month') }}</span></div>
                @if ($pendingOrder)
                    <a href="{{ route('candidate.payment.show', $pendingOrder) }}" class="rounded-lg bg-ttn-amber px-5 py-2.5 text-[13px] font-bold cursor-pointer whitespace-nowrap" style="color: var(--color-ttn-navy)">
                        {{ __('payment.continue') }}
                    </a>
                @else
                    <form method="POST" action="{{ route('candidate.premium.store') }}">
                        @csrf
                        <button class="rounded-lg bg-ttn-amber px-5 py-2.5 text-[13px] font-bold cursor-pointer whitespace-nowrap" style="color: var(--color-ttn-navy)">
                            {{ __('payment.proceed') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</x-candidate-shell>
