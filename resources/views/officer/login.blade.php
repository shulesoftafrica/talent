<x-layout title="Officer Login — ShuleSoft Talent Network">
    <div class="flex min-h-screen items-center justify-center p-4" style="background: var(--color-ttn-navy)">
        <div class="w-[380px] max-w-full rounded-2xl bg-ttn-card p-6 sm:p-8">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                    <x-brand-logo size="h-7 w-7" />
                    <span class="font-display text-[13px] font-bold">{{ __('nav.ops_brand') }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <x-theme-toggle />
                    <x-language-switcher />
                </div>
            </div>
            <div class="text-xs font-semibold text-ttn-amber mb-6">{{ __('nav.ops_subtitle') }}</div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-ttn-red-bg px-4 py-3 text-[13px] font-semibold text-ttn-red">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('officer.login.submit') }}" class="flex flex-col gap-3">
                @csrf
                <div>
                    <label class="block text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('officer.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border border-ttn-border px-3.5 py-3 text-sm">
                </div>
                <div>
                    <label class="block text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('officer.password') }}</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg border border-ttn-border px-3.5 py-3 text-sm">
                </div>
                <button class="mt-2 rounded-lg bg-ttn-primary py-3 text-sm font-bold text-white cursor-pointer">{{ __('officer.sign_in') }}</button>
            </form>
        </div>
    </div>
</x-layout>
