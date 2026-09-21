<x-candidate-shell :candidate="$candidate" active="applications" title="Onboarding" subtitle="Documents the school needs before you start">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @php
        $chip = [
            'todo' => ['To do', 'bg-ttn-subtle text-ttn-text2'],
            'saved' => ['Saved', 'bg-ttn-amber-bg text-ttn-amber-text'],
            'submitted' => ['Submitted', 'bg-ttn-primary-light text-ttn-primary-dark'],
            'approved' => ['Approved', 'bg-ttn-primary-light text-ttn-primary-dark'],
            'returned' => ['Returned', 'bg-ttn-red-bg text-ttn-red'],
            'waived' => ['Not needed', 'bg-ttn-subtle text-ttn-text2'],
        ];
        $accept = fn ($item) => implode(',', array_filter(array_map(fn ($m) => ['application/pdf' => '.pdf', 'image/jpeg' => '.jpg,.jpeg', 'image/png' => '.png'][$m] ?? null, $item->accepts)));
        $done = $overview['done'] ?? 0;
        $total = $overview['total'] ?? 0;
        $percent = $total ? (int) round($done / $total * 100) : 0;
        $input = fn ($item, $key, $default = '') => old($key, $item->data[$key] ?? $default);
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">{{ session('status') }}</div>
    @endif

    @if (! $overview)
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6 text-[13.5px]">
            <div class="font-bold mb-1">Your onboarding checklist is opening.</div>
            <p class="text-ttn-text2 text-[12.5px]">You accepted the offer. The school's system is opening your checklist — this takes about a minute. Refresh this page shortly.</p>
        </div>
    @else
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
            <div class="flex justify-between items-center gap-3 flex-wrap mb-2">
                <div>
                    <div class="font-display text-[17px] font-extrabold">{{ $job->title ?? 'Your new job' }}</div>
                    <div class="text-[12.5px] text-ttn-text2">{{ $done }} of {{ $total }} complete</div>
                </div>
                <a href="{{ route('candidate.applications.offer', $application) }}" class="text-[12.5px] font-bold text-ttn-primary-dark">View your offer</a>
            </div>
            <div class="h-1.5 w-full rounded-full bg-ttn-track"><div class="h-full rounded-full bg-ttn-primary" style="width: {{ $percent }}%"></div></div>

            @if ($overview['completed'])
                <div class="mt-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">Onboarding complete — welcome! The school has approved everything.</div>
            @elseif (($hr->onboarding_status ?? null) === 'submitted')
                <div class="mt-4 rounded-lg bg-ttn-subtle px-4 py-3 text-[13px] font-semibold text-ttn-text2">You have sent everything. The school is reviewing your documents.</div>
            @else
                <form method="POST" action="{{ route('candidate.onboarding.submit-all', $application) }}" class="mt-4">
                    @csrf
                    @error('submit_all')<div class="mb-2 text-[12.5px] font-semibold text-ttn-red">{{ $message }}</div>@enderror
                    <button type="submit" @disabled(! $overview['can_submit_all'])
                            class="rounded-lg bg-ttn-primary px-5 py-2.5 text-[13.5px] font-bold text-white {{ $overview['can_submit_all'] ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}">Submit for review</button>
                    @unless ($overview['can_submit_all'])
                        <span class="ml-2 text-[12px] text-ttn-text2">Available when every required item is complete.</span>
                    @endunless
                </form>
            @endif
        </div>

        @foreach ($items as $item)
            @php $label = $chip[$item->display]; @endphp
            <div id="item-{{ $item->id }}" class="rounded-2xl border border-ttn-border bg-ttn-card p-5 mb-3"
                 x-data="{ member: '{{ old('member', isset($item->data['member']) ? ($item->data['member'] ? 'yes' : 'no') : '') }}', fund: '{{ $input($item, 'fund') }}' }">
                <div class="flex justify-between items-start gap-3 flex-wrap mb-1">
                    <div class="text-[14px] font-bold">{{ $loop->iteration }}. {{ $item->label }}@unless ($item->required) <span class="text-[11px] font-semibold text-ttn-text2">(optional)</span>@endunless</div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $label[1] }}">{{ $label[0] }}</span>
                </div>
                @if ($item->instructions)<div class="text-[12.5px] text-ttn-text2 mb-2">{{ $item->instructions }}</div>@endif

                @if ($item->display === 'returned' && $item->review_note)
                    <div class="rounded-lg bg-ttn-red-bg px-3 py-2 text-[12.5px] font-semibold text-ttn-red mb-2">The school sent this back: {{ $item->review_note }}</div>
                @endif
                @if ($item->display === 'waived')<div class="text-[12.5px] text-ttn-text2">The school says you do not need to provide this.</div>@endif
                @error("item_{$item->id}")<div class="rounded-lg bg-ttn-red-bg px-3 py-2 text-[12.5px] font-semibold text-ttn-red mb-2">{{ $message }}</div>@enderror

                {{-- Medical form: download, get it completed, upload it back --}}
                @if ($item->type === 'form_return' && $item->template_path && $item->editable)
                    <ol class="text-[12.5px] text-ttn-text2 list-decimal pl-5 mb-2 space-y-0.5">
                        <li><a href="{{ route('candidate.onboarding.template', [$application, $item->id]) }}" class="font-bold text-ttn-primary-dark">⬇ Download the {{ mb_strtolower($item->label) }} form</a></li>
                        <li>Take it to a registered hospital to be completed, signed and stamped.</li>
                        <li>Upload the completed form below.</li>
                    </ol>
                @endif

                {{-- Current values and files --}}
                @if ($item->files)
                    <ul class="text-[12.5px] mb-2 space-y-1">
                        @foreach ($item->files as $f)
                            <li class="flex items-center gap-2">
                                @if ($item->type === 'photo' && str_starts_with($f['mime'] ?? '', 'image/'))<span>🖼</span>@else<span>📎</span>@endif
                                <span>{{ ($f['label'] ?? null) ? $f['label'].' — ' : '' }}{{ $f['original_name'] }}</span>
                                @if (($f['source'] ?? '') !== 'upload')<span class="text-[11px] text-ttn-text2">({{ str_replace('_', ' ', $f['source']) }})</span>@endif
                                @if ($item->editable)
                                    <form method="POST" action="{{ route('candidate.onboarding.remove-file', [$application, $item->id, $f['id']]) }}" class="ml-auto">@csrf<button class="text-[11.5px] font-bold text-ttn-red cursor-pointer">Remove</button></form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! $item->editable)
                    @if ($item->data)
                        <dl class="text-[12.5px] text-ttn-text2 space-y-0.5">
                            @foreach ($item->data as $k => $v)
                                @php $secret = in_array($k, ['number', 'tin', 'membership_number'], true); @endphp
                                <div class="flex gap-2"><dt class="font-semibold">{{ ucfirst(str_replace('_', ' ', $k)) }}:</dt><dd>{{ $secret ? '••••'.substr((string) $v, -3) : (is_bool($v) ? ($v ? 'Yes' : 'No') : $v) }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                    @if ($item->display === 'submitted')<div class="mt-1 text-[12px] text-ttn-text2">Read-only unless the school returns it.</div>@endif
                @else
                    <form method="POST" action="{{ route('candidate.onboarding.save', [$application, $item->id]) }}" enctype="multipart/form-data" class="space-y-2">
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
                                <input x-show="fund === 'other'" type="text" name="fund_other" value="{{ $input($item, 'fund_other') }}" placeholder="Fund name" maxlength="100" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                                <input type="text" name="membership_number" value="{{ $input($item, 'membership_number') }}" placeholder="Membership number" maxlength="40" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                                <div class="text-[11.5px] text-ttn-text2">Optional: upload your membership card.</div>
                            </div>

                        @elseif ($item->type === 'identity_document')
                            <select name="doc_type" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                                <option value="">Document type</option>
                                @foreach (['national_id' => 'National ID (NIDA)', 'passport' => 'Passport', 'driving_licence' => 'Driving licence'] as $k => $t)
                                    <option value="{{ $k }}" @selected($input($item, 'doc_type') === $k)>{{ $t }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="number" value="{{ $input($item, 'number') }}" placeholder="Document number" maxlength="40" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                            <div class="text-[11.5px] text-ttn-text2">NIDA: 20 digits. Passport and licence: letters and digits. Upload the front, and the back for cards (up to 2 files).</div>
                            @if ($verifiedId)
                                <button type="submit" formaction="{{ route('candidate.onboarding.verified-id', [$application, $item->id]) }}" formnovalidate class="text-[12.5px] font-bold text-ttn-primary-dark cursor-pointer">Use my verified ID</button>
                            @endif

                        @elseif ($item->type === 'tin')
                            <input type="text" name="tin" value="{{ $input($item, 'tin') }}" inputmode="numeric" placeholder="9-digit TIN" maxlength="11" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[13px]">
                            <div class="text-[11.5px] text-ttn-text2">Dashes are fine. Optional: upload your TIN certificate.</div>

                        @elseif ($item->type === 'photo')
                            <div class="text-[11.5px] text-ttn-text2">A clear, front-facing photo of you (JPG or PNG). On a phone you can take one with the camera.</div>
                            @if ($hasAvatar)
                                <button type="submit" formaction="{{ route('candidate.onboarding.profile-photo', [$application, $item->id]) }}" formnovalidate class="text-[12.5px] font-bold text-ttn-primary-dark cursor-pointer">Use my profile photo</button>
                            @endif
                        @endif

                        @php $single = in_array($item->type, ['file', 'form_return', 'photo'], true); @endphp
                        @if ($single || in_array($item->type, ['multi_file', 'identity_document', 'pension_declaration', 'tin'], true))
                            <input type="file" name="files[]" accept="{{ $accept($item) }}"
                                   @if ($item->type === 'photo') capture="user" @endif
                                   @if (! $single && $item->max_files > 1) multiple @endif class="block w-full text-[12.5px]">
                            @if ($item->type === 'multi_file')
                                <input type="text" name="labels[]" placeholder="Label for this file (for example: Form Four certificate)" maxlength="100" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2 text-[12.5px]">
                            @endif
                            <div class="text-[11.5px] text-ttn-text2">Up to {{ $single ? 1 : $item->max_files }} file(s), {{ round($item->max_size_kb / 1024) }} MB each.{{ $single && $item->files ? ' A new file replaces the current one.' : '' }}</div>
                        @endif

                        <div class="flex gap-2 flex-wrap pt-1">
                            <button type="submit" name="intent" value="save" class="rounded-lg border border-ttn-border px-4 py-2 text-[13px] font-bold cursor-pointer">Save</button>
                            <button type="submit" name="intent" value="submit" class="rounded-lg bg-ttn-primary px-4 py-2 text-[13px] font-bold text-white cursor-pointer">Submit this item</button>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    @endif
</x-candidate-shell>
