<x-candidate-shell :candidate="$candidate" active="jobs" :title="__('jobs.greeting', ['name' => explode(' ', $candidate->full_name)[0]])" :subtitle="__('jobs.subtitle', ['count' => $jobs->count()])">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (!$candidate->is_premium)
        <div class="rounded-2xl p-4 text-white mb-4.5 flex items-center justify-between gap-3.5 flex-wrap" style="background: linear-gradient(135deg, var(--color-ttn-navy), oklch(0.22 0.015 260))">
            <div>
                <div class="font-display text-[13.5px] font-bold mb-0.5">{{ __('jobs.unlock_premium') }}</div>
                <div class="text-xs opacity-80">{{ __('jobs.unlock_premium_desc') }}</div>
            </div>
            <a href="{{ route('candidate.premium.show') }}" class="rounded-lg bg-ttn-amber px-4 py-2 text-xs font-bold whitespace-nowrap" style="color: var(--color-ttn-navy)">{{ __('jobs.unlock') }}</a>
        </div>
    @endif

    <div class="font-display text-sm font-bold mb-3">{{ __('jobs.matched_for_you') }}</div>

    <div class="flex flex-col gap-3 mb-4.5">
        @forelse ($jobs as $job)
            <div x-data="aiModal('{{ route('candidate.ai.job-coach', [$job['source_schema'], $job['id']]) }}')" class="rounded-xl border border-ttn-border bg-ttn-card p-4">
                <div class="flex gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-ttn-subtle text-lg">🔒</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start gap-2.5 flex-wrap">
                            <div>
                                <div class="text-[14.5px] font-bold">{{ $job['title'] }}</div>
                                <div class="text-xs text-ttn-text2">{{ __('jobs.school_hidden') }} &middot; {{ $job['location'] }} &middot; {{ $job['salary_label'] }}</div>
                                <div class="text-[11px] text-ttn-text2 opacity-70 mt-0.5">{{ __('jobs.posted_days_ago', ['n' => $job['posted_days_ago']]) }}</div>
                            </div>
                            <span class="rounded-full px-3 py-1.5 text-xs font-bold whitespace-nowrap
                                {{ $job['match_score'] >= 85 ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($job['match_score'] >= 65 ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                                {{ __('jobs.match_pct', ['pct' => $job['match_score']]) }}
                            </span>
                        </div>

                        @if ($job['deadline_days'] !== null)
                            <div class="mt-2">
                                <span class="rounded-full px-3 py-1 text-[11.5px] font-extrabold
                                    {{ $job['deadline_days'] <= 1 ? 'bg-ttn-red-bg text-ttn-red' : ($job['deadline_days'] <= 5 ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-primary-light text-ttn-primary-dark') }}">
                                    {{ __('jobs.apply_before') }} &middot; {{ $job['deadline_days'] <= 1 ? __('jobs.apply_before_last_day') : __('jobs.apply_before_days_left', ['n' => $job['deadline_days']]) }}
                                </span>
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-1.5 mt-2.5">
                            @foreach ($job['reasons'] as $reason)
                                <span class="rounded-full bg-ttn-subtle px-2.5 py-1 text-[11px] font-semibold text-ttn-text2">✓ {{ $reason }}</span>
                            @endforeach
                        </div>

                        @if ($job['missing'])
                            <div class="text-xs font-medium text-ttn-amber-text mt-2">{{ __('jobs.missing_hint', ['x' => $job['missing']]) }}</div>
                        @endif

                        <div class="flex flex-wrap gap-2 mt-3">
                            @if (in_array("{$job['source_schema']}:{$job['id']}", $appliedKeys, true))
                                <span class="rounded-lg bg-ttn-primary-light text-ttn-primary-dark px-4 py-2 text-xs font-bold">{{ __('jobs.applied') }}</span>
                            @else
                                <form method="POST" action="{{ route('candidate.applications.apply') }}">
                                    @csrf
                                    <input type="hidden" name="source_schema" value="{{ $job['source_schema'] }}">
                                    <input type="hidden" name="job_posting_id" value="{{ $job['id'] }}">
                                    <button class="rounded-lg bg-ttn-primary px-4 py-2 text-xs font-bold text-white cursor-pointer">{{ __('jobs.apply') }}</button>
                                </form>
                            @endif
                            <button @click="show()" class="rounded-lg border border-ttn-border px-4 py-2 text-xs font-bold cursor-pointer">{{ __('jobs.job_coach') }}</button>
                            <span title="{{ __('common.coming_soon') }}" class="rounded-lg border border-ttn-border px-4 py-2 text-xs font-bold text-ttn-text2 cursor-not-allowed">{{ __('jobs.save') }}</span>
                        </div>
                    </div>
                </div>

                <div x-show="open" x-cloak @click.self="close()" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-3 sm:p-5">
                    <div @click.stop class="w-[460px] max-w-full max-h-[80vh] overflow-y-auto rounded-2xl bg-ttn-card p-5 sm:p-7 text-ttn-text">
                        <div class="font-display text-base font-bold mb-1">{{ __('jobs.coach_title') }}</div>
                        <div class="text-[12.5px] text-ttn-text2 mb-4" x-show="!loading && !error">{!! __('jobs.coach_preparing', ['job' => '<span class="font-semibold">' . e($job['title']) . '</span>']) !!}</div>
                        <div x-show="loading" class="text-[13px] text-ttn-text2">{{ __('common.loading') }}</div>
                        <div x-show="error" x-text="error" class="text-[13px] text-ttn-red"></div>
                        <template x-if="data">
                            <div class="flex flex-col gap-3">
                                <template x-for="topic in data.topics" :key="topic.title">
                                    <div class="rounded-lg bg-ttn-subtle px-3.5 py-3">
                                        <div class="text-[12.5px] font-bold mb-1" x-text="topic.title"></div>
                                        <div class="text-[11.5px] text-ttn-text2" x-text="topic.body"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div @click="close()" class="text-center text-xs font-semibold text-ttn-text2 cursor-pointer mt-4">{{ __('common.close') }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-8 text-center">
                <div class="font-display text-[15px] font-bold mb-1.5">{{ __('jobs.no_jobs') }}</div>
                <div class="text-[13px] text-ttn-text2">{{ __('jobs.no_jobs_desc') }}</div>
            </div>
        @endforelse
    </div>

    @if ($hiddenCount > 0)
        <div class="rounded-2xl p-5 text-white text-center" style="background: var(--color-ttn-navy)">
            <div class="font-display text-[13.5px] font-bold mb-1.5">{{ __('jobs.free_matches_done') }}</div>
            <div class="text-xs opacity-80 mb-3.5">{{ trans_choice('jobs.more_matches', $hiddenCount, ['n' => $hiddenCount]) }}</div>
            <a href="{{ route('candidate.premium.show') }}" class="inline-block rounded-lg bg-ttn-amber px-5 py-2.5 text-[13px] font-bold" style="color: var(--color-ttn-navy)">{{ __('jobs.unlock_premium_cta') }}</a>
        </div>
    @endif
</x-candidate-shell>
