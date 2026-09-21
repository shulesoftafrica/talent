<x-candidate-shell :candidate="$candidate" active="applications" title="Onboarding" subtitle="Documents the school needs before you start">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @php
        $required = $items->filter(fn ($i) => $i->required);
        $doneCount = $required->filter(fn ($i) => in_array($i->status, ['approved', 'waived'], true))->count();
        $percent = $required->isEmpty() ? 0 : (int) round($doneCount / $required->count() * 100);
        $chip = [
            'pending' => ['To do', 'bg-ttn-subtle text-ttn-text2'],
            'submitted' => ['Sent for review', 'bg-ttn-primary-light text-ttn-primary-dark'],
            'approved' => ['Approved', 'bg-ttn-primary-light text-ttn-primary-dark'],
            'waived' => ['Not needed', 'bg-ttn-subtle text-ttn-text2'],
            'returned' => ['Fix and resend', 'bg-ttn-red-bg text-ttn-red'],
        ];
        $accept = fn ($item) => implode(',', array_map(fn ($m) => ['application/pdf' => '.pdf', 'image/jpeg' => '.jpg,.jpeg', 'image/png' => '.png'][$m] ?? '', $item->accepts));
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">{{ session('status') }}</div>
    @endif

    <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
        <div class="flex justify-between items-center gap-3 flex-wrap mb-2">
            <div>
                <div class="font-display text-[17px] font-extrabold">{{ $job->title ?? 'Your new job' }}</div>
                <div class="text-[12.5px] text-ttn-text2">{{ $doneCount }} of {{ $required->count() }} required items approved</div>
            </div>
            <a href="{{ route('candidate.applications.offer', $application) }}" class="text-[12.5px] font-bold text-ttn-primary-dark">View your offer</a>
        </div>
        <div class="h-1.5 w-full rounded-full bg-ttn-track"><div class="h-full rounded-full bg-ttn-primary" style="width: {{ $percent }}%"></div></div>

        @if ($active)
            <div class="mt-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">Everything is approved and your staff account is active. You can now sign in to the school system with the phone number or email you gave.</div>
        @elseif (($hr->onboarding_status ?? null) === 'approved')
            <div class="mt-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">Everything is approved. The school will activate your staff account shortly and message you.</div>
        @elseif (($hr->onboarding_status ?? null) === 'submitted')
            <div class="mt-4 rounded-lg bg-ttn-subtle px-4 py-3 text-[13px] font-semibold text-ttn-text2">You have sent everything. The school is reviewing your documents.</div>
        @endif
    </div>

    @foreach ($items as $item)
        @php
            $editable = ! $active && in_array($item->status, ['pending', 'returned', 'submitted'], true);
            $label = $chip[$item->status] ?? [ucfirst($item->status), 'bg-ttn-subtle text-ttn-text2'];
            $fileNeeded = in_array($item->type, ['file', 'form_return', 'photo', 'multi_file', 'identity_document'], true);
            $value = fn ($k, $d = '') => old($k, $item->values[$k] ?? $d);
        @endphp
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3" x-data="{ member: '{{ old('member', isset($item->values['member']) ? ($item->values['member'] ? 'yes' : 'no') : '') }}', fund: '{{ $value('fund') }}' }">
            <div class="flex justify-between items-start gap-3 flex-wrap mb-1">
                <div class="text-[14px] font-bold">{{ $loop->iteration }}. {{ $item->label }}@unless ($item->required) <span class="text-[11px] font-semibold text-ttn-text2">(optional)</span>@endunless</div>
                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $label[1] }}">{{ $label[0] }}</span>
            </div>
            @if ($item->instructions)<div class="text-[12.5px] text-ttn-text2 mb-2">{{ $item->instructions }}</div>@endif

            @if ($item->status === 'returned' && $item->review_note)
                <div class="rounded-lg bg-ttn-red-bg px-3 py-2 text-[12.5px] font-semibold text-ttn-red mb-2">The school sent this back: {{ $item->review_note }}</div>
            @endif
            @if ($item->status === 'waived')<div class="text-[12.5px] text-ttn-text2">The school says you do not need to provide this.</div>@endif

            @if ($templates[$item->id] ?? null)
                <a href="{{ route('candidate.applications.onboarding.template', [$application, $item->id]) }}" class="inline-block text-[12.5px] font-bold text-ttn-primary-dark mb-2">⬇ Download the {{ $item->label }} template to sign or complete</a>
            @endif

            @if ($item->files->isNotEmpty())
                <ul class="text-[12.5px] text-ttn-text2 mb-2">
                    @foreach ($item->files as $f)<li>📎 {{ $f->original_name }}</li>@endforeach
                </ul>
            @endif

            @error("item_{$item->id}")<div class="rounded-lg bg-ttn-red-bg px-3 py-2 text-[12.5px] font-semibold text-ttn-red mb-2">{{ $message }}</div>@enderror

            @if ($editable)
                <form method="POST" action="{{ route('candidate.applications.onboarding.submit', [$application, $item->id]) }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    @if ($item->type === 'pension_declaration')
                        <div class="flex gap-4 text-[13px]">
                            <label><input type="radio" name="member" value="yes" x-model="member"> Yes, I am a member</label>
                            <label><input type="radio" name="member" value="no" x-model="member"> No</label>
                        </div>
                        <div x-show="member === 'yes'" x-cloak class="space-y-2">
                            <select name="fund" x-model="fund" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                                <option value="">Choose your fund</option>
                                <option value="NSSF">NSSF</option><option value="PSSSF">PSSSF</option><option value="other">Other</option>
                            </select>
                            <input x-show="fund === 'other'" type="text" name="fund_other" value="{{ $value('fund_other') }}" placeholder="Fund name" maxlength="100" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                            <input type="text" name="membership_number" value="{{ $value('membership_number') }}" placeholder="Membership number" maxlength="40" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                        </div>
                    @elseif ($item->type === 'identity_document')
                        <select name="doc_type" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                            <option value="">Document type</option>
                            @foreach (['national_id' => 'National ID', 'passport' => 'Passport', 'driving_licence' => 'Driving licence'] as $k => $t)
                                <option value="{{ $k }}" @selected($value('doc_type') === $k)>{{ $t }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="number" value="{{ $value('number') }}" placeholder="Document number" maxlength="40" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                    @elseif ($item->type === 'tin')
                        <input type="text" name="tin" value="{{ $value('tin') }}" inputmode="numeric" placeholder="9-digit TIN" maxlength="11" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                    @endif

                    @if ($fileNeeded || in_array($item->type, ['pension_declaration', 'tin'], true))
                        <input type="file" name="files[]" accept="{{ $accept($item) }}" {{ $item->max_files > 1 ? 'multiple' : '' }} class="block w-full text-[12.5px]">
                        <div class="text-[11.5px] text-ttn-text2">Up to {{ $item->max_files }} file(s), {{ round($item->max_size_kb / 1024) }} MB each.{{ $item->files->isNotEmpty() ? ' Choosing new files replaces the ones above.' : '' }}</div>
                    @endif

                    <button type="submit" class="rounded-lg bg-ttn-primary px-4 py-2 text-[13px] font-bold text-white cursor-pointer">{{ $item->status === 'pending' ? 'Submit' : 'Send again' }}</button>
                </form>
            @endif
        </div>
    @endforeach
</x-candidate-shell>
