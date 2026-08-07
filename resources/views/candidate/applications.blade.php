<x-candidate-shell :candidate="$candidate" active="applications" :title="__('applications.title')" :subtitle="__('applications.subtitle')">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">
            {{ session('status') }}
        </div>
    @endif

    @if (!$hasApplications)
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6 sm:p-10 text-center">
            <div class="font-display text-[15px] font-bold mb-1.5">{{ __('applications.no_active') }}</div>
            <div class="text-[13px] text-ttn-text2 mb-5">{{ __('applications.no_active_desc') }}</div>
            <div class="flex gap-2.5 justify-center flex-wrap">
                <a href="{{ route('candidate.jobs') }}" class="rounded-lg bg-ttn-primary px-4 py-2.5 text-[13px] font-bold text-white">{{ __('applications.see_jobs') }}</a>
                <a href="{{ route('candidate.profile') }}" class="rounded-lg border border-ttn-border px-4 py-2.5 text-[13px] font-bold text-ttn-text2">{{ __('applications.improve_profile') }}</a>
                <button @click="$store.careerReadiness.show()" class="rounded-lg border border-ttn-border px-4 py-2.5 text-[13px] font-bold text-ttn-text2 cursor-pointer">{{ __('applications.grow_career') }}</button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-4 mb-5">
            {{-- Left: grouped lists --}}
            <div class="flex lg:flex-col gap-4 overflow-x-auto lg:overflow-visible pb-1 lg:pb-0">
                @if ($attention->isNotEmpty())
                    <div class="shrink-0 w-[240px] lg:w-auto">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-amber-text mb-2">{{ __('applications.needs_attention') }}</div>
                        <div class="flex flex-col gap-2">
                            @foreach ($attention as $app)
                                @php $isSelected = $selected['uuid'] === $app['uuid']; @endphp
                                <div class="rounded-lg border {{ $isSelected ? 'border-ttn-primary' : 'border-ttn-border' }} border-l-4 {{ $isSelected ? 'bg-ttn-primary-light' : 'bg-ttn-card' }}">
                                    <a href="{{ route('candidate.applications.index', ['selected' => $app['uuid']]) }}" class="text-left p-3 block">
                                        <div class="text-[13px] font-bold mb-0.5">{{ $app['title'] }}</div>
                                        <div class="text-[11px] text-ttn-text2">{{ $app['school'] }} &middot; {{ $app['status_label'] }}</div>
                                    </a>
                                    <x-withdraw-modal :app="$app" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($waiting->isNotEmpty())
                    <div class="shrink-0 w-[240px] lg:w-auto">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2">{{ __('applications.waiting_for_school') }}</div>
                        <div class="flex flex-col gap-2">
                            @foreach ($waiting as $app)
                                @php $isSelected = $selected['uuid'] === $app['uuid']; @endphp
                                <div class="rounded-lg border {{ $isSelected ? 'border-ttn-primary' : 'border-ttn-border' }} {{ $isSelected ? 'bg-ttn-primary-light' : 'bg-ttn-card' }}">
                                    <a href="{{ route('candidate.applications.index', ['selected' => $app['uuid']]) }}" class="text-left p-3 block">
                                        <div class="text-[13px] font-bold mb-0.5">{{ $app['title'] }}</div>
                                        <div class="text-[11px] text-ttn-text2">{{ $app['school'] }} &middot; {{ $app['status_label'] }}</div>
                                        <div class="text-[10.5px] text-ttn-text2 mt-1 opacity-80">{{ __('applications.applied_on', ['date' => $app['applied_on']]) }}</div>
                                    </a>
                                    <x-withdraw-modal :app="$app" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($completed->isNotEmpty())
                    <div x-data="{ open: false }" class="shrink-0 w-[240px] lg:w-auto">
                        <button @click="open = !open" class="w-full text-left text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2 cursor-pointer">
                            {{ __('applications.completed', ['count' => $completed->count()]) }} <span x-text="open ? '▲' : '▼'"></span>
                        </button>
                        <div x-show="open" x-cloak class="flex flex-col gap-2">
                            @foreach ($completed as $app)
                                @php $isSelected = $selected['uuid'] === $app['uuid']; @endphp
                                <div class="rounded-lg border {{ $isSelected ? 'border-ttn-primary' : 'border-ttn-border' }} {{ $isSelected ? 'bg-ttn-primary-light' : 'bg-ttn-card' }}">
                                    <a href="{{ route('candidate.applications.index', ['selected' => $app['uuid']]) }}" class="text-left p-3 block">
                                        <div class="text-[13px] font-bold mb-0.5">{{ $app['title'] }}</div>
                                        <div class="text-[11px] text-ttn-text2">{{ $app['school'] }} &middot; {{ $app['status_label'] }}</div>
                                    </a>
                                    {{-- Hired shows Manage so the candidate sees the
                                         "contact the school directly" guidance; a
                                         rejected application has nothing to withdraw
                                         from, so it gets no Manage button at all. --}}
                                    @if (!$app['is_rejected'])
                                        <x-withdraw-modal :app="$app" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: detail panel --}}
            <div class="flex flex-col gap-3.5 min-w-0">
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6">
                    <div class="font-display text-base font-bold mb-0.5">{{ $selected['title'] }}</div>
                    <div class="text-[13px] text-ttn-text2 mb-5">{{ $selected['school'] }} @if($selected['location']) &middot; {{ $selected['location'] }} @endif &middot; {{ __('applications.applied_on', ['date' => $selected['applied_on']]) }}</div>

                    {{-- Stage tracker --}}
                    <div class="flex items-center mb-5 overflow-x-auto -mx-1 px-1">
                        @foreach ($selected['steps'] as $i => $step)
                            <div class="flex items-center flex-1 min-w-[64px]">
                                <div class="flex flex-col items-center gap-1.5">
                                    <div class="flex h-6.5 w-6.5 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                         style="background: {{ $step['reached'] ? 'var(--color-ttn-primary-dark)' : 'var(--color-ttn-border)' }}">
                                        {{ $step['mark'] }}
                                    </div>
                                    <div class="text-[10.5px] sm:text-[11px] font-semibold text-center max-w-[70px]" style="color: {{ $step['reached'] ? 'var(--color-ttn-text)' : 'var(--color-ttn-text2)' }}">
                                        {{ $step['label'] }}
                                    </div>
                                </div>
                                @if (!$loop->last)
                                    <div class="flex-1 h-0.5 mx-1 mb-6 min-w-[16px]" style="background: {{ $step['reached'] ? 'var(--color-ttn-primary-dark)' : 'var(--color-ttn-border)' }}"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($selected['is_rejected'])
                        <div class="rounded-lg bg-ttn-red-bg px-4 py-3 mb-3.5">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-red mb-1">{{ __('applications.status') }}</div>
                            <div class="text-[12.5px] text-ttn-red">{{ $selected['stage_context'] }}</div>
                        </div>
                    @else
                        <div class="rounded-lg bg-ttn-subtle px-4 py-3 mb-3.5">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-1">{{ __('applications.current_stage', ['status' => $selected['status_label']]) }}</div>
                            <div class="text-[12.5px] text-ttn-text2">{{ $selected['stage_context'] }}</div>
                            @if ($selected['has_interview'])
                                <div class="flex flex-wrap gap-2 mt-2.5">
                                    <span class="rounded-full border border-ttn-border bg-ttn-card px-2.5 py-1 text-[11px] font-semibold">{{ __('applications.interview', ['date' => $selected['interview_date']]) }}</span>
                                    @if ($selected['interview_time'])
                                        <span class="rounded-full border border-ttn-border bg-ttn-card px-2.5 py-1 text-[11px] font-semibold">{{ __('applications.interview_time', ['x' => $selected['interview_time']]) }}</span>
                                    @endif
                                    @if ($selected['interview_type'])
                                        <span class="rounded-full border border-ttn-border bg-ttn-card px-2.5 py-1 text-[11px] font-semibold">{{ __('applications.interview_type', ['x' => $selected['interview_type']]) }}</span>
                                    @endif
                                    @if ($selected['interview_duration'])
                                        <span class="rounded-full border border-ttn-border bg-ttn-card px-2.5 py-1 text-[11px] font-semibold">{{ __('applications.interview_duration', ['x' => $selected['interview_duration']]) }}</span>
                                    @endif
                                    @if ($selected['interview_location'])
                                        <span class="rounded-full border border-ttn-border bg-ttn-card px-2.5 py-1 text-[11px] font-semibold">{{ __('applications.interview_location', ['x' => $selected['interview_location']]) }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-lg bg-ttn-primary-light px-4 py-3 mb-3.5">
                        <div class="flex justify-between items-center gap-3 flex-wrap">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-primary-dark mb-0.5">{{ __('applications.next_action') }}</div>
                                <div class="text-[13.5px] font-bold text-ttn-primary-dark">{{ $selected['next_action_label'] }}</div>
                                <div class="text-[11.5px] text-ttn-primary-dark mt-0.5">{{ $selected['next_action_sub'] }}</div>
                            </div>
                        </div>

                        @if ($selected['awaiting_interview_response'])
                            <div x-data="{ noting: false, note: '' }" class="mt-3 pt-3 border-t border-ttn-primary-dark/20">
                                <div x-show="noting" x-cloak>
                                    <textarea form="interview-response-form" name="note" rows="2" maxlength="500" placeholder="{{ __('applications.interview_note_placeholder') }}" x-model="note" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px] bg-ttn-card mb-2"></textarea>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <form id="interview-response-form" method="POST" action="{{ route('candidate.applications.interview-response', $selected['uuid']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('applications.confirm_attendance') }}</button>
                                    </form>
                                    <button type="button" @click="noting = !noting" class="rounded-lg border border-ttn-primary-dark/30 px-3 py-2 text-[11.5px] font-bold text-ttn-primary-dark cursor-pointer">
                                        <span x-text="noting ? @js(__('common.cancel')) : @js(__('applications.add_note'))"></span>
                                    </button>
                                    <x-withdraw-modal :app="$selected" :label="__('applications.cant_attend')" :compact="true" />
                                </div>
                            </div>
                        @elseif ($selected['interview_response'] === 'confirmed')
                            <div class="mt-3 pt-3 border-t border-ttn-primary-dark/20 text-[12.5px] font-semibold text-ttn-primary-dark">
                                ✓ {{ __('applications.attendance_confirmed') }}
                                @if ($selected['interview_response_note'])
                                    <div class="text-[11.5px] font-normal text-ttn-text2 mt-1">"{{ $selected['interview_response_note'] }}"</div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="rounded-lg border border-ttn-border p-4 mb-3.5">
                        <div class="text-[12.5px] font-bold mb-2">{{ $selected['companion']['title'] }}</div>
                        <div class="flex flex-col gap-1.5">
                            @foreach ($selected['companion']['items'] as $item)
                                <div class="text-[12.5px] leading-relaxed text-ttn-text2">&bull; {{ $item }}</div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-2.5 gap-2 flex-wrap">
                        <div class="text-[12.5px] font-bold">{{ __('applications.hiring_confidence') }}</div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold
                                {{ $selected['health_label'] === 'High' ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($selected['health_label'] === 'Medium' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                                {{ $selected['health_label'] }}
                            </span>
                            <span class="font-display text-[15px] font-extrabold">{{ $selected['health_score'] }}%</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        @foreach ($selected['health_why'] as $why)
                            <span class="rounded-full bg-ttn-subtle px-2.5 py-1 text-[11px] font-semibold text-ttn-text2">✓ {{ $why }}</span>
                        @endforeach
                    </div>
                    @if ($selected['health_missing'])
                        <div class="text-xs font-medium text-ttn-amber-text">{{ __('applications.missing_hint', ['x' => $selected['health_missing']]) }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
            <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('applications.hiring_journey') }}</div>
            <div class="flex gap-7 flex-wrap">
                @foreach ($progressStats as $stat)
                    <div>
                        <div class="font-display text-2xl font-extrabold">{{ $stat['value'] }}</div>
                        <div class="text-xs font-semibold text-ttn-text2 mt-0.5">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($withdrawn->isNotEmpty())
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-5 mt-5">
            <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-1">{{ __('applications.history_title') }}</div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-3">{{ __('applications.withdrawn_section') }}</div>
            <div class="flex flex-col gap-2.5">
                @foreach ($withdrawn as $app)
                    <div class="rounded-lg border border-ttn-border p-3.5">
                        <div class="flex justify-between items-start gap-2.5 flex-wrap">
                            <div>
                                <div class="text-[13px] font-bold mb-0.5">{{ $app['title'] }}</div>
                                <div class="text-[11px] text-ttn-text2">{{ $app['school'] }}</div>
                            </div>
                            <span class="shrink-0 rounded-full bg-ttn-subtle px-2.5 py-1 text-[10.5px] font-bold text-ttn-text2">{{ __('applications.withdrawn_on', ['date' => $app['withdrawn_on']]) }}</span>
                        </div>
                        <div class="text-[11.5px] text-ttn-text2 mt-1.5">
                            {{ $app['reason'] === 'other' ? $app['reason_other'] : __('applications.reason_' . $app['reason']) }}
                        </div>
                        @if ($app['can_reapply'])
                            <form method="POST" action="{{ route('candidate.applications.apply') }}" class="mt-2.5">
                                @csrf
                                <input type="hidden" name="source_schema" value="{{ $app['source_schema'] }}">
                                <input type="hidden" name="job_posting_id" value="{{ $app['source_job_posting_id'] }}">
                                <button class="rounded-lg bg-ttn-primary px-3.5 py-1.5 text-[11.5px] font-bold text-white cursor-pointer">{{ __('applications.apply_again') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($offerPromptApplicationId)
        <div x-data="{ open: true }" x-show="open" x-cloak @click.self="open = false" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-3 sm:p-5">
            <div @click.stop class="w-[420px] max-w-full rounded-2xl bg-ttn-card p-5 sm:p-7 text-ttn-text">
                <div class="font-display text-base font-bold mb-1">{{ __('applications.offer_prompt_title') }}</div>
                <div class="text-[12.5px] text-ttn-text2 mb-4">{{ __('applications.offer_prompt_desc') }}</div>
                <form method="POST" action="{{ route('candidate.applications.withdrawal-details', $offerPromptApplicationId) }}" class="flex flex-col gap-2.5">
                    @csrf
                    <div>
                        <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1">{{ __('applications.offer_employer') }}</div>
                        <input name="new_employer_name" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                    </div>
                    <div>
                        <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1">{{ __('applications.offer_position') }}</div>
                        <input name="new_position" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                    </div>
                    <div>
                        <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1">{{ __('applications.offer_start_date') }}</div>
                        <input name="new_start_date" type="date" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                    </div>
                    <div>
                        <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('applications.offer_found_via') }}</div>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1.5 text-[12.5px] font-semibold cursor-pointer">
                                <input type="radio" name="found_via_shulesoft" value="1" class="h-4 w-4"> {{ __('common.yes') }}
                            </label>
                            <label class="flex items-center gap-1.5 text-[12.5px] font-semibold cursor-pointer">
                                <input type="radio" name="found_via_shulesoft" value="0" class="h-4 w-4"> {{ __('common.no') }}
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-2">
                        <button type="button" @click="open = false" class="rounded-lg border border-ttn-border px-4 py-2.5 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('applications.skip') }}</button>
                        <button type="submit" class="flex-1 rounded-lg bg-ttn-primary px-4 py-2.5 text-[12.5px] font-bold text-white cursor-pointer">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-candidate-shell>
