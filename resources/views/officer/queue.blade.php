<x-officer-shell :officer="$officer" active="queue" :title="__('officer.queue_title')">
    <div class="p-4 sm:p-8 grid grid-cols-1 xl:grid-cols-[1.8fr_1fr] gap-5 max-w-[1280px]">
        <div class="min-w-0">
            <h1 class="font-display text-lg sm:text-xl font-extrabold mb-1">{{ __('officer.queue_title') }}</h1>
            <div class="text-[12.5px] text-ttn-text2 mb-4">{{ __('officer.queue_subtitle') }}</div>

            @if (session('status'))
                <div class="mb-3 rounded-lg bg-ttn-primary-light px-4 py-2.5 text-[13px] font-semibold text-ttn-primary-dark">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-xl border border-ttn-border bg-ttn-card overflow-x-auto">
                <div class="grid grid-cols-[2fr_0.8fr_0.8fr] sm:grid-cols-[2.6fr_0.8fr_0.8fr] gap-2 sm:gap-2.5 px-3 sm:px-4 py-3 bg-ttn-subtle text-[10px] sm:text-[10.5px] font-bold uppercase tracking-wide text-ttn-text2 min-w-[420px]">
                    <div>{{ __('officer.col_candidate') }}</div><div>{{ __('officer.col_type') }}</div><div>{{ __('officer.col_sla') }}</div>
                </div>
                @forelse ($items as $item)
                    <a href="{{ route('officer.queue', ['selected' => $item['id']]) }}"
                       class="grid grid-cols-[2fr_0.8fr_0.8fr] sm:grid-cols-[2.6fr_0.8fr_0.8fr] gap-2 sm:gap-2.5 items-start px-3 sm:px-4 py-3.5 border-t border-ttn-border block min-w-[420px]
                              {{ $selected && $selected['id'] === $item['id'] ? 'bg-ttn-subtle' : '' }}">
                        <div class="min-w-0">
                            <div class="text-[12.5px] font-bold truncate">{{ $item['candidate_name'] }}</div>
                            <div class="text-[11px] text-ttn-text2 truncate">{{ $item['payer'] }}</div>
                        </div>
                        <div class="text-[11px] leading-snug">{{ $item['type'] }}</div>
                        <div class="text-[10px] font-bold" style="color: {{ str_starts_with($item['sla_label'], 'Overdue') ? 'var(--color-ttn-red)' : 'var(--color-ttn-text2)' }}">
                            {{ $item['sla_label'] }}
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center text-[13px] text-ttn-text2 min-w-[420px]">{{ __('officer.queue_empty') }}</div>
                @endforelse
            </div>
        </div>

        <div class="xl:max-h-[calc(100vh-4rem)] xl:overflow-y-auto xl:pr-1">
            @if ($selected)
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                    <div class="flex justify-between items-start gap-2.5 mb-1 flex-wrap">
                        <div class="font-display text-[15px] font-bold">{{ $selected['candidate_name'] }}</div>
                        <span class="rounded-full px-2.5 py-1 text-[10.5px] font-bold whitespace-nowrap
                            {{ $selected['priority']['key'] === 'high' ? 'bg-ttn-red-bg text-ttn-red' : ($selected['priority']['key'] === 'decision' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-primary-light text-ttn-primary-dark') }}">
                            {{ $selected['priority']['icon'] }} {{ $selected['priority']['label'] }}
                        </span>
                    </div>
                    <div class="text-[12.5px] text-ttn-text2">{{ $selected['type'] }} &middot; {{ __('officer.payer_label', ['payer' => $selected['payer']]) }} &middot; {{ __('officer.submitted_label', ['date' => $selected['submitted_at']]) }}</div>
                </div>

                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                    <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3">{{ __('officer.candidate_summary') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2.5">
                        @foreach ($selected['candidate_info'] as $info)
                            <div>
                                <div class="text-[10.5px] text-ttn-text2">{{ $info['label'] }}</div>
                                <div class="text-[12.5px] font-semibold">{{ $info['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                    <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3">{{ __('officer.evidence') }}</div>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($selected['evidence'] as $evidence)
                            <span class="rounded-full bg-ttn-subtle px-3 py-1.5 text-[11.5px] font-semibold">📎 {{ $evidence }}</span>
                        @empty
                            <div class="text-[13px] text-ttn-text2">{{ __('officer.no_evidence') }}</div>
                        @endforelse
                    </div>
                </div>

                @if (!empty($selected['submission']))
                    <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                        <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3">{{ __('officer.submitted_details') }}</div>
                        <div class="flex flex-col gap-4">
                            @foreach ($selected['submission'] as $section)
                                <div>
                                    <div class="text-[12.5px] font-bold mb-2">{{ $section['title'] }}</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 rounded-lg bg-ttn-subtle p-3">
                                        @foreach ($section['fields'] as $field)
                                            <div>
                                                <div class="text-[10.5px] text-ttn-text2">{{ $field['label'] }}</div>
                                                @if (!empty($field['url']))
                                                    <a href="{{ $field['url'] }}" target="_blank" rel="noopener" class="text-[12.5px] font-semibold text-ttn-primary-dark underline">{{ $field['value'] }}</a>
                                                @else
                                                    <div class="text-[12.5px] font-semibold">{{ $field['value'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                    <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3">{{ __('officer.checklist') }}</div>
                    <div class="flex flex-col gap-1.5">
                        @foreach ($selected['checklist'] as $check)
                            <div class="text-[12.5px] font-medium" style="color: {{ $check['done'] ? 'var(--color-ttn-primary-dark)' : 'var(--color-ttn-text2)' }}">
                                {{ $check['done'] ? '✓' : '○' }} {{ $check['label'] }}
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (!empty($selected['history']))
                    <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                        <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-3">{{ __('officer.trust_record') }}</div>
                        <div class="flex flex-col gap-2">
                            @foreach ($selected['history'] as $h)
                                <div class="flex justify-between items-center gap-2 rounded-lg bg-ttn-subtle px-3 py-2">
                                    <span class="text-[12.5px] font-semibold">{{ $h['label'] }}</span>
                                    <span class="text-[11px] text-ttn-text2">{{ $h['date'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3.5">
                    <div class="flex justify-between items-center mb-3">
                        <div class="text-xs font-bold uppercase tracking-wide text-ttn-text2">{{ __('officer.internal_notes') }} <span class="normal-case font-medium opacity-70">{{ __('officer.staff_only') }}</span></div>
                    </div>
                    <div class="flex flex-col gap-2 mb-3">
                        @forelse ($selected['notes'] as $note)
                            <div class="text-[12px] leading-relaxed text-ttn-text2 bg-ttn-subtle rounded-lg px-3 py-2">{{ $note }}</div>
                        @empty
                            <div class="text-[12.5px] text-ttn-text2">{{ __('officer.no_notes') }}</div>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('officer.queue.note', $selected['id']) }}" class="flex gap-2">
                        @csrf
                        <input name="notes" placeholder="{{ __('officer.note_placeholder') }}" required class="flex-1 min-w-0 rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        <button class="shrink-0 rounded-lg bg-ttn-primary px-3.5 py-2 text-xs font-bold text-white cursor-pointer">{{ __('officer.add') }}</button>
                    </form>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <form method="POST" action="{{ route('officer.queue.approve', $selected['id']) }}">
                        @csrf
                        <button class="w-full rounded-lg bg-ttn-primary-dark py-2.5 text-[12.5px] font-bold text-white cursor-pointer">{{ __('officer.approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('officer.queue.reject', $selected['id']) }}">
                        @csrf
                        <button class="w-full rounded-lg border border-ttn-border bg-ttn-card py-2.5 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('officer.reject') }}</button>
                    </form>
                </div>
            @else
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-8 text-center text-[13px] text-ttn-text2">
                    {{ __('officer.select_item') }}
                </div>
            @endif
        </div>
    </div>
</x-officer-shell>
