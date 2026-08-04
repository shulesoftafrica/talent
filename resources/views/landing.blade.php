<x-layout title="ShuleSoft Talent Network — The hiring network for schools">
    <div x-data="landingPage()" class="min-h-screen">
        <header class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4 md:px-12 border-b border-ttn-border">
            <div class="flex items-center gap-2.5">
                <x-brand-logo size="h-8 w-8" />
                <span class="font-display text-[13.5px] sm:text-[15px] font-extrabold">{{ __('landing.brand') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <x-theme-toggle />
                <x-language-switcher />
            </div>
        </header>

        <div class="mx-auto max-w-[1180px] grid grid-cols-1 md:grid-cols-[1.05fr_0.95fr] gap-7 px-4 sm:px-6 md:px-12 pt-6 pb-16">
            {{-- Hero copy --}}
            <div>
                <div class="inline-flex items-center gap-1.5 rounded-full bg-ttn-primary-light px-3 py-1.5 text-xs font-bold text-ttn-primary-dark mb-2.5">
                    <span>●</span> {{ __('landing.badge') }}
                </div>
                <h1 class="font-display text-2xl sm:text-3xl font-extrabold leading-tight tracking-tight mb-2">
                    {{ __('landing.headline') }}
                </h1>
                <p class="text-[14.5px] leading-relaxed text-ttn-text2 mb-4 max-w-[460px]">
                    {{ __('landing.hero_copy') }}
                </p>

                <div class="flex items-baseline gap-5 max-w-[460px] mb-3.5 flex-wrap">
                    <div>
                        <div class="font-display text-[34px] sm:text-[42px] font-extrabold leading-none text-ttn-primary-dark">600+</div>
                        <div class="text-[13px] font-bold mt-0.5">{{ __('landing.stat_schools') }}</div>
                    </div>
                    <div class="h-8.5 w-px bg-ttn-border"></div>
                    <div>
                        <div class="font-display text-3xl sm:text-4xl font-extrabold leading-none">11,000+</div>
                        <div class="text-[13px] font-bold text-ttn-text2 mt-0.5">{{ __('landing.stat_employed') }}</div>
                    </div>
                    <div class="h-8.5 w-px bg-ttn-border"></div>
                    <div>
                        <div class="font-display text-3xl sm:text-4xl font-extrabold leading-none">5+</div>
                        <div class="text-[13px] font-bold text-ttn-text2 mt-0.5">{{ __('landing.stat_countries') }}</div>
                    </div>
                </div>

                <div class="text-[11px] font-bold text-ttn-text2 uppercase tracking-wide mb-2">{{ __('landing.hiring_across') }}</div>
                <div class="flex gap-2.5 flex-wrap">
                    @foreach (__('landing.countries') as $code => $country)
                        <div title="{{ $country }}" class="flex items-center gap-1.5 rounded-full bg-ttn-subtle pl-1.5 pr-3 py-1.5">
                            <x-country-flag :code="$code" />
                            <span class="text-xs font-bold">{{ $code }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="text-[11.5px] font-medium text-ttn-text2 mt-2.5">{{ __('landing.any_country') }}</div>
            </div>

            {{-- Create profile card --}}
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 shadow-lg h-fit">
                <div class="font-display text-base font-bold mb-0.5">{{ __('landing.card_title') }}</div>
                <div class="text-xs font-medium text-ttn-text2 mb-4">{{ __('landing.card_subtitle') }}</div>

                <div class="flex rounded-lg overflow-hidden border border-ttn-border mb-4.5">
                    <button
                        @click="tab = 'first'"
                        class="flex-1 py-2.5 text-[12px] sm:text-[12.5px] font-bold cursor-pointer"
                        :class="tab === 'first' ? 'bg-ttn-primary text-white' : 'bg-ttn-card text-ttn-text2'"
                    >{{ __('landing.tab_new') }}</button>
                    <button
                        @click="tab = 'active'"
                        class="flex-1 py-2.5 text-[12px] sm:text-[12.5px] font-bold cursor-pointer"
                        :class="tab === 'active' ? 'bg-ttn-primary text-white' : 'bg-ttn-card text-ttn-text2'"
                    >{{ __('landing.tab_active') }}</button>
                </div>

                {{-- I Have a Profile --}}
                <div x-show="tab === 'active'" x-cloak>
                    <div class="text-[13px] font-semibold text-ttn-text2 mb-3.5">{{ __('landing.enter_contact') }}</div>
                    <input
                        type="text" x-model="loginContact"
                        placeholder="{{ __('landing.contact_placeholder') }}"
                        class="w-full rounded-lg border border-ttn-border px-3.5 py-3 text-sm font-medium mb-3.5"
                    >
                    <p class="text-xs text-ttn-red mb-2" x-show="loginError" x-text="loginError" x-cloak></p>
                    <button
                        @click="openOtpForLogin()" :disabled="otpSending"
                        class="w-full rounded-[10px] bg-ttn-primary py-3.5 text-[14.5px] font-bold text-white cursor-pointer disabled:opacity-60"
                    >{{ __('landing.send_otp') }}</button>
                </div>

                {{-- I'm New --}}
                <div x-show="tab === 'first'" x-cloak>
                    <template x-if="!cvParsed">
                        <div>
                            <label
                                class="block cursor-pointer rounded-xl border-2 border-dashed border-ttn-primary bg-ttn-subtle p-5 sm:p-6 text-center mb-3.5"
                                :class="cvUploading && 'pointer-events-none'"
                            >
                                <template x-if="cvUploading">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="h-7 w-7 animate-spin text-ttn-primary" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <div class="text-[13px] font-bold text-ttn-text2">{{ __('landing.cv_reading') }}</div>
                                    </div>
                                </template>
                                <template x-if="!cvUploading">
                                    <div>
                                        <div class="text-[13px] font-bold text-ttn-text2 mb-3.5">{{ __('landing.cv_drop') }}</div>
                                        <span class="inline-block rounded-lg bg-ttn-primary px-6 sm:px-7 py-3 text-sm font-bold text-white">{{ __('landing.cv_upload') }}</span>
                                    </div>
                                </template>
                                <input type="file" accept=".pdf,.docx,.doc" class="hidden" @change="uploadCv($event)">
                            </label>
                            <p class="text-xs text-ttn-red mb-2" x-show="cvError" x-text="cvError" x-cloak></p>
                            <div class="flex items-start gap-2 rounded-lg bg-ttn-subtle p-3">
                                <span class="text-sm">📱</span>
                                <span class="text-xs font-semibold leading-relaxed text-ttn-text2">
                                    {{ __('landing.whatsapp_note') }}
                                </span>
                            </div>
                        </div>
                    </template>

                    <template x-if="cvParsed">
                        <div>
                            <div class="text-[13px] font-bold text-ttn-primary-dark mb-3" x-text="parsedFullName ? T.cv_parsed : T.cv_confirm_manual"></div>
                            <div class="flex flex-col gap-2.5 mb-4.5">
                                <div class="flex justify-between items-center gap-2 rounded-lg bg-ttn-subtle px-3.5 py-2.5 text-[13.5px] font-medium">
                                    <span class="text-ttn-text2 shrink-0">{{ __('landing.full_name') }}</span>
                                    <input x-model="parsedFullName" placeholder="{{ __('landing.full_name_placeholder') }}" class="bg-transparent font-bold text-right w-28 sm:w-44 min-w-0 focus:outline-none">
                                </div>
                                <div class="flex justify-between items-center gap-2 rounded-lg bg-ttn-subtle px-3.5 py-2.5 text-[13.5px] font-medium">
                                    <span class="text-ttn-text2 shrink-0">{{ __('landing.role') }}</span>
                                    <span class="font-bold truncate" x-text="parsedRole || '—'"></span>
                                </div>
                                <div class="flex justify-between items-center gap-2 rounded-lg border border-ttn-primary bg-ttn-primary-light px-3.5 py-2.5 text-[13.5px] font-medium">
                                    <span class="text-ttn-primary-dark shrink-0">{{ __('landing.whatsapp_number') }}</span>
                                    <input x-model="parsedPhone" placeholder="{{ __('landing.contact_placeholder') }}" class="bg-transparent font-bold text-right w-24 sm:w-40 min-w-0 focus:outline-none">
                                </div>
                            </div>
                            <button
                                @click="openOtpForSignup()" :disabled="otpSending || !parsedPhone"
                                class="w-full rounded-[10px] bg-ttn-primary py-3.5 text-[14.5px] font-bold text-white mb-3 cursor-pointer disabled:opacity-60"
                            >{!! __('landing.confirm_verify') !!}</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Journey steps --}}
        <div class="mx-auto max-w-[1180px] px-4 sm:px-6 md:px-12 pb-12">
            <div class="font-display text-lg sm:text-xl font-extrabold text-center mb-5">{{ __('landing.steps_title') }}</div>
            <div class="flex items-end justify-center flex-wrap gap-y-6">
                @foreach ([
                    ['n' => 1, 'icon' => '📄', 'label' => __('landing.step_upload')],
                    ['n' => 2, 'icon' => '✨', 'label' => __('landing.step_ai_profile')],
                    ['n' => 3, 'icon' => '🎯', 'label' => __('landing.step_apply')],
                    ['n' => 4, 'icon' => '📋', 'label' => __('landing.step_shortlisted')],
                    ['n' => 5, 'icon' => '🎉', 'label' => __('landing.step_hired')],
                ] as $i => $step)
                    <div class="flex items-center flex-1 max-w-[200px] min-w-[110px]">
                        <div class="flex flex-col items-center gap-2.5 w-28" :class="{{ $i % 2 === 1 ? "'−translate-y-3.5'" : "''" }}">
                            <div class="relative h-14 w-14">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-ttn-primary text-white font-display text-2xl font-extrabold shadow-md">{{ $step['n'] }}</div>
                                <div class="absolute -bottom-1 -right-1 flex h-6.5 w-6.5 items-center justify-center rounded-full border-2 border-ttn-bg bg-ttn-card text-sm">{{ $step['icon'] }}</div>
                            </div>
                            <div class="text-center text-[12.5px] font-bold leading-tight">{{ $step['label'] }}</div>
                        </div>
                        @if (!$loop->last)
                            <div class="flex-1 h-0.5 mx-[-4px] mb-7 bg-[repeating-linear-gradient(90deg,var(--color-ttn-primary-light)_0,var(--color-ttn-primary-light)_6px,transparent_6px,transparent_12px)]"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Privacy grid --}}
        <div class="mx-auto max-w-[1180px] px-4 sm:px-6 md:px-12 pb-16">
            <div class="rounded-2xl bg-ttn-navy p-5 sm:p-6 md:p-7 text-white grid grid-cols-1 md:grid-cols-2 gap-3 gap-x-6">
                @foreach ([
                    __('landing.privacy_1'),
                    __('landing.privacy_2'),
                    __('landing.privacy_3'),
                    __('landing.privacy_4'),
                ] as $point)
                    <div class="flex gap-2 items-start">
                        <span class="text-ttn-amber font-bold">✓</span>
                        <span class="text-[13px] leading-relaxed opacity-90">{{ $point }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- OTP modal --}}
        <div x-show="otpOpen" x-cloak @click.self="closeOtp()" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
            <div @click.stop class="w-[360px] max-w-full rounded-2xl bg-ttn-card p-6 sm:p-8">
                <template x-if="otpStage === 'code'">
                    <div>
                        <div class="font-display text-base font-bold mb-1.5">{{ __('landing.otp_verify_title') }}</div>
                        <div class="text-[13px] text-ttn-text2 mb-4.5">{{ __('landing.otp_code_sent_to') }} <span x-text="otpContact"></span></div>
                        <input
                            x-model="otpCode" maxlength="6" placeholder="000000" autocomplete="one-time-code"
                            class="w-full rounded-[10px] border border-ttn-border px-3.5 py-3.5 text-center font-mono text-xl font-bold tracking-[0.4em] mb-4"
                        >
                        <p class="text-xs text-ttn-red mb-2 text-center" x-show="otpError" x-text="otpError" x-cloak></p>
                        <button
                            @click="verifyOtp()" :disabled="otpVerifying"
                            class="w-full rounded-[10px] bg-ttn-primary py-3 text-sm font-bold text-white mb-2.5 cursor-pointer disabled:opacity-60"
                        >{{ __('landing.otp_verify') }}</button>
                        <div @click="resendOtp()" class="text-center text-xs font-bold text-ttn-primary-dark cursor-pointer">{{ __('landing.otp_resend') }}</div>
                    </div>
                </template>
                <template x-if="otpStage === 'success'">
                    <div>
                        <div class="mx-auto mb-4 flex h-13 w-13 items-center justify-center rounded-full bg-ttn-primary-light text-2xl text-ttn-primary-dark">✓</div>
                        <div class="font-display text-base font-bold text-center mb-1.5">{{ __('landing.otp_verified') }}</div>
                        <div class="text-[13px] text-ttn-text2 text-center mb-5">{{ __('landing.otp_redirecting') }}</div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        const T = @json(__('landing.js'));

        function landingPage() {
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
                tab: 'first',
                cvUploading: false,
                cvParsed: false,
                cvError: null,
                parsedFullName: '',
                parsedRole: '',
                parsedPhone: '',
                loginContact: '',
                loginError: null,
                otpOpen: false,
                otpStage: 'code',
                otpPurpose: 'login',
                otpContact: '',
                otpCode: '',
                otpError: null,
                otpSending: false,
                otpVerifying: false,

                async uploadCv(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.cvUploading = true;
                    this.cvError = null;

                    const form = new FormData();
                    form.append('cv', file);

                    try {
                        const res = await fetch('{{ route('onboarding.cv') }}', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: form,
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            this.cvError = data.message || T.cv_error_generic;
                            return;
                        }
                        this.parsedFullName = data.full_name || '';
                        this.parsedRole = data.role || '';
                        this.parsedPhone = data.phone || '';
                        this.cvParsed = true;
                    } catch (e) {
                        this.cvError = T.upload_failed;
                    } finally {
                        this.cvUploading = false;
                    }
                },

                async openOtpForLogin() {
                    this.loginError = null;
                    if (!this.loginContact) {
                        this.loginError = T.enter_contact_first;
                        return;
                    }
                    this.otpPurpose = 'login';
                    this.otpContact = this.loginContact;
                    await this.sendOtp();
                },

                async openOtpForSignup() {
                    this.otpPurpose = 'signup';
                    this.otpContact = this.parsedPhone;
                    await this.sendOtp();
                },

                async sendOtp() {
                    this.otpSending = true;
                    this.otpError = null;
                    const { ok, data } = await postJson('{{ route('otp.send') }}', {
                        phone_or_email: this.otpContact,
                        purpose: this.otpPurpose,
                    });
                    this.otpSending = false;

                    if (!ok) {
                        if (this.otpPurpose === 'login') {
                            this.loginError = data.message || T.could_not_send_code;
                        } else {
                            this.cvError = data.message || T.could_not_send_code;
                        }
                        return;
                    }

                    this.otpStage = 'code';
                    this.otpCode = '';
                    this.otpOpen = true;
                },

                async resendOtp() {
                    await postJson('{{ route('otp.resend') }}', {
                        phone_or_email: this.otpContact,
                        purpose: this.otpPurpose,
                    });
                },

                async verifyOtp() {
                    this.otpVerifying = true;
                    this.otpError = null;
                    const { ok, data } = await postJson('{{ route('otp.verify') }}', {
                        phone_or_email: this.otpContact,
                        code: this.otpCode,
                        purpose: this.otpPurpose,
                        full_name: this.otpPurpose === 'signup' ? this.parsedFullName : undefined,
                    });
                    this.otpVerifying = false;

                    if (!ok) {
                        this.otpError = data.message || T.invalid_code;
                        return;
                    }

                    this.otpStage = 'success';
                    setTimeout(() => { window.location.href = data.redirect; }, 900);
                },

                closeOtp() {
                    this.otpOpen = false;
                },
            };
        }
    </script>
</x-layout>
