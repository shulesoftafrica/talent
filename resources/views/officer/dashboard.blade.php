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

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mt-3.5">
            <div class="font-display text-[15px] font-bold mb-0.5">{{ __('officer.subject_balance_title') }}</div>
            <div class="text-[11.5px] text-ttn-text2 mb-4">{{ __('officer.subject_balance_subtitle') }}</div>

            @if (empty($subjectBalance))
                <div class="text-[13px] text-ttn-text2">{{ __('officer.subject_balance_empty') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px] min-w-[560px]">
                        <thead>
                            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-ttn-text2 border-b border-ttn-border">
                                <th class="text-left py-2 pr-2 font-bold">{{ __('officer.subject_balance_col_area') }}</th>
                                <th class="text-right py-2 px-2 font-bold">{{ __('officer.subject_balance_col_candidates') }}</th>
                                <th class="text-right py-2 px-2 font-bold">{{ __('officer.subject_balance_col_jobs') }}</th>
                                <th class="py-2 px-2 font-bold w-[160px]">{{ __('officer.subject_balance_col_balance') }}</th>
                                <th class="text-right py-2 pl-2 font-bold">{{ __('officer.subject_balance_col_gap') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjectBalance as $row)
                                <tr class="border-b border-ttn-hairline last:border-0">
                                    <td class="py-3 pr-2 font-bold whitespace-nowrap">{{ $row['subject'] }}</td>
                                    <td class="py-3 px-2 text-right text-ttn-text2">{{ number_format($row['candidates']) }}</td>
                                    <td class="py-3 px-2 text-right text-ttn-text2">{{ number_format($row['jobs']) }}</td>
                                    <td class="py-3 px-2">
                                        <div class="grid grid-cols-2 h-2">
                                            <div class="flex justify-end pr-px">
                                                @if ($row['gap'] < 0)
                                                    <div class="h-2 rounded-l bg-ttn-red" style="width: {{ $row['bar_pct'] }}%"></div>
                                                @endif
                                            </div>
                                            <div class="flex justify-start pl-px border-l border-ttn-border">
                                                @if ($row['gap'] > 0)
                                                    <div class="h-2 rounded-r bg-ttn-primary" style="width: {{ $row['bar_pct'] }}%"></div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 pl-2 text-right font-bold whitespace-nowrap {{ $row['gap'] < 0 ? 'text-ttn-red' : ($row['gap'] > 0 ? 'text-ttn-primary-dark' : 'text-ttn-text2') }}">
                                        {{ $row['gap'] > 0 ? '+' : '' }}{{ number_format($row['gap']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="mt-3.5">
            <div class="font-display text-[15px] font-bold mb-0.5">{{ __('officer.job_health_title') }}</div>
            <div class="text-[11.5px] text-ttn-text2 mb-3.5">{{ __('officer.job_health_subtitle') }}</div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-3.5">
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                    <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.job_health_active') }}</div>
                    <div class="font-display text-2xl font-extrabold text-ttn-primary-dark">{{ number_format($jobHealth['active_count']) }}</div>
                </div>
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                    <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.job_health_applications') }}</div>
                    <div class="font-display text-2xl font-extrabold text-ttn-primary-dark">{{ number_format($jobHealth['total_applications']) }}</div>
                    <div class="text-[11px] font-medium text-ttn-text2 mt-1">{{ __('officer.job_health_applications_sub') }}</div>
                </div>
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                    <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.job_health_avg') }}</div>
                    <div class="font-display text-2xl font-extrabold text-ttn-primary-dark">{{ $jobHealth['avg_applications'] }}</div>
                </div>
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4.5">
                    <div class="text-[11px] font-semibold text-ttn-text2 mb-2">{{ __('officer.job_health_zero') }}</div>
                    <div class="font-display text-2xl font-extrabold {{ $jobHealth['zero_application_count'] > 0 ? 'text-ttn-red' : 'text-ttn-primary-dark' }}">{{ number_format($jobHealth['zero_application_count']) }}</div>
                </div>
            </div>

            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="flex justify-between items-baseline mb-3.5 flex-wrap gap-1">
                    <div class="font-display text-[13.5px] font-bold">{{ __('officer.job_health_table_title') }}</div>
                    @if (!empty($jobHealth['postings']))
                        <div class="text-[11px] text-ttn-text2">{{ __('officer.job_health_flagged_count', ['n' => $jobHealth['flagged_count'], 'total' => $jobHealth['active_count']]) }}</div>
                    @endif
                </div>

                @if (empty($jobHealth['postings']))
                    <div class="text-[13px] text-ttn-text2">{{ __('officer.job_health_empty') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px] min-w-[760px]">
                            <thead>
                                <tr class="text-[10.5px] font-bold uppercase tracking-wide text-ttn-text2 border-b border-ttn-border">
                                    <th class="text-left py-2 pr-2 font-bold">{{ __('officer.job_health_col_vacancy') }}</th>
                                    <th class="text-left py-2 px-2 font-bold">{{ __('officer.job_health_col_school') }}</th>
                                    <th class="text-left py-2 px-2 font-bold">{{ __('officer.job_health_col_location') }}</th>
                                    <th class="text-left py-2 px-2 font-bold">{{ __('officer.job_health_col_country') }}</th>
                                    <th class="text-right py-2 px-2 font-bold">{{ __('officer.job_health_col_days_live') }}</th>
                                    <th class="text-right py-2 px-2 font-bold">{{ __('officer.job_health_col_applications') }}</th>
                                    <th class="text-left py-2 pl-2 font-bold">{{ __('officer.job_health_col_diagnosis') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($jobHealth['postings'] as $row)
                                    <tr class="border-b border-ttn-hairline last:border-0">
                                        <td class="py-3 pr-2 font-bold whitespace-nowrap">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full mr-1.5 {{ $row['applications'] === 0 ? 'bg-ttn-red' : ($row['flagged'] ? 'bg-ttn-amber' : 'bg-ttn-primary') }}"></span>
                                            {{ $row['title'] }}
                                        </td>
                                        <td class="py-3 px-2 whitespace-nowrap {{ $row['school'] ? 'text-ttn-text2' : 'italic text-ttn-text2 opacity-60' }}">
                                            {{ $row['school'] ?? __('officer.job_health_unknown_school') }}
                                        </td>
                                        <td class="py-3 px-2 text-ttn-text2 whitespace-nowrap">{{ $row['location'] ?? __('officer.job_health_unknown') }}</td>
                                        <td class="py-3 px-2 text-ttn-text2 whitespace-nowrap">{{ $row['country'] ?? __('officer.job_health_unknown') }}</td>
                                        <td class="py-3 px-2 text-right text-ttn-text2">{{ $row['days_live'] }}</td>
                                        <td class="py-3 px-2 text-right text-ttn-text2">{{ $row['applications'] }}</td>
                                        <td class="py-3 pl-2 whitespace-nowrap {{ $row['flagged'] ? 'text-ttn-text2' : 'text-ttn-primary-dark font-semibold' }}">{{ $row['diagnosis'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-officer-shell>
