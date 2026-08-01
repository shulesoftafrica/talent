<x-officer-shell :officer="$officer" active="dashboard" :title="__('officer.dashboard_title')">
    <div class="p-4 sm:p-8 max-w-[1100px]">
        <h1 class="font-display text-lg sm:text-xl font-extrabold mb-1">{{ __('officer.dashboard_title') }}</h1>
        <div class="text-[12.5px] text-ttn-text2 mb-5">{{ __('officer.dashboard_subtitle') }}</div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-5">
            @foreach ($stats as $stat)
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                    <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ $stat['label'] }}</div>
                    <div class="font-display text-2xl font-extrabold text-ttn-primary-dark">{{ $stat['value'] }}</div>
                    <div class="text-[11px] font-medium text-ttn-text2 mt-1">{{ $stat['sub'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.by_location') }}</div>
                <div class="flex flex-col gap-3">
                    @forelse ($regions as $region)
                        @php $pct = round(($region->total / $totalCandidates) * 100); @endphp
                        <div>
                            <div class="flex justify-between text-[12.5px] font-semibold mb-1">
                                <span>{{ $region->current_location }}</span>
                                <span class="text-ttn-text2">{{ $region->total }} &middot; {{ $pct }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-ttn-subtle overflow-hidden">
                                <div class="h-full rounded-full bg-ttn-primary" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[13px] text-ttn-text2">{{ __('officer.no_location_data') }}</div>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3.5">{{ __('officer.by_profession') }}</div>
                <div class="flex flex-col gap-3">
                    @forelse ($professions as $profession)
                        @php $pct = round(($profession->total / $totalCandidates) * 100); @endphp
                        <div>
                            <div class="flex justify-between text-[12.5px] font-semibold mb-1">
                                <span>{{ $profession->profession }}</span>
                                <span class="text-ttn-text2">{{ $profession->total }} &middot; {{ $pct }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-ttn-subtle overflow-hidden">
                                <div class="h-full rounded-full bg-ttn-amber" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-[13px] text-ttn-text2">{{ __('officer.no_profession_data') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-officer-shell>
