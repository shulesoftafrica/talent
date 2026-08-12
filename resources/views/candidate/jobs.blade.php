<x-candidate-shell :candidate="$candidate" active="jobs" :title="__('jobs.greeting', ['name' => explode(' ', $candidate->full_name)[0]])" :subtitle="__('jobs.subtitle', ['count' => $totalCount])">
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

    <div
        x-data="jobMatchesInfiniteScroll('{{ route('candidate.jobs.more') }}', {{ $jobs->count() }}, {{ $hasMore ? 'true' : 'false' }})"
        class="flex flex-col gap-3 mb-4.5"
    >
        @forelse ($jobs as $job)
            @include('candidate._job-card', ['job' => $job])
        @empty
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-8 text-center">
                <div class="font-display text-[15px] font-bold mb-1.5">{{ __('jobs.no_jobs') }}</div>
                <div class="text-[13px] text-ttn-text2">{{ __('jobs.no_jobs_desc') }}</div>
            </div>
        @endforelse

        <div x-ref="sentinel" x-show="hasMore" x-cloak class="py-3 text-center text-xs font-semibold text-ttn-text2">
            {{ __('common.loading') }}
        </div>
    </div>

    @if ($hiddenCount > 0)
        <div class="rounded-2xl p-5 text-white text-center" style="background: var(--color-ttn-navy)">
            <div class="font-display text-[13.5px] font-bold mb-1.5">{{ __('jobs.free_matches_done') }}</div>
            <div class="text-xs opacity-80 mb-3.5">{{ trans_choice('jobs.more_matches', $hiddenCount, ['n' => $hiddenCount]) }}</div>
            <a href="{{ route('candidate.premium.show') }}" class="inline-block rounded-lg bg-ttn-amber px-5 py-2.5 text-[13px] font-bold" style="color: var(--color-ttn-navy)">{{ __('jobs.unlock_premium_cta') }}</a>
        </div>
    @endif

    <script>
        function jobMatchesInfiniteScroll(moreUrl, loadedCount, hasMoreInitially) {
            return {
                offset: loadedCount,
                hasMore: hasMoreInitially,
                loading: false,
                observer: null,

                init() {
                    if (!this.hasMore) return;

                    this.observer = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting) this.loadMore();
                    }, { rootMargin: '400px' });

                    this.observer.observe(this.$refs.sentinel);
                },

                async loadMore() {
                    if (this.loading || !this.hasMore) return;
                    this.loading = true;

                    try {
                        const res = await fetch(`${moreUrl}?offset=${this.offset}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json();

                        // insertAdjacentHTML with a raw string wouldn't get Alpine's
                        // x-data/@click bindings initialized on the new cards (the
                        // Job Coach modal would be dead) — build real nodes and run
                        // them through Alpine.initTree() individually instead.
                        const temp = document.createElement('div');
                        temp.innerHTML = data.html;
                        Array.from(temp.children).forEach((node) => {
                            this.$refs.sentinel.before(node);
                            if (window.Alpine) window.Alpine.initTree(node);
                        });

                        this.offset += data.count;
                        this.hasMore = data.has_more;

                        if (!this.hasMore) this.observer.disconnect();
                    } catch (e) {
                        this.hasMore = false;
                        this.observer.disconnect();
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
</x-candidate-shell>
