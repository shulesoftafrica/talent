<x-officer-shell :officer="$officer" active="ai-usage" :title="__('officer.ai_usage_title')">
    <div class="p-4 sm:p-8 max-w-[1100px]">
        <h1 class="font-display text-lg sm:text-xl font-extrabold mb-1">{{ __('officer.ai_usage_title') }}</h1>
        <div class="text-[12.5px] text-ttn-text2 mb-5">{{ __('officer.ai_usage_subtitle') }}</div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-5">
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.ai_total_cost') }}</div>
                <div class="font-display text-2xl font-extrabold text-ttn-primary-dark">${{ number_format((float) $totals->cost, 4) }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.ai_total_requests') }}</div>
                <div class="font-display text-2xl font-extrabold">{{ number_format($totals->requests) }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.ai_total_tokens') }}</div>
                <div class="font-display text-2xl font-extrabold">{{ number_format($totals->tokens) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-5">
            @if ($byFeature->isNotEmpty())
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                    <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.ai_by_feature') }}</div>
                    <div class="flex flex-col gap-2.5">
                        @foreach ($byFeature as $row)
                            <div class="flex items-center justify-between gap-3 text-[12.5px]">
                                <span class="font-semibold">{{ $row['label'] }}</span>
                                <span class="text-ttn-text2">{{ number_format($row['requests']) }} req &middot; <span class="font-bold text-ttn-primary-dark">${{ number_format((float) $row['cost'], 4) }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($byProvider->isNotEmpty())
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                    <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.ai_by_provider') }}</div>
                    <div class="flex flex-col gap-2.5">
                        @foreach ($byProvider as $row)
                            <div class="flex items-center justify-between gap-3 text-[12.5px]">
                                <span class="font-semibold capitalize">{{ $row->provider }}</span>
                                <span class="text-ttn-text2">{{ number_format($row->successes) }}/{{ number_format($row->requests) }} {{ __('officer.ai_ok') }} &middot; <span class="font-bold text-ttn-primary-dark">${{ number_format((float) $row->cost, 4) }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden mb-5">
            <div class="px-5 py-3.5 bg-ttn-subtle text-xs font-bold uppercase tracking-wide text-ttn-text2">{{ __('officer.ai_by_candidate') }}</div>

            <div class="overflow-x-auto">
                <div class="grid grid-cols-[1.6fr_0.7fr_0.8fr_0.7fr_0.9fr_0.5fr] gap-2 px-5 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-ttn-text2 min-w-[600px]">
                    <div>{{ __('officer.ai_col_candidate') }}</div>
                    <div>{{ __('officer.ai_col_requests') }}</div>
                    <div>{{ __('officer.ai_col_tokens') }}</div>
                    <div>{{ __('officer.ai_col_cost') }}</div>
                    <div>{{ __('officer.ai_col_last_request') }}</div>
                    <div></div>
                </div>

                @forelse ($byCandidate as $row)
                    @php $candidate = $candidates->get($row->candidate_id); @endphp
                    <div class="grid grid-cols-[1.6fr_0.7fr_0.8fr_0.7fr_0.9fr_0.5fr] gap-2 items-center px-5 py-3 border-t border-ttn-hairline text-[12.5px] min-w-[600px]">
                        <div class="font-semibold truncate">{{ $candidate->full_name ?? ('#' . $row->candidate_id) }}</div>
                        <div>{{ number_format($row->requests) }}</div>
                        <div>{{ number_format($row->tokens) }}</div>
                        <div class="font-bold text-ttn-primary-dark">${{ number_format((float) $row->cost, 4) }}</div>
                        <div class="text-ttn-text2">{{ \Illuminate\Support\Carbon::parse($row->last_request_at)->format('d M Y') }}</div>
                        <div>
                            <a href="{{ route('officer.ai-usage', ['candidate' => $row->candidate_id]) }}" class="text-[11.5px] font-bold text-ttn-primary-dark whitespace-nowrap">{{ __('officer.ai_view_detail') }} &rarr;</a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-[13px] text-ttn-text2 min-w-[600px]">{{ __('officer.ai_no_usage') }}</div>
                @endforelse
            </div>

            @if ($byCandidate->hasPages())
                <div class="px-5 py-3.5 border-t border-ttn-hairline">
                    {{ $byCandidate->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        @if ($unattributed && $unattributed->requests > 0)
            <div class="rounded-2xl border border-ttn-border bg-ttn-subtle p-4 sm:p-5 mb-5 text-[12.5px]">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-ttn-text2">{{ __('officer.ai_unattributed') }}</span>
                    <span class="font-bold">{{ number_format($unattributed->requests) }} req &middot; ${{ number_format((float) $unattributed->cost, 4) }}</span>
                </div>
            </div>
        @endif

        @if ($selectedCandidate)
            <div class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden">
                <div class="px-5 py-3.5 bg-ttn-subtle flex items-center justify-between gap-3">
                    <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2">{{ __('officer.ai_detail_title', ['name' => $selectedCandidate->full_name]) }}</div>
                    <a href="{{ route('officer.ai-usage') }}" class="text-[11.5px] font-bold text-ttn-primary-dark whitespace-nowrap">{{ __('officer.ai_back_to_summary') }}</a>
                </div>

                <div class="overflow-x-auto">
                    <div class="grid grid-cols-[1fr_0.7fr_0.8fr_0.7fr_0.7fr_1fr] gap-2 px-5 py-2.5 text-[10.5px] font-bold uppercase tracking-wide text-ttn-text2 min-w-[640px]">
                        <div>{{ __('officer.ai_col_feature') }}</div>
                        <div>{{ __('officer.ai_col_provider') }}</div>
                        <div>{{ __('officer.ai_col_status') }}</div>
                        <div>{{ __('officer.ai_col_tokens') }}</div>
                        <div>{{ __('officer.ai_col_cost') }}</div>
                        <div>{{ __('officer.ai_col_when') }}</div>
                    </div>

                    @forelse ($requestLog as $log)
                        <div class="grid grid-cols-[1fr_0.7fr_0.8fr_0.7fr_0.7fr_1fr] gap-2 items-center px-5 py-3 border-t border-ttn-hairline text-[12.5px] min-w-[640px]">
                            <div class="font-semibold">{{ \App\Services\AI\AiFeature::label($log->feature) }}</div>
                            <div class="capitalize text-ttn-text2">{{ $log->provider }}</div>
                            <div>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold whitespace-nowrap {{ $log->status === 'success' ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-red-bg text-ttn-red' }}">
                                    {{ __('officer.ai_status_' . $log->status) }}
                                </span>
                            </div>
                            <div>{{ number_format($log->total_tokens) }}</div>
                            <div class="font-bold text-ttn-primary-dark">${{ number_format((float) $log->estimated_cost_usd, 4) }}</div>
                            <div class="text-ttn-text2">{{ $log->created_at->format('d M Y H:i') }}</div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-[13px] text-ttn-text2 min-w-[640px]">{{ __('officer.ai_no_usage') }}</div>
                    @endforelse
                </div>

                @if ($requestLog->hasPages())
                    <div class="px-5 py-3.5 border-t border-ttn-hairline">
                        {{ $requestLog->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-officer-shell>
