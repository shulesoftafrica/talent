@props(['app'])

<div x-data="{ manageOpen: false, step: 'choice', reason: '' }" class="px-3 pb-2.5 -mt-1">
    <button @click.prevent="manageOpen = true" type="button" class="text-[10.5px] font-bold text-ttn-text2 hover:text-ttn-primary-dark cursor-pointer">
        {{ __('applications.manage') }}
    </button>

    <div x-show="manageOpen" x-cloak @click.self="manageOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-3 sm:p-5">
        <div @click.stop class="w-[440px] max-w-full max-h-[85vh] overflow-y-auto rounded-2xl bg-ttn-card p-5 sm:p-7 text-ttn-text">

            {{-- Step 1: continue or withdraw --}}
            <template x-if="step === 'choice'">
                <div>
                    <div class="font-display text-base font-bold mb-1">{{ __('applications.manage_title') }}</div>
                    <div class="text-[12.5px] text-ttn-text2 mb-4">{{ $app['title'] }}</div>

                    @if ($app['can_withdraw'])
                        <div class="flex flex-col gap-2.5">
                            <button @click="manageOpen = false" type="button" class="rounded-lg bg-ttn-primary px-4 py-3 text-[13px] font-bold text-white cursor-pointer">
                                {{ __('applications.continue_application') }}
                            </button>
                            <button @click="step = 'reason'" type="button" class="rounded-lg border border-ttn-border px-4 py-3 text-[13px] font-bold text-ttn-red cursor-pointer">
                                {{ __('applications.withdraw_application') }}
                            </button>
                        </div>
                    @else
                        <div class="rounded-lg bg-ttn-amber-bg px-3.5 py-3 text-[12.5px] text-ttn-amber-text mb-4">
                            {{ __('applications.hired_no_withdraw') }}
                        </div>
                        <button @click="manageOpen = false" type="button" class="w-full rounded-lg bg-ttn-primary px-4 py-3 text-[13px] font-bold text-white cursor-pointer">
                            {{ __('common.close') }}
                        </button>
                    @endif
                </div>
            </template>

            @if ($app['can_withdraw'])
                <form method="POST" action="{{ route('candidate.applications.withdraw', $app['uuid']) }}">
                    @csrf

                    {{--
                        Steps 2 & 3 share ONE form whose reason radios must
                        survive the step transition, so this uses x-show (DOM
                        stays mounted) rather than x-if/template (which would
                        tear the checked radio out of the DOM before submit).
                    --}}
                    <div x-show="step === 'reason'" x-cloak>
                        <div class="font-display text-base font-bold mb-1">{{ __('applications.withdraw_title') }}</div>
                        <div class="text-[12px] text-ttn-text2 mb-4 rounded-lg bg-ttn-subtle px-3 py-2.5">{{ __('applications.withdraw_privacy_note') }}</div>
                        <div class="flex flex-col gap-2 mb-3">
                            @foreach (\App\Services\Applications\WithdrawalReason::KEYS as $key)
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-[12.5px] font-semibold cursor-pointer"
                                       :class="reason === '{{ $key }}' ? 'border-ttn-primary bg-ttn-primary-light' : 'border-ttn-border'">
                                    <input type="radio" name="reason" value="{{ $key }}" x-model="reason" required class="h-4 w-4 shrink-0">
                                    {{ __('applications.reason_' . $key) }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="reason === 'other'" x-cloak>
                            <textarea name="reason_other" rows="2" maxlength="500" placeholder="{{ __('applications.reason_other_placeholder') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px] mb-3"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="step = 'choice'" class="rounded-lg border border-ttn-border px-4 py-2.5 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('common.cancel') }}</button>
                            <button type="button" @click="if (reason) step = 'confirm'" class="flex-1 rounded-lg bg-ttn-primary px-4 py-2.5 text-[12.5px] font-bold text-white cursor-pointer">{{ __('common.continue') }}</button>
                        </div>
                    </div>

                    <div x-show="step === 'confirm'" x-cloak>
                        <div class="font-display text-base font-bold mb-1">{{ __('applications.confirm_withdraw_title') }}</div>
                        <div class="text-[12.5px] text-ttn-text2 mb-4">{{ __('applications.confirm_withdraw_desc') }}</div>
                        <div class="flex gap-2">
                            <button type="button" @click="step = 'reason'" class="rounded-lg border border-ttn-border px-4 py-2.5 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('common.cancel') }}</button>
                            <button type="submit" class="flex-1 rounded-lg bg-ttn-red px-4 py-2.5 text-[12.5px] font-bold text-white cursor-pointer">{{ __('applications.confirm_withdraw_button') }}</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
