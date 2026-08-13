<x-officer-shell :officer="$officer" active="feedback" :title="__('feedback.ops_title')">
    <div class="p-4 sm:p-8 max-w-[820px]">
        <a href="{{ route('officer.feedback.index') }}" class="text-[12.5px] font-semibold text-ttn-text2 mb-4 inline-block">&larr; {{ __('feedback.ops_detail_back') }}</a>

        @if (session('status'))
            <div class="rounded-lg bg-ttn-primary-light px-3.5 py-2.5 text-[12.5px] font-semibold text-ttn-primary-dark mb-4">{{ session('status') }}</div>
        @endif

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <div class="font-display text-[15px] font-bold mb-1">{{ __('feedback.ops_category_' . $item->category) }}{{ $item->subcategory ? ' — ' . __('feedback.' . $item->category . '_' . $item->subcategory) : '' }}</div>
                    <div class="text-[11.5px] text-ttn-text2">{{ $item->created_at->format('d M Y, H:i') }}</div>
                </div>
                <div class="flex gap-2">
                    <span class="rounded-full px-2.5 py-1 text-[10.5px] font-bold
                        {{ $item->priority === 'critical' ? 'bg-ttn-red-bg text-ttn-red' : ($item->priority === 'high' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                        {{ __('feedback.ops_priority_' . $item->priority) }}
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-[10.5px] font-bold
                        {{ $item->status === 'resolved' ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($item->status === 'in_review' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2') }}">
                        {{ __('feedback.status_' . $item->status) }}
                    </span>
                </div>
            </div>

            @if ($item->sentiment)
                <div class="mb-3 text-[13px]">
                    {{ ['like' => '👍', 'neutral' => '😐', 'dislike' => '👎'][$item->sentiment] ?? '' }}
                </div>
            @endif

            <div class="mb-4">
                <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-1.5">{{ __('feedback.ops_detail_candidate_info') }}</div>
                <div class="text-[13px] font-semibold">{{ $item->candidate?->full_name ?? '—' }}</div>
                <div class="text-[12px] text-ttn-text2">{{ $item->candidate?->email }} @if($item->candidate?->phone) &middot; {{ $item->candidate->phone }} @endif</div>
            </div>

            @if ($item->context_label)
                <div class="mb-4">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-1.5">{{ __('feedback.ops_detail_context') }}</div>
                    <div class="text-[12.5px]">{{ $item->context_label }}</div>
                </div>
            @endif

            @if ($item->message)
                <div class="mb-2">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-1.5">{{ __('feedback.ops_detail_message') }}</div>
                    <div class="text-[13px] leading-relaxed rounded-lg bg-ttn-subtle px-3.5 py-3 whitespace-pre-line">{{ $item->message }}</div>
                </div>
            @endif
        </div>

        {{-- Status + assign --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-4">
            <div class="flex flex-wrap gap-2.5 mb-4">
                <form method="POST" action="{{ route('officer.feedback.status', $item) }}">
                    @csrf
                    <input type="hidden" name="status" value="in_review">
                    <button class="rounded-lg border border-ttn-border px-3.5 py-2 text-[12px] font-bold cursor-pointer">{{ __('feedback.ops_detail_mark_in_review') }}</button>
                </form>
                <form method="POST" action="{{ route('officer.feedback.status', $item) }}">
                    @csrf
                    <input type="hidden" name="status" value="resolved">
                    <button class="rounded-lg bg-ttn-primary px-3.5 py-2 text-[12px] font-bold text-white cursor-pointer">{{ __('feedback.ops_detail_mark_resolved') }}</button>
                </form>
            </div>

            <form method="POST" action="{{ route('officer.feedback.assign', $item) }}" class="flex items-center gap-2.5">
                @csrf
                <div class="text-[12.5px] font-bold">{{ __('feedback.ops_detail_assign_label') }}</div>
                <select name="officer_id" onchange="this.form.submit()" class="rounded-lg border border-ttn-border px-2.5 py-1.5 text-[12.5px]">
                    <option value="">{{ __('feedback.ops_detail_assign_unassigned') }}</option>
                    @foreach ($assignableOfficers as $id => $name)
                        <option value="{{ $id }}" @selected($item->assigned_officer_id === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Respond --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-4">
            <form method="POST" action="{{ route('officer.feedback.respond', $item) }}">
                @csrf
                <div class="text-[12.5px] font-bold mb-1.5">{{ __('feedback.ops_detail_response_label') }}</div>
                @if ($item->staff_response)
                    <div class="mb-2.5 rounded-lg bg-ttn-primary-light px-3.5 py-2.5 text-[12.5px] text-ttn-primary-dark whitespace-pre-line">{{ $item->staff_response }}</div>
                @endif
                <textarea name="staff_response" rows="3" placeholder="{{ __('feedback.ops_detail_response_placeholder') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px] mb-2.5"></textarea>
                <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('feedback.ops_detail_send_response') }}</button>
            </form>
        </div>

        {{-- Internal notes + resolution --}}
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-4">
            <form method="POST" action="{{ route('officer.feedback.notes', $item) }}">
                @csrf
                <div class="text-[12.5px] font-bold mb-1.5">{{ __('feedback.ops_detail_notes_label') }}</div>
                <textarea name="internal_notes" rows="3" placeholder="{{ __('feedback.ops_detail_notes_placeholder') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px] mb-3">{{ $item->internal_notes }}</textarea>

                <div class="text-[12.5px] font-bold mb-1.5">{{ __('feedback.ops_detail_resolution_label') }}</div>
                <textarea name="resolution" rows="2" placeholder="{{ __('feedback.ops_detail_resolution_placeholder') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px] mb-2.5">{{ $item->resolution }}</textarea>

                <button class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold cursor-pointer">{{ __('feedback.ops_detail_save_notes') }}</button>
            </form>
        </div>

        {{-- Previous submissions --}}
        @if ($previous->isNotEmpty())
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5">
                <div class="text-[12.5px] font-bold mb-3">{{ __('feedback.ops_detail_previous') }}</div>
                <div class="flex flex-col gap-2">
                    @foreach ($previous as $p)
                        <a href="{{ route('officer.feedback.show', $p) }}" class="flex items-center justify-between rounded-lg bg-ttn-subtle px-3.5 py-2.5 hover:bg-ttn-border/40">
                            <div>
                                <div class="text-[12px] font-semibold">{{ __('feedback.ops_category_' . $p->category) }}{{ $p->subcategory ? ' — ' . __('feedback.' . $p->category . '_' . $p->subcategory) : '' }}</div>
                                <div class="text-[11px] text-ttn-text2">{{ $p->created_at->format('d M Y') }}</div>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold
                                {{ $p->status === 'resolved' ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-subtle text-ttn-text2' }}">
                                {{ __('feedback.status_' . $p->status) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-officer-shell>
