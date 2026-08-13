<x-officer-shell :officer="$officer" active="feedback" :title="__('feedback.ops_title')">
    <div class="p-4 sm:p-8 max-w-[1100px]">
        <h1 class="font-display text-lg sm:text-xl font-extrabold mb-1">{{ __('feedback.ops_title') }}</h1>
        <div class="text-[12.5px] text-ttn-text2 mb-5">{{ __('feedback.ops_subtitle') }}</div>

        @if (session('status'))
            <div class="rounded-lg bg-ttn-primary-light px-3.5 py-2.5 text-[12.5px] font-semibold text-ttn-primary-dark mb-4">{{ session('status') }}</div>
        @endif

        {{-- Stat tiles --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4">
                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('feedback.ops_stat_open') }}</div>
                <div class="font-display text-xl font-extrabold text-ttn-primary-dark">{{ $stats['open'] }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4">
                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('feedback.ops_stat_urgent') }}</div>
                <div class="font-display text-xl font-extrabold text-ttn-red">{{ $stats['urgent'] }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4">
                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('feedback.ops_stat_new_today') }}</div>
                <div class="font-display text-xl font-extrabold text-ttn-primary-dark">{{ $stats['new_today'] }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4">
                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('feedback.ops_stat_avg_response') }}</div>
                <div class="font-display text-xl font-extrabold text-ttn-primary-dark">{{ $stats['avg_response'] }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4">
                <div class="text-[10.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('feedback.ops_stat_resolved_week') }}</div>
                <div class="font-display text-xl font-extrabold text-ttn-primary-dark">{{ $stats['resolved_this_week'] }}</div>
            </div>
        </div>

        {{-- Product intelligence --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-5">
            <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-4">{{ __('feedback.ops_intel_title') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <div class="text-[12px] font-bold mb-2.5">{{ __('feedback.ops_top_problems') }}</div>
                    @forelse ($topProblems as $p)
                        <div class="flex justify-between text-[12px] mb-1.5">
                            <span class="text-ttn-text2">{{ $p['label'] }}</span>
                            <span class="font-bold">{{ $p['count'] }}</span>
                        </div>
                    @empty
                        <div class="text-[11.5px] text-ttn-text2">{{ __('feedback.ops_no_data') }}</div>
                    @endforelse
                </div>
                <div>
                    <div class="text-[12px] font-bold mb-2.5">{{ __('feedback.ops_sentiment') }}</div>
                    @if ($sentiment['total'] > 0)
                        <div class="flex flex-col gap-1.5">
                            <div class="flex justify-between text-[12px]"><span>👍</span><span class="font-bold">{{ $sentiment['like'] }}%</span></div>
                            <div class="flex justify-between text-[12px]"><span>😐</span><span class="font-bold">{{ $sentiment['neutral'] }}%</span></div>
                            <div class="flex justify-between text-[12px]"><span>👎</span><span class="font-bold">{{ $sentiment['dislike'] }}%</span></div>
                        </div>
                    @else
                        <div class="text-[11.5px] text-ttn-text2">{{ __('feedback.ops_no_data') }}</div>
                    @endif
                </div>
                <div>
                    <div class="text-[12px] font-bold mb-2.5">{{ __('feedback.ops_top_ideas') }}</div>
                    @forelse ($topIdeas as $idea)
                        <div class="text-[11.5px] text-ttn-text2 mb-1.5 line-clamp-2">{{ $idea }}</div>
                    @empty
                        <div class="text-[11.5px] text-ttn-text2">{{ __('feedback.ops_no_data') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-2.5 mb-4">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('feedback.ops_search_placeholder') }}"
                   class="flex-1 min-w-[200px] rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">

            <select name="category" class="rounded-lg border border-ttn-border px-2.5 py-2 text-[12.5px]" onchange="this.form.submit()">
                <option value="">{{ __('feedback.ops_filter_category') }}: {{ __('feedback.ops_filter_all') }}</option>
                @foreach (\App\Models\FeedbackItem::CATEGORIES as $c)
                    <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ __('feedback.ops_category_' . $c) }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-lg border border-ttn-border px-2.5 py-2 text-[12.5px]" onchange="this.form.submit()">
                <option value="">{{ __('feedback.ops_filter_status') }}: {{ __('feedback.ops_filter_all') }}</option>
                @foreach (\App\Models\FeedbackItem::STATUSES as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __('feedback.status_' . $s) }}</option>
                @endforeach
            </select>

            <select name="priority" class="rounded-lg border border-ttn-border px-2.5 py-2 text-[12.5px]" onchange="this.form.submit()">
                <option value="">{{ __('feedback.ops_filter_priority') }}: {{ __('feedback.ops_filter_all') }}</option>
                @foreach (\App\Models\FeedbackItem::PRIORITIES as $p)
                    <option value="{{ $p }}" @selected(($filters['priority'] ?? '') === $p)>{{ __('feedback.ops_priority_' . $p) }}</option>
                @endforeach
            </select>

            <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('common.search') }}</button>
        </form>

        {{-- List --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead>
                        <tr class="border-b border-ttn-border text-left text-[11px] font-bold uppercase tracking-wide text-ttn-text2">
                            <th class="px-4 py-3">{{ __('feedback.ops_column_candidate') }}</th>
                            <th class="px-4 py-3">{{ __('feedback.ops_column_category') }}</th>
                            <th class="px-4 py-3">{{ __('feedback.ops_column_message') }}</th>
                            <th class="px-4 py-3">{{ __('feedback.ops_column_priority') }}</th>
                            <th class="px-4 py-3">{{ __('feedback.ops_column_status') }}</th>
                            <th class="px-4 py-3">{{ __('feedback.ops_column_submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-b border-ttn-border last:border-0 hover:bg-ttn-subtle cursor-pointer" onclick="window.location='{{ route('officer.feedback.show', $item) }}'">
                                <td class="px-4 py-3 font-semibold">{{ $item->candidate?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ __('feedback.ops_category_' . $item->category) }}</td>
                                <td class="px-4 py-3 text-ttn-text2 max-w-[280px] truncate">{{ $item->message ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold
                                        {{ $item->priority === 'critical' ? 'bg-ttn-red-bg text-ttn-red' : ($item->priority === 'high' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                                        {{ __('feedback.ops_priority_' . $item->priority) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[10.5px] font-bold
                                        {{ $item->status === 'resolved' ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($item->status === 'in_review' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                                        {{ __('feedback.status_' . $item->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-ttn-text2">{{ $item->created_at->format('d M, H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-ttn-text2">{{ __('feedback.ops_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($items->hasPages())
            <div class="flex justify-between items-center mt-4">
                <a href="{{ $items->previousPageUrl() }}" class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold {{ $items->onFirstPage() ? 'opacity-40 pointer-events-none' : '' }}">‹</a>
                <div class="text-[12px] text-ttn-text2">{{ $items->currentPage() }} / {{ $items->lastPage() }}</div>
                <a href="{{ $items->nextPageUrl() }}" class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold {{ $items->hasMorePages() ? '' : 'opacity-40 pointer-events-none' }}">›</a>
            </div>
        @endif
    </div>
</x-officer-shell>
