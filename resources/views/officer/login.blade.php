<x-layout title="Officer Login — ShuleSoft Talent Network">
    <div x-data="officerLogin()" class="flex min-h-screen items-center justify-center p-4" style="background: var(--color-ttn-navy)">
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

            {{-- Password sign-in --}}
            <div x-show="mode === 'password'">
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
                <div @click="mode = 'otp-email'" class="mt-4 text-center text-xs font-bold text-ttn-primary-dark cursor-pointer">{{ __('officer.otp_switch_to_otp') }}</div>
            </div>

            {{-- OTP: enter email --}}
            <div x-show="mode === 'otp-email'" x-cloak>
                <label class="block text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('officer.email') }}</label>
                <input type="email" x-model="otpEmail" @keydown.enter="sendOtp()" autofocus
                       class="w-full rounded-lg border border-ttn-border px-3.5 py-3 text-sm mb-2">
                <p class="text-xs text-ttn-red mb-2" x-show="otpError" x-text="otpError" x-cloak></p>
                <button @click="sendOtp()" :disabled="otpSending"
                        class="w-full rounded-lg bg-ttn-primary py-3 text-sm font-bold text-white cursor-pointer disabled:opacity-60">
                    {{ __('officer.otp_send_code') }}
                </button>
                <div @click="mode = 'password'; otpError = null" class="mt-4 text-center text-xs font-bold text-ttn-text2 cursor-pointer">{{ __('officer.otp_switch_to_password') }}</div>
            </div>

            {{-- OTP: enter code --}}
            <div x-show="mode === 'otp-code'" x-cloak>
                <div class="text-[13px] text-ttn-text2 mb-4">{{ __('officer.otp_code_sent_to') }} <span x-text="otpEmail" class="font-semibold"></span></div>
                <input
                    x-model="otpCode" maxlength="6" placeholder="000000" autocomplete="one-time-code" autofocus
                    @keydown.enter="verifyOtp()"
                    class="w-full rounded-[10px] border border-ttn-border px-3.5 py-3.5 text-center font-mono text-xl font-bold tracking-[0.4em] mb-4"
                >
                <p class="text-xs text-ttn-red mb-2 text-center" x-show="otpError" x-text="otpError" x-cloak></p>
                <button
                    @click="verifyOtp()" :disabled="otpVerifying"
                    class="w-full rounded-[10px] bg-ttn-primary py-3 text-sm font-bold text-white mb-2.5 cursor-pointer disabled:opacity-60"
                >{{ __('officer.otp_verify') }}</button>
                <div class="flex justify-center gap-4">
                    <div @click="resendOtp()" class="text-center text-xs font-bold text-ttn-primary-dark cursor-pointer">{{ __('officer.otp_resend') }}</div>
                    <div @click="mode = 'otp-email'; otpCode = ''; otpError = null" class="text-center text-xs font-bold text-ttn-text2 cursor-pointer">{{ __('officer.otp_change_email') }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function officerLogin() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            async function postJson(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(body),
                });
                return { ok: res.ok, data: await res.json() };
            }

            return {
                mode: 'password',
                otpEmail: '',
                otpCode: '',
                otpError: null,
                otpSending: false,
                otpVerifying: false,

                async sendOtp() {
                    this.otpError = null;
                    if (!this.otpEmail) {
                        this.otpError = @js(__('officer.otp_enter_email_first'));
                        return;
                    }
                    this.otpSending = true;
                    const { ok, data } = await postJson('{{ route('officer.otp.send') }}', { email: this.otpEmail });
                    this.otpSending = false;

                    if (!ok) {
                        this.otpError = data.message || @js(__('officer.otp_could_not_send'));
                        return;
                    }

                    this.otpCode = '';
                    this.mode = 'otp-code';
                },

                async resendOtp() {
                    this.otpError = null;
                    this.otpCode = '';
                    await postJson('{{ route('officer.otp.resend') }}', { email: this.otpEmail });
                },

                async verifyOtp() {
                    if (this.otpVerifying) return;
                    this.otpVerifying = true;
                    this.otpError = null;
                    const { ok, data } = await postJson('{{ route('officer.otp.verify') }}', {
                        email: this.otpEmail,
                        code: this.otpCode,
                    });
                    this.otpVerifying = false;

                    if (!ok) {
                        this.otpError = data.message || @js(__('officer.otp_invalid_code'));
                        this.otpCode = '';
                        return;
                    }

                    window.location.href = data.redirect;
                },
            };
        }
    </script>
</x-layout>
