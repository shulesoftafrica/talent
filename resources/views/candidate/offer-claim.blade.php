<x-layout title="Your job offer — ShuleSoft Talent Network">
    <div class="min-h-screen">
        <header class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4 md:px-12 border-b border-ttn-border">
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5">
                <x-brand-logo size="h-8 w-8" />
                <span class="font-display text-[13.5px] sm:text-[15px] font-extrabold">ShuleSoft Talent Network</span>
            </a>
            <div class="flex items-center gap-1.5"><x-theme-toggle /><x-language-switcher /></div>
        </header>

        <main class="mx-auto max-w-md px-4 py-10">
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6">
                @if ($stage === 'invalid')
                    <div class="font-display text-[17px] font-extrabold mb-2">This offer link is not valid</div>
                    <p class="text-[13px] text-ttn-text2">The link may have expired. Please contact the school's HR department.</p>
                @elseif ($stage === 'mismatch')
                    <div class="font-display text-[17px] font-extrabold mb-2">This offer is for someone else</div>
                    <p class="text-[13px] text-ttn-text2">Sign in with the phone number or email the school has on your application.</p>
                @else
                    <div class="font-display text-[17px] font-extrabold mb-2">You have a job offer</div>
                    <p class="text-[13px] text-ttn-text2 mb-4">To open it, confirm it is you. We send a one-time code to the phone number on your application ({{ $masked ?? 'on file' }}).</p>

                    @if (session('claim_error'))
                        <div class="mb-3 rounded-lg bg-ttn-red-bg px-3 py-2 text-[12.5px] font-semibold text-ttn-red">{{ session('claim_error') }}</div>
                    @endif
                    @error('code')<div class="mb-3 rounded-lg bg-ttn-red-bg px-3 py-2 text-[12.5px] font-semibold text-ttn-red">{{ $message }}</div>@enderror

                    @if ($stage === 'code')
                        <form method="POST" action="{{ route('offers.claim.verify', [$module, $token]) }}" class="space-y-2">
                            @csrf
                            <label class="block text-[13px] font-bold" for="code">Enter the 6-digit code</label>
                            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" minlength="6" required
                                   class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2.5 text-[15px] tracking-widest">
                            <button type="submit" class="w-full rounded-lg bg-ttn-primary px-4 py-2.5 text-[13.5px] font-bold text-white cursor-pointer">Open my offer</button>
                        </form>
                        <form method="POST" action="{{ route('offers.claim.send', [$module, $token]) }}" class="mt-3 text-center">
                            @csrf
                            <button type="submit" class="text-[12.5px] font-bold text-ttn-primary-dark cursor-pointer">Send a new code</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('offers.claim.send', [$module, $token]) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-ttn-primary px-4 py-2.5 text-[13.5px] font-bold text-white cursor-pointer">Send me a code</button>
                        </form>
                    @endif
                @endif
            </div>
        </main>
    </div>
</x-layout>
