<x-officer-shell :officer="$officer" active="analytics" :title="__('officer.analytics_title')">
    <div class="p-4 sm:p-8 max-w-[1100px]">
        <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
            <h1 class="font-display text-lg sm:text-xl font-extrabold">{{ __('officer.analytics_title') }}</h1>
            <div class="flex gap-1.5">
                @foreach ([7 => __('officer.analytics_7d'), 30 => __('officer.analytics_30d'), 90 => __('officer.analytics_90d')] as $value => $label)
                    <a href="{{ route('officer.analytics', ['days' => $value]) }}"
                       class="rounded-lg px-3 py-1.5 text-[12px] font-bold {{ $days === $value ? 'bg-ttn-primary text-white' : 'bg-ttn-subtle text-ttn-text2' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
        <div class="text-[12.5px] text-ttn-text2 mb-5">{{ __('officer.analytics_subtitle') }}</div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5 mb-5">
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.analytics_total_views') }}</div>
                <div class="font-display text-2xl font-extrabold">{{ number_format($totals->views) }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.analytics_candidates') }}</div>
                <div class="font-display text-2xl font-extrabold">{{ number_format($totals->candidates) }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.analytics_sessions') }}</div>
                <div class="font-display text-2xl font-extrabold">{{ number_format($totals->sessions) }}</div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.analytics_avg_time') }}</div>
                <div class="font-display text-2xl font-extrabold">
                    {{ $totals->avg_duration_ms ? number_format($totals->avg_duration_ms / 1000, 1) . 's' : '—' }}
                </div>
            </div>
        </div>

        {{-- Traffic over time --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-5">
            <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-4">{{ __('officer.analytics_traffic_over_time') }}</div>
            @if ($dailyTraffic->isEmpty())
                <div class="text-[13px] text-ttn-text2 text-center py-6">{{ __('officer.analytics_no_data') }}</div>
            @else
                <div class="flex items-end gap-1 h-32 overflow-x-auto">
                    @foreach ($dailyTraffic as $day)
                        <div class="flex-1 min-w-[6px] flex flex-col items-center justify-end h-full group relative">
                            <div class="w-full rounded-t bg-ttn-primary" style="height: {{ max(2, round(($day->views / $maxDailyViews) * 100)) }}%"></div>
                            <div class="absolute -top-6 hidden group-hover:block rounded bg-ttn-navy text-white text-[10px] font-bold px-1.5 py-0.5 whitespace-nowrap z-10">
                                {{ \Illuminate\Support\Carbon::parse($day->day)->format('d M') }}: {{ $day->views }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[10px] text-ttn-text2 mt-2">
                    <span>{{ \Illuminate\Support\Carbon::parse($dailyTraffic->first()->day)->format('d M') }}</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($dailyTraffic->last()->day)->format('d M') }}</span>
                </div>
            @endif
        </div>

        {{-- Most visited pages --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden mb-5">
            <div class="px-5 py-3.5 bg-ttn-subtle text-xs font-bold uppercase tracking-wide text-ttn-text2">{{ __('officer.analytics_top_pages') }}</div>
            @forelse ($topPages as $page)
                @php $pct = $totals->views > 0 ? round(($page['views'] / $totals->views) * 100) : 0; @endphp
                <div class="px-5 py-3 border-t border-ttn-hairline">
                    <div class="flex items-center justify-between gap-3 mb-1.5 text-[12.5px]">
                        <span class="font-semibold truncate">{{ $page['label'] }}</span>
                        <span class="text-ttn-text2 whitespace-nowrap">
                            {{ number_format($page['views']) }} {{ __('officer.analytics_views') }}
                            @if ($page['avg_duration_ms'])
                                &middot; {{ number_format($page['avg_duration_ms'] / 1000, 1) }}s {{ __('officer.analytics_avg') }}
                            @endif
                        </span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-ttn-track">
                        <div class="h-full rounded-full bg-ttn-primary" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-[13px] text-ttn-text2">{{ __('officer.analytics_no_data') }}</div>
            @endforelse
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-5">
            {{-- Device mix --}}
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.analytics_devices') }}</div>
                <div class="flex flex-col gap-2.5">
                    @forelse ($deviceMix as $row)
                        @php $pct = $totals->views > 0 ? round(($row->views / $totals->views) * 100) : 0; @endphp
                        <div>
                            <div class="flex justify-between text-[12px] font-semibold mb-1">
                                <span class="capitalize">{{ $row->device_type ?? __('officer.analytics_unknown') }}</span>
                                <span class="text-ttn-text2">{{ $pct }}%</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-ttn-track">
                                <div class="h-full rounded-full bg-ttn-primary" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[12.5px] text-ttn-text2">{{ __('officer.analytics_no_data') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Browser mix --}}
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.analytics_browsers') }}</div>
                <div class="flex flex-col gap-2">
                    @forelse ($browserMix as $row)
                        <div class="flex items-center justify-between gap-3 text-[12.5px]">
                            <span class="font-semibold">{{ $row->browser ?? __('officer.analytics_unknown') }}</span>
                            <span class="text-ttn-text2">{{ number_format($row->views) }}</span>
                        </div>
                    @empty
                        <div class="text-[12.5px] text-ttn-text2">{{ __('officer.analytics_no_data') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Platform mix --}}
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.analytics_platforms') }}</div>
                <div class="flex flex-col gap-2">
                    @forelse ($platformMix as $row)
                        <div class="flex items-center justify-between gap-3 text-[12.5px]">
                            <span class="font-semibold">{{ $row->platform ?? __('officer.analytics_unknown') }}</span>
                            <span class="text-ttn-text2">{{ number_format($row->views) }}</span>
                        </div>
                    @empty
                        <div class="text-[12.5px] text-ttn-text2">{{ __('officer.analytics_no_data') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Top countries --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
            <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.analytics_top_countries') }}</div>
            <div class="flex flex-col gap-2.5">
                @forelse ($topCountries as $row)
                    @php $pct = $totals->views > 0 ? round(($row['views'] / $totals->views) * 100) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-[12px] font-semibold mb-1">
                            <span>{{ $row['name'] }}</span>
                            <span class="text-ttn-text2">{{ number_format($row['views']) }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-ttn-track">
                            <div class="h-full rounded-full bg-ttn-primary" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-[12.5px] text-ttn-text2">{{ __('officer.analytics_no_country_data') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</x-officer-shell>
