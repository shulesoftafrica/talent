<x-candidate-shell :candidate="$candidate" active="profile" :title="__('verification.title')" :subtitle="__('verification.subtitle')">
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

    @if ($purchased->isNotEmpty())
        @php $categoryRoutes = [
            'identity' => 'candidate.verification.identity.show',
            'education' => 'candidate.verification.education.show',
            'professional_license' => 'candidate.verification.license.show',
            'employment_history' => 'candidate.verification.employment.show',
            'background_check' => 'candidate.verification.background.show',
            'references' => 'candidate.verification.references.show',
        ]; @endphp
        <div class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden mb-4">
            <div class="px-4 sm:px-5 py-3 bg-ttn-subtle text-[10px] font-bold uppercase tracking-wide text-ttn-text2">
                {{ __('verification.your_verifications') }}
            </div>
            @foreach ($purchased as $item)
                @php
                    $isVerified = $item->status === \App\Services\Verification\VerificationStatus::VERIFIED;
                    $routeName = $categoryRoutes[$item->verificationType->key] ?? null;
                @endphp
                <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-t border-ttn-hairline">
                    <div class="text-[12.5px] font-semibold">{{ $item->verificationType->name }}</div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold whitespace-nowrap {{ $isVerified ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-amber-bg text-ttn-amber-text' }}">
                            {{ \App\Services\Verification\VerificationStatus::label($item->status) }}
                        </span>
                        @if ($routeName && \Illuminate\Support\Facades\Route::has($routeName))
                            <a href="{{ route($routeName) }}" class="text-[11.5px] font-bold text-ttn-primary-dark whitespace-nowrap">{{ __('verification.start') }} &rarr;</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($purchasable->isNotEmpty())
        <div x-data="verificationCheckout({{ $purchasable->map(fn ($i) => ['id' => $i->id, 'name' => $i->verificationType->name, 'price' => (float) $i->price])->values()->toJson() }})"
             class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden mb-4">
            <form method="POST" action="{{ route('candidate.verification.store') }}">
                @csrf
                <div class="grid grid-cols-[22px_1fr_64px] sm:grid-cols-[28px_1fr_100px] gap-1.5 sm:gap-2.5 px-3 sm:px-5 py-3 bg-ttn-subtle text-[9px] sm:text-[10px] font-bold uppercase tracking-wide text-ttn-text2">
                    <div></div><div>{{ __('verification.col_name') }}</div><div>{{ __('verification.col_cost') }}</div>
                </div>
                @foreach ($purchasable as $item)
                    <label class="grid grid-cols-[22px_1fr_64px] sm:grid-cols-[28px_1fr_100px] gap-1.5 sm:gap-2.5 items-center px-3 sm:px-5 py-3 border-t border-ttn-hairline cursor-pointer">
                        <div>
                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" x-model="selected" class="h-4 w-4 cursor-pointer">
                        </div>
                        <div class="text-[11.5px] sm:text-[12.5px] font-semibold truncate">{{ $item->verificationType->name }}</div>
                        <div class="text-[11.5px] sm:text-[12.5px] font-bold">${{ number_format($item->price, 0) }}</div>
                    </label>
                @endforeach

                <div class="px-4 sm:px-5 py-4 border-t border-ttn-hairline bg-ttn-subtle">
                    <div class="font-display text-[13px] font-bold mb-2">{{ __('verification.summary') }}</div>
                    <div class="text-[12px] text-ttn-text2" x-show="selected.length === 0">{{ __('verification.summary_empty') }}</div>
                    <div x-show="selected.length > 0" class="flex flex-col gap-1.5">
                        <template x-for="item in selectedItems" :key="item.id">
                            <div class="flex justify-between text-[12px]">
                                <span x-text="item.name"></span>
                                <span class="font-semibold" x-text="'$' + item.price.toFixed(0)"></span>
                            </div>
                        </template>
                        <div class="flex justify-between text-[13px] font-bold pt-1.5 mt-1 border-t border-ttn-border">
                            <span>{{ __('verification.total') }}</span>
                            <span x-text="'$' + total.toFixed(0)"></span>
                        </div>
                    </div>
                </div>

                <div class="px-4 sm:px-5 py-4 border-t border-ttn-hairline">
                    <button type="submit" :disabled="selected.length === 0"
                            class="w-full rounded-lg bg-ttn-primary py-3 text-sm font-bold text-white cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ __('verification.request_verification') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($purchasable->isEmpty() && $purchased->isEmpty())
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6 text-center text-[13px] text-ttn-text2">
            {{ __('verification.none_available') }}
        </div>
    @endif

    <script>
        function verificationCheckout(items) {
            return {
                items: items.map((i) => ({ id: String(i.id), name: i.name, price: i.price })),
                selected: items.map((i) => String(i.id)),
                get selectedItems() {
                    return this.items.filter((i) => this.selected.includes(i.id));
                },
                get total() {
                    return this.selectedItems.reduce((sum, i) => sum + i.price, 0);
                },
            };
        }
    </script>
</x-candidate-shell>
