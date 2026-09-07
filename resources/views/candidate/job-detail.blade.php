<x-candidate-shell :candidate="$candidate" active="jobs" :title="$locked ? $title : $job['title']">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    <a href="{{ route('candidate.jobs') }}" class="text-[12.5px] font-semibold text-ttn-text2 mb-4 inline-block">&larr; {{ __('jobs.back_to_matches') }}</a>

    @if ($locked)
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-8 text-center">
            <div class="text-3xl mb-3">🔒</div>
            <div class="font-display text-base font-bold mb-1.5">{{ $title }}</div>
            <div class="text-xs text-ttn-text2 mb-4">{{ __('jobs.school_hidden') }} &middot; {{ $location }}</div>
            <div class="font-display text-[15px] font-bold mb-1.5">{{ __('jobs.detail_locked_title') }}</div>
            <div class="text-[13px] text-ttn-text2 mb-4 max-w-md mx-auto">{{ __('jobs.detail_locked_desc') }}</div>
            <a href="{{ route('candidate.premium.show') }}" class="inline-block rounded-lg bg-ttn-amber px-5 py-2.5 text-[13px] font-bold" style="color: var(--color-ttn-navy)">{{ __('jobs.unlock_premium_cta') }}</a>
        </div>
    @else
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
            <div class="flex justify-between items-start gap-3 flex-wrap mb-3">
                <div>
                    <div class="font-display text-lg font-bold">{{ $job['title'] }}</div>
                    <div class="text-[13px] text-ttn-text2 mt-1">{{ __('jobs.school_hidden') }} &middot; {{ $job['location'] }} &middot; {{ $job['salary_label'] }}</div>
                    <div class="text-[11px] text-ttn-text2 opacity-70 mt-0.5">{{ __('jobs.posted_days_ago', ['n' => $job['posted_days_ago']]) }}</div>
                </div>
                <span class="rounded-full px-3 py-1.5 text-xs font-bold whitespace-nowrap
                    {{ $job['match_score'] >= 85 ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($job['match_score'] >= 65 ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                    {{ __('jobs.match_pct', ['pct' => $job['match_score']]) }}
                </span>
            </div>

            @if ($job['deadline_days'] !== null)
                <div class="mb-3">
                    <span class="rounded-full px-3 py-1 text-[11.5px] font-extrabold
                        {{ $job['deadline_days'] <= 1 ? 'bg-ttn-red-bg text-ttn-red' : ($job['deadline_days'] <= 5 ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-primary-light text-ttn-primary-dark') }}">
                        {{ __('jobs.apply_before') }} &middot; {{ $job['deadline_days'] <= 1 ? __('jobs.apply_before_last_day') : __('jobs.apply_before_days_left', ['n' => $job['deadline_days']]) }}
                    </span>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @if ($applied)
                    <span class="rounded-lg bg-ttn-primary-light text-ttn-primary-dark px-4 py-2 text-xs font-bold">{{ __('jobs.applied') }}</span>
                @else
                    <form method="POST" action="{{ route('candidate.applications.apply') }}">
                        @csrf
                        <input type="hidden" name="source_schema" value="{{ $job['source_schema'] }}">
                        <input type="hidden" name="job_posting_id" value="{{ $job['id'] }}">
                        <button class="rounded-lg bg-ttn-primary px-4 py-2 text-xs font-bold text-white cursor-pointer">{{ __('jobs.apply') }}</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
            <div class="font-display text-[15px] font-bold mb-3">{{ __('jobs.match_breakdown_title') }}</div>
            <div class="flex flex-col gap-2 mb-1">
                @foreach ($job['reasons'] as $reason)
                    <div class="flex items-start gap-2 text-[13px]">
                        <span class="text-ttn-primary-dark font-bold">✓</span>
                        <span>{{ $reason }}</span>
                    </div>
                @endforeach
            </div>
            @if ($job['missing'])
                <div class="text-xs font-medium text-ttn-amber-text mt-2">{{ __('jobs.missing_hint', ['x' => $job['missing']]) }}</div>
            @endif
        </div>

        {{-- Verified skill match (Academy credentials → §34–§35, §48) --}}
        @if (!empty($skillMatch))
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="font-display text-[15px] font-bold">Verified skill match</div>
                    <span class="rounded-full px-3 py-1 text-[12px] font-bold whitespace-nowrap
                        {{ $skillMatch['percent'] >= 85 ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($skillMatch['percent'] >= 50 ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                        {{ $skillMatch['percent'] }}% match
                    </span>
                </div>
                <div class="text-[12.5px] text-ttn-text2 mb-3">
                    {{ $skillMatch['required_met'] }} of {{ $skillMatch['required_total'] }} required skill{{ $skillMatch['required_total'] === 1 ? '' : 's' }} matched
                </div>
                <div class="flex flex-col gap-2">
                    @foreach ($skillMatch['items'] as $it)
                        <div class="flex items-start justify-between gap-3 text-[13px]">
                            <div class="flex items-start gap-2">
                                <span class="{{ $it['met'] ? 'text-ttn-primary-dark' : 'text-ttn-text2' }} font-bold">{{ $it['met'] ? '✓' : '✗' }}</span>
                                <span>
                                    <span class="font-semibold">{{ $it['skill_name'] }}</span>
                                    <span class="text-ttn-text2">— {{ $it['min_level_name'] }}</span>
                                    <span class="text-[10.5px] font-bold uppercase tracking-wide {{ $it['requirement'] === 'required' ? 'text-ttn-text2' : 'text-ttn-text2 opacity-70' }}">· {{ $it['requirement'] }}</span>
                                    @if ($it['met'])
                                        <span class="block text-[11.5px] text-ttn-primary-dark">You: {{ $it['candidate_level'] }} ✓ verified</span>
                                    @elseif ($it['candidate_level'])
                                        <span class="block text-[11.5px] text-ttn-amber-text">You: {{ $it['candidate_level'] }} — below required level</span>
                                    @else
                                        <span class="block text-[11.5px] text-ttn-text2">Not yet verified</span>
                                    @endif
                                </span>
                            </div>
                            @unless ($it['met'])
                                <a href="{{ $it['verify_url'] }}" target="_blank" rel="noopener" class="shrink-0 rounded-lg border border-ttn-primary px-2.5 py-1 text-[11.5px] font-bold text-ttn-primary-dark">Verify →</a>
                            @endunless
                        </div>
                    @endforeach
                </div>
                @if (!empty($skillMatch['gaps']))
                    <div class="mt-3 rounded-lg bg-ttn-subtle px-3 py-2.5 text-[12px] text-ttn-text2">
                        Improve your match: verify
                        {{ collect($skillMatch['gaps'])->pluck('skill_name')->join(', ', ' and ') }}
                        on ShuleSoft Academy to move up for roles like this.
                    </div>
                @endif
            </div>
        @endif

        @forelse ($sections as $key => $text)
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
                <div class="font-display text-[15px] font-bold mb-2">{{ __('jobs.section_' . $key) }}</div>
                <div class="text-[13px] text-ttn-text2 whitespace-pre-line leading-relaxed">{{ $text }}</div>
            </div>
        @empty
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 text-[13px] text-ttn-text2">
                {{ __('jobs.no_details') }}
            </div>
        @endforelse
    @endif
</x-candidate-shell>
