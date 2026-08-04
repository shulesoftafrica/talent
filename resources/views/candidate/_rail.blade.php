@php
    $verificationItems = $candidate->verificationItems()->with('verificationType')->get()->keyBy(fn ($i) => $i->verificationType->key);
    $unreadNotifications = $candidate->notifications()->whereNull('read_at')->latest()->take(6)->get();
@endphp

<div x-data="{ notifOpen: false }" class="relative flex justify-end">
    <button @click="notifOpen = !notifOpen" class="relative flex h-8 w-8 items-center justify-center rounded-full border border-ttn-border bg-ttn-card cursor-pointer">
        🔔
        @if ($unreadNotifications->isNotEmpty())
            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-ttn-red text-[9px] font-bold text-white">
                {{ $unreadNotifications->count() }}
            </span>
        @endif
    </button>
    <div x-show="notifOpen" x-cloak @click.outside="notifOpen = false"
         class="absolute top-10 right-0 z-20 w-64 max-w-[80vw] rounded-xl border border-ttn-border bg-ttn-card p-2 shadow-lg">
        @forelse ($unreadNotifications as $note)
            <a href="{{ route('candidate.notifications.open', $note) }}" class="block rounded-lg px-2.5 py-2 text-[11.5px] font-semibold hover:bg-ttn-subtle">
                🔔 {{ $note->title }}
            </a>
        @empty
            <div class="px-2.5 py-2 text-xs font-medium text-ttn-text2">{{ __('rail.no_notifications') }}</div>
        @endforelse
    </div>
</div>

<div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
    <div class="text-xs font-bold text-ttn-text2 uppercase tracking-wide mb-3.5">{{ __('rail.your_summary') }}</div>
    <div class="flex items-center gap-3 mb-4">
        <div class="flex h-13 w-13 items-center justify-center rounded-full font-display text-[13px] font-extrabold"
             style="background: conic-gradient(var(--color-ttn-primary) {{ $candidate->career_score }}%, var(--color-ttn-track) 0)">
            <div class="flex h-[41px] w-[41px] items-center justify-center rounded-full bg-ttn-card">{{ $candidate->career_score }}</div>
        </div>
        <div>
            <div class="text-[13px] font-bold">{{ __('rail.career_score') }}</div>
            <a href="{{ route('candidate.jobs') }}" class="text-[11.5px] font-bold text-ttn-primary-dark">{{ __('rail.view_plan') }}</a>
        </div>
    </div>

    <div class="flex flex-col gap-2.5">
        <div class="pt-2.5 border-t border-ttn-hairline flex justify-between items-center">
            <span class="text-[12.5px] font-semibold">{{ __('rail.trust_score') }}</span>
            <span class="text-[12.5px] font-bold text-ttn-primary-dark">{{ $candidate->trust_score }}/100</span>
        </div>
        <div class="pt-2.5 border-t border-ttn-hairline">
            <div class="flex justify-between mb-1.5">
                <span class="text-[12.5px] font-semibold">{{ __('rail.profile_strength') }}</span>
                <span class="text-[12.5px] font-bold">{{ $candidate->profile_strength }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-ttn-track">
                <div class="h-full rounded-full bg-ttn-primary" style="width: {{ $candidate->profile_strength }}%"></div>
            </div>
        </div>
    </div>
</div>

<div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
    <div class="flex justify-between items-center mb-1">
        <div class="text-xs font-bold text-ttn-text2 uppercase tracking-wide">{{ __('rail.grow_your_career') }}</div>
        <span class="text-sm">🚀</span>
    </div>
    <div class="text-[12.5px] text-ttn-text2 mb-3">{{ __('rail.grow_your_career_desc') }}</div>
    <button @click="$store.careerReadiness.show()" class="w-full rounded-lg bg-ttn-primary px-4 py-2.5 text-xs font-bold text-white cursor-pointer">{{ __('rail.view_growth_plan') }}</button>
</div>

@if (config('services.verification_enabled'))
<div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
    <div class="text-xs font-bold text-ttn-text2 uppercase tracking-wide mb-3">{{ __('rail.verification_progress') }}</div>
    <div class="flex flex-col gap-1">
        @foreach (['identity' => __('rail.verification_identity'), 'education' => __('rail.verification_education'), 'employment_history' => __('rail.verification_employment'), 'professional_license' => __('rail.verification_license')] as $key => $label)
            @php
                $item = $verificationItems->get($key);
                $isVerified = $item?->status === \App\Services\Verification\VerificationStatus::VERIFIED;
                $statusLabel = $item ? \App\Services\Verification\VerificationStatus::label($item->status) : __('common.not_verified');
            @endphp
            <a href="{{ route('candidate.verification.show') }}" class="flex justify-between items-center py-1.5 text-[12.5px] font-semibold">
                <span>{{ $label }}</span>
                <span class="{{ $isVerified ? 'text-ttn-primary-dark font-bold' : 'text-ttn-amber-text font-bold' }}">
                    {{ $isVerified ? __('common.verified') : $statusLabel }}
                </span>
            </a>
        @endforeach
    </div>
    <div class="text-[11px] text-ttn-text2 mt-2.5">{{ __('rail.verification_footer') }}</div>
</div>
@endif

<div x-data x-show="$store.careerReadiness.open" x-cloak
     @click.self="$store.careerReadiness.close()"
     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-3 sm:p-5">
    <div @click.stop class="w-[560px] max-w-full max-h-[85vh] overflow-y-auto rounded-2xl bg-ttn-card p-5 sm:p-7 text-ttn-text">
        <div class="font-display text-base font-bold mb-1">{{ __('rail.modal_title') }}</div>
        <div class="text-[12.5px] text-ttn-text2 mb-4" x-show="!$store.careerReadiness.loading && !$store.careerReadiness.error">
            {{ __('rail.modal_desc') }}
        </div>
        <div x-show="$store.careerReadiness.loading" class="text-[13px] text-ttn-text2">{{ __('common.loading') }}</div>
        <div x-show="$store.careerReadiness.error" x-text="$store.careerReadiness.error" class="text-[13px] text-ttn-red"></div>

        <template x-if="$store.careerReadiness.data">
            <div class="flex flex-col gap-5">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2">{{ __('rail.recommended_trainings') }}</div>
                    <div class="flex flex-col gap-2.5">
                        <template x-for="training in $store.careerReadiness.data.recommendations" :key="training.id">
                            <div class="rounded-xl border border-ttn-border p-3.5">
                                <div class="flex justify-between items-start gap-2.5 mb-1 flex-wrap">
                                    <span class="text-[12.5px] font-bold" x-text="training.title"></span>
                                    <span class="whitespace-nowrap rounded-full bg-ttn-subtle px-2 py-0.5 text-[10px] font-bold text-ttn-primary-dark" x-text="training.priority"></span>
                                </div>
                                <div class="text-[11.5px] text-ttn-text2 mb-2" x-text="training.why"></div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 mb-2.5">
                                    <template x-for="m in training.meta" :key="m.k">
                                        <span class="text-[11px] text-ttn-text2"><span class="font-semibold" x-text="m.k"></span>: <span x-text="m.v"></span></span>
                                    </template>
                                </div>
                                {{-- Academy LMS courses link out to the course page; talent trainings enrol in-place. --}}
                                <template x-if="training.url">
                                    <a :href="training.url" target="_blank" rel="noopener"
                                       class="inline-block rounded-lg bg-ttn-primary px-3.5 py-2 text-[11.5px] font-bold text-white cursor-pointer">
                                        {{ __('rail.view_course') }}
                                    </a>
                                </template>
                                <template x-if="!training.url">
                                    <div>
                                        <button
                                            x-show="!training.completed"
                                            @click="$store.careerReadiness.enroll(training.id)"
                                            :disabled="training.enrolled"
                                            :class="training.enrolled ? 'bg-ttn-subtle text-ttn-text2 cursor-default' : 'bg-ttn-primary text-white cursor-pointer'"
                                            class="rounded-lg px-3.5 py-2 text-[11.5px] font-bold"
                                            x-text="training.enrolled ? @js(__('rail.enrolled')) : @js(__('rail.enroll'))">
                                        </button>
                                        <span x-show="training.completed" class="text-[11.5px] font-bold text-ttn-primary-dark">{{ __('rail.completed') }} <span x-text="training.certificate_id"></span></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="$store.careerReadiness.data.nextSteps.length">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2">{{ __('rail.next_steps') }}</div>
                    <div class="flex flex-col gap-1.5">
                        <template x-for="step in $store.careerReadiness.data.nextSteps" :key="step.label">
                            <div class="flex justify-between items-center gap-2 rounded-lg bg-ttn-subtle px-3.5 py-2.5">
                                <span class="text-[12.5px] font-semibold" x-text="step.label"></span>
                                <span class="text-xs font-bold text-ttn-primary-dark whitespace-nowrap" x-text="step.impact"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="$store.careerReadiness.data.completedCerts.length">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2">{{ __('rail.verified_certifications') }}</div>
                    <div class="flex flex-col gap-1.5">
                        <template x-for="cert in $store.careerReadiness.data.completedCerts" :key="cert.name">
                            <div class="text-[12.5px] font-semibold">✓ <span x-text="cert.name"></span> <span class="text-ttn-text2 font-normal" x-text="cert.issuer"></span></div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <div @click="$store.careerReadiness.close()" class="text-center text-xs font-semibold text-ttn-text2 cursor-pointer mt-5">{{ __('common.close') }}</div>
    </div>
</div>
