<x-candidate-shell :candidate="$candidate" active="profile" :title="__('profile.title')">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-ttn-red-bg px-4 py-3 text-[13px] font-semibold text-ttn-red">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Personal Information --}}
    <div x-data="{ editing: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-4">
            <div class="font-display text-[15px] font-bold">{{ __('profile.personal_info') }}</div>
            <button @click="editing = !editing" type="button" class="text-xs font-bold text-ttn-primary-dark cursor-pointer">
                <span x-show="!editing">{{ __('common.edit') }}</span>
                <span x-show="editing" x-cloak>{{ __('common.cancel') }}</span>
            </button>
        </div>

        <div x-show="!editing" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.full_name') }}</div>
                <div class="rounded-lg border border-ttn-border px-3 py-2.5 text-[13.5px] font-medium">{{ $candidate->full_name }}</div>
            </div>
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.phone') }}</div>
                <div class="rounded-lg border border-ttn-border px-3 py-2.5 text-[13.5px] font-medium">{{ $candidate->phone }}</div>
            </div>
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.preferred_location') }}</div>
                <div class="rounded-lg border border-ttn-border px-3 py-2.5 text-[13.5px] font-medium">{{ $candidate->current_location ?: '—' }}</div>
            </div>
            <div>
                <div class="flex items-center gap-1.5 text-[11.5px] font-semibold text-ttn-amber-text mb-1.5">
                    @if ($candidate->current_employer)
                        {{ __('profile.current_employer_pending') }} <span class="font-normal">{{ __('profile.pending_verification_paren') }}</span>
                    @else
                        {{ __('profile.current_employer') }}
                    @endif
                </div>
                <div class="rounded-lg border {{ $candidate->current_employer ? 'border-ttn-amber bg-ttn-amber-bg' : 'border-ttn-border' }} px-3 py-2.5 text-[13.5px] font-medium">
                    {{ $candidate->current_employer ?: '—' }}
                </div>
            </div>
        </div>

        <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            @csrf
            @method('PUT')
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.full_name') }}</div>
                <input name="full_name" value="{{ old('full_name', $candidate->full_name) }}" required class="w-full rounded-lg border border-ttn-border px-3 py-2.5 text-[13.5px]">
            </div>
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.phone') }}</div>
                <div class="rounded-lg border border-ttn-border bg-ttn-subtle px-3 py-2.5 text-[13.5px] font-medium text-ttn-text2">{{ $candidate->phone }}</div>
            </div>
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.preferred_location') }}</div>
                <input name="current_location" value="{{ old('current_location', $candidate->current_location) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2.5 text-[13.5px]">
            </div>
            <div>
                <div class="text-[11.5px] font-semibold text-ttn-text2 mb-1.5">{{ __('profile.current_employer') }}</div>
                <input name="current_employer" value="{{ old('current_employer', $candidate->current_employer) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2.5 text-[13.5px]">
                @if ($candidate->current_employer)
                    <div class="text-[10.5px] text-ttn-amber-text mt-1">{{ __('profile.edit_will_reverify') }}</div>
                @endif
            </div>
            <div class="sm:col-span-2">
                <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_changes') }}</button>
            </div>
        </form>
    </div>

    {{-- Profile Completion --}}
    <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="font-display text-[15px] font-bold mb-1">{{ __('profile.completion_title') }}</div>
        <div class="text-xs text-ttn-text2 mb-3.5">{{ __('profile.completion_desc') }}</div>
        <div class="flex flex-col gap-2.5">
            @foreach ($profileCompletion as $pc)
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-[12.5px] font-semibold">{{ $pc['label'] }}</span>
                        <span class="text-xs font-bold text-ttn-text2">{{ $pc['pct'] }}%</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-ttn-track">
                        <div class="h-full rounded-full bg-ttn-primary" style="width: {{ $pc['pct'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @include('candidate.career-builder._card')

    {{-- Experience --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-3.5">
            <div class="font-display text-[15px] font-bold">{{ __('profile.experience') }}</div>
            <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-lg bg-ttn-primary text-lg font-bold text-white cursor-pointer">+</button>
        </div>
        <div class="flex flex-col gap-3">
            @forelse ($candidate->experiences as $exp)
                <div x-data="{ editing: false }" class="rounded-lg bg-ttn-subtle p-3.5">
                    <div x-show="!editing">
                        <div class="flex justify-between items-start gap-2.5 flex-wrap">
                            <div class="text-[13.5px] font-bold">{{ $exp->title }} — {{ $exp->organization }}</div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10.5px] font-bold {{ $exp->is_verified ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-subtle text-ttn-text2 border border-ttn-border' }}">
                                {{ $exp->is_verified ? __('profile.employment_verified') : __('profile.pending_verification') }}
                            </span>
                        </div>
                        <div class="text-xs text-ttn-text2 mb-2">
                            {{ $exp->start_date?->format('M Y') }} – {{ $exp->is_current ? __('profile.present') : ($exp->end_date?->format('M Y') ?? '—') }}
                            @if ($exp->location) &middot; {{ $exp->location }} @endif
                        </div>
                        @if ($exp->tasks)
                            <div class="flex flex-col gap-1 mb-2">
                                @foreach ($exp->tasks as $task)
                                    <div class="flex gap-1.5 text-[12.5px] leading-relaxed"><span class="text-ttn-text2">•</span><span>{{ $task }}</span></div>
                                @endforeach
                            </div>
                        @endif
                        <div class="flex gap-3">
                            <button @click="editing = true" type="button" class="text-[11.5px] font-bold text-ttn-primary-dark cursor-pointer">{{ __('common.edit') }}</button>
                            <form method="POST" action="{{ route('candidate.profile.experiences.destroy', $exp) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11.5px] font-bold text-ttn-red cursor-pointer">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.experiences.update', $exp) }}" class="flex flex-col gap-2.5">
                        @csrf
                        @method('PUT')
                        <input name="title" value="{{ $exp->title }}" placeholder="{{ __('profile.job_title_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <input name="organization" value="{{ $exp->organization }}" placeholder="{{ __('profile.organization_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <input name="location" value="{{ $exp->location }}" placeholder="{{ __('profile.location_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <input name="start_date" type="date" value="{{ $exp->start_date?->format('Y-m-d') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                            <input name="end_date" type="date" value="{{ $exp->end_date?->format('Y-m-d') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        </div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-ttn-text2">
                            <input type="checkbox" name="is_current" value="1" @checked($exp->is_current)> {{ __('profile.currently_work_here') }}
                        </label>
                        <textarea name="tasks" placeholder="{{ __('profile.tasks_placeholder') }}" rows="3" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">{{ implode("\n", $exp->tasks ?? []) }}</textarea>
                        @if ($exp->is_verified)
                            <div class="text-[10.5px] text-ttn-amber-text">{{ __('profile.edit_will_reverify') }}</div>
                        @endif
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_changes') }}</button>
                            <button @click="editing = false" type="button" class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="text-[13px] text-ttn-text2">{{ __('profile.no_experience') }}</div>
            @endforelse
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('candidate.profile.experiences.store') }}" class="mt-4 flex flex-col gap-2.5 border-t border-ttn-hairline pt-4">
            @csrf
            <input name="title" placeholder="{{ __('profile.job_title_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <input name="organization" placeholder="{{ __('profile.organization_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <input name="location" placeholder="{{ __('profile.location_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <input name="start_date" type="date" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                <input name="end_date" type="date" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            </div>
            <label class="flex items-center gap-2 text-xs font-semibold text-ttn-text2">
                <input type="checkbox" name="is_current" value="1"> {{ __('profile.currently_work_here') }}
            </label>
            <textarea name="tasks" placeholder="{{ __('profile.tasks_placeholder') }}" rows="3" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]"></textarea>
            <button class="self-start rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_experience') }}</button>
        </form>
    </div>

    {{-- Education --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-3.5">
            <div class="font-display text-[15px] font-bold">{{ __('profile.education') }}</div>
            <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-lg bg-ttn-primary text-lg font-bold text-white cursor-pointer">+</button>
        </div>
        <div class="flex flex-col gap-2.5">
            @forelse ($candidate->educations as $ed)
                <div x-data="{ editing: false }" class="rounded-lg bg-ttn-subtle p-3.5">
                    <div x-show="!editing">
                        <div class="flex justify-between items-start gap-2.5 flex-wrap">
                            <div>
                                <div class="text-[13.5px] font-bold">{{ $ed->degree }} — {{ $ed->school }}</div>
                                <div class="text-xs text-ttn-text2">{{ $ed->start_year }} – {{ $ed->end_year }}</div>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10.5px] font-bold {{ $ed->status === 'Verified' ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-subtle text-ttn-text2 border border-ttn-border' }}">
                                {{ $ed->status === 'Verified' ? __('common.verified') : $ed->status }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-2.5">
                            @if ($ed->status !== 'Verified' && config('services.verification_enabled'))
                                <a href="{{ route('candidate.verification.show') }}" class="inline-block rounded-md bg-ttn-primary px-3.5 py-1.5 text-xs font-bold text-white">{{ __('profile.verify_now') }}</a>
                            @endif
                            <button @click="editing = true" type="button" class="text-[11.5px] font-bold text-ttn-primary-dark cursor-pointer">{{ __('common.edit') }}</button>
                            <form method="POST" action="{{ route('candidate.profile.educations.destroy', $ed) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11.5px] font-bold text-ttn-red cursor-pointer">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.educations.update', $ed) }}" class="flex flex-col gap-2.5">
                        @csrf
                        @method('PUT')
                        <input name="degree" value="{{ $ed->degree }}" placeholder="{{ __('profile.degree_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <input name="school" value="{{ $ed->school }}" placeholder="{{ __('profile.institution_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <input name="start_year" value="{{ $ed->start_year }}" placeholder="{{ __('profile.start_year_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                            <input name="end_year" value="{{ $ed->end_year }}" placeholder="{{ __('profile.end_year_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        </div>
                        @if ($ed->status === 'Verified')
                            <div class="text-[10.5px] text-ttn-amber-text">{{ __('profile.edit_will_reverify') }}</div>
                        @endif
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_changes') }}</button>
                            <button @click="editing = false" type="button" class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="text-[13px] text-ttn-text2">{{ __('profile.no_education') }}</div>
            @endforelse
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('candidate.profile.educations.store') }}" class="mt-4 flex flex-col gap-2.5 border-t border-ttn-hairline pt-4">
            @csrf
            <input name="degree" placeholder="{{ __('profile.degree_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <input name="school" placeholder="{{ __('profile.institution_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <input name="start_year" placeholder="{{ __('profile.start_year_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                <input name="end_year" placeholder="{{ __('profile.end_year_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            </div>
            <button class="self-start rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_education') }}</button>
        </form>
    </div>

    {{-- Certifications --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-3.5">
            <div class="font-display text-[15px] font-bold">{{ __('profile.certifications') }}</div>
            <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-lg bg-ttn-primary text-lg font-bold text-white cursor-pointer">+</button>
        </div>
        <div class="flex flex-col gap-2">
            @forelse ($candidate->certifications as $cert)
                <div x-data="{ editing: false }" class="rounded-lg bg-ttn-subtle p-3">
                    <div x-show="!editing">
                        <div class="flex justify-between items-center gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[13px] font-bold">{{ $cert->name }}</span>
                                @if ($cert->category)
                                    <span class="rounded-full bg-ttn-border px-2 py-0.5 text-[9.5px] font-semibold uppercase tracking-wide text-ttn-text2">{{ $cert->category }}</span>
                                @endif
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $cert->status === 'Verified' ? 'bg-ttn-primary-light text-ttn-primary-dark' : ($cert->status === 'Pending' ? 'bg-ttn-amber-bg text-ttn-amber-text' : 'bg-ttn-subtle text-ttn-text2 border border-ttn-border') }}">
                                {{ $cert->status === 'Verified' ? __('common.verified') : $cert->status }}
                            </span>
                        </div>
                        <div class="text-[11.5px] text-ttn-text2 mt-0.5">
                            {{ __('profile.issued_by', ['issuer' => $cert->issuer ?: __('profile.unknown')]) }}
                            @if ($cert->expires_at) &middot; {{ __('profile.expires', ['date' => $cert->expires_at->format('M Y')]) }} @endif
                        </div>
                        <div class="flex gap-3 mt-2">
                            <button @click="editing = true" type="button" class="text-[11px] font-bold text-ttn-primary-dark cursor-pointer">{{ __('common.edit') }}</button>
                            <form method="POST" action="{{ route('candidate.profile.certifications.destroy', $cert) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11px] font-bold text-ttn-red cursor-pointer">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.certifications.update', $cert) }}" class="flex flex-col gap-2.5">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $cert->name }}" placeholder="{{ __('profile.cert_name_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <input name="issuer" value="{{ $cert->issuer }}" placeholder="{{ __('profile.issuer_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <input name="category" value="{{ $cert->category }}" placeholder="{{ __('profile.category_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <input name="issued_at" type="date" value="{{ $cert->issued_at?->format('Y-m-d') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                            <input name="expires_at" type="date" value="{{ $cert->expires_at?->format('Y-m-d') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        </div>
                        @if ($cert->status === 'Verified')
                            <div class="text-[10.5px] text-ttn-amber-text">{{ __('profile.edit_will_reverify') }}</div>
                        @endif
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_changes') }}</button>
                            <button @click="editing = false" type="button" class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="text-[13px] text-ttn-text2">{{ __('profile.no_certifications') }}</div>
            @endforelse
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('candidate.profile.certifications.store') }}" class="mt-4 flex flex-col gap-2.5 border-t border-ttn-hairline pt-4">
            @csrf
            <input name="name" placeholder="{{ __('profile.cert_name_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <input name="issuer" placeholder="{{ __('profile.issuer_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <input name="category" placeholder="{{ __('profile.category_placeholder') }}" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <input name="issued_at" type="date" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                <input name="expires_at" type="date" class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            </div>
            <button class="self-start rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_certification') }}</button>
        </form>
    </div>

    {{-- Skills --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-2.5">
            <div class="font-display text-[15px] font-bold">{{ __('profile.skills') }}</div>
            <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-lg bg-ttn-primary text-lg font-bold text-white cursor-pointer">+</button>
        </div>
        @php $verifiedSkills = $candidate->skills->where('is_verified', true); $otherSkills = $candidate->skills->where('is_verified', false); @endphp
        @if ($verifiedSkills->isNotEmpty())
            <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2">{{ __('profile.verified_skills') }}</div>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($verifiedSkills as $skill)
                    <div x-data="{ editing: false }" class="inline-flex items-center gap-1 rounded-full bg-ttn-primary-light pl-3.5 pr-1.5 py-1">
                        <span x-show="!editing" class="text-xs font-semibold text-ttn-primary-dark">✓ {{ $skill->name }}</span>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.skills.update', $skill) }}" class="flex items-center gap-1">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $skill->name }}" required class="w-28 rounded-md border border-ttn-primary px-1.5 py-0.5 text-xs bg-ttn-card">
                            <button class="text-xs text-ttn-primary-dark font-bold cursor-pointer">✓</button>
                        </form>
                        <button @click="editing = !editing" type="button" class="text-[10px] text-ttn-primary-dark opacity-70 hover:opacity-100 cursor-pointer px-1">✎</button>
                        <form method="POST" action="{{ route('candidate.profile.skills.destroy', $skill) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                            @csrf
                            @method('DELETE')
                            <button class="text-[10px] text-ttn-red opacity-70 hover:opacity-100 cursor-pointer px-1">✕</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-2">{{ __('profile.other_skills') }}</div>
        <div class="flex flex-wrap gap-2">
            @forelse ($otherSkills as $skill)
                <div x-data="{ editing: false }" class="inline-flex items-center gap-1 rounded-full bg-ttn-subtle pl-3.5 pr-1.5 py-1">
                    <span x-show="!editing" class="text-xs font-semibold">{{ $skill->name }}</span>
                    <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.skills.update', $skill) }}" class="flex items-center gap-1">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $skill->name }}" required class="w-28 rounded-md border border-ttn-border px-1.5 py-0.5 text-xs bg-ttn-card">
                        <button class="text-xs font-bold cursor-pointer">✓</button>
                    </form>
                    <button @click="editing = !editing" type="button" class="text-[10px] text-ttn-text2 hover:text-ttn-primary-dark cursor-pointer px-1">✎</button>
                    <form method="POST" action="{{ route('candidate.profile.skills.destroy', $skill) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                        @csrf
                        @method('DELETE')
                        <button class="text-[10px] text-ttn-red opacity-70 hover:opacity-100 cursor-pointer px-1">✕</button>
                    </form>
                </div>
            @empty
                <div class="text-[13px] text-ttn-text2">{{ __('profile.no_skills') }}</div>
            @endforelse
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('candidate.profile.skills.store') }}" class="mt-4 flex gap-2.5 border-t border-ttn-hairline pt-4">
            @csrf
            <input name="name" placeholder="{{ __('profile.skill_placeholder') }}" required class="flex-1 min-w-0 rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <button class="shrink-0 rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('common.add') }}</button>
        </form>
    </div>

    {{-- Professional Portfolio --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-1">
            <div class="font-display text-[15px] font-bold">{{ __('profile.portfolio') }}</div>
            <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-lg bg-ttn-primary text-lg font-bold text-white cursor-pointer">+</button>
        </div>
        <div class="text-xs text-ttn-text2 mb-3.5">{{ __('profile.portfolio_desc') }}</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            @forelse ($candidate->portfolioItems as $item)
                <div x-data="{ editing: false }" class="rounded-lg bg-ttn-subtle p-3.5">
                    <div x-show="!editing">
                        <div class="text-[10.5px] font-bold uppercase tracking-wide text-ttn-text2 mb-1">{{ $item->type }}</div>
                        <div class="text-[13px] font-semibold">{{ $item->title }}</div>
                        @if ($item->file_size_bytes)
                            <div class="text-[11px] text-ttn-text2 mt-1">{{ number_format($item->file_size_bytes / 1024, 0) }} KB</div>
                        @endif
                        <div class="flex gap-3 mt-2">
                            <button @click="editing = true" type="button" class="text-[11px] font-bold text-ttn-primary-dark cursor-pointer">{{ __('common.edit') }}</button>
                            <form method="POST" action="{{ route('candidate.profile.portfolio.destroy', $item) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[11px] font-bold text-ttn-red cursor-pointer">{{ __('common.delete') }}</button>
                            </form>
                        </div>
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.portfolio.update', $item) }}" enctype="multipart/form-data" class="flex flex-col gap-2.5">
                        @csrf
                        @method('PUT')
                        <select name="type" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                            @foreach (['Lesson Plan' => __('profile.portfolio_type_lesson_plan'), 'Teaching Video' => __('profile.portfolio_type_teaching_video'), 'Presentation Slides' => __('profile.portfolio_type_slides'), 'Project' => __('profile.portfolio_type_project'), 'Research' => __('profile.portfolio_type_research'), 'Document' => __('profile.portfolio_type_document')] as $value => $label)
                                <option value="{{ $value }}" @selected($item->type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="title" value="{{ $item->title }}" placeholder="{{ __('profile.title_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px] bg-ttn-card">
                        <div class="text-[10.5px] text-ttn-text2">{{ __('profile.replace_file_optional') }}</div>
                        <input name="file" type="file" class="text-[13px]">
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.save_changes') }}</button>
                            <button @click="editing = false" type="button" class="rounded-lg border border-ttn-border px-4 py-2 text-[12.5px] font-bold text-ttn-text2 cursor-pointer">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="col-span-1 sm:col-span-2 text-[13px] text-ttn-text2">{{ __('profile.no_portfolio') }}</div>
            @endforelse
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('candidate.profile.portfolio.store') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-2.5 border-t border-ttn-hairline pt-4">
            @csrf
            <select name="type" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                <option value="">{{ __('profile.portfolio_type_placeholder') }}</option>
                <option value="Lesson Plan">{{ __('profile.portfolio_type_lesson_plan') }}</option>
                <option value="Teaching Video">{{ __('profile.portfolio_type_teaching_video') }}</option>
                <option value="Presentation Slides">{{ __('profile.portfolio_type_slides') }}</option>
                <option value="Project">{{ __('profile.portfolio_type_project') }}</option>
                <option value="Research">{{ __('profile.portfolio_type_research') }}</option>
                <option value="Document">{{ __('profile.portfolio_type_document') }}</option>
            </select>
            <input name="title" placeholder="{{ __('profile.title_placeholder') }}" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <input name="file" type="file" required class="text-[13px]">
            <button class="self-start rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('profile.upload') }}</button>
        </form>
    </div>

    {{-- Hobbies --}}
    <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
        <div class="flex justify-between items-center mb-3.5">
            <div class="font-display text-[15px] font-bold">{{ __('profile.hobbies') }}</div>
            <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded-lg bg-ttn-primary text-lg font-bold text-white cursor-pointer">+</button>
        </div>
        <div class="flex flex-wrap gap-2">
            @forelse ($candidate->hobbies as $hobby)
                <div x-data="{ editing: false }" class="inline-flex items-center gap-1 rounded-full bg-ttn-subtle pl-3.5 pr-1.5 py-1">
                    <span x-show="!editing" class="text-xs font-semibold">{{ $hobby->name }}</span>
                    <form x-show="editing" x-cloak method="POST" action="{{ route('candidate.profile.hobbies.update', $hobby) }}" class="flex items-center gap-1">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $hobby->name }}" required class="w-28 rounded-md border border-ttn-border px-1.5 py-0.5 text-xs bg-ttn-card">
                        <button class="text-xs font-bold cursor-pointer">✓</button>
                    </form>
                    <button @click="editing = !editing" type="button" class="text-[10px] text-ttn-text2 hover:text-ttn-primary-dark cursor-pointer px-1">✎</button>
                    <form method="POST" action="{{ route('candidate.profile.hobbies.destroy', $hobby) }}" onsubmit="return confirm('{{ addslashes(__('common.confirm_delete')) }}')">
                        @csrf
                        @method('DELETE')
                        <button class="text-[10px] text-ttn-red opacity-70 hover:opacity-100 cursor-pointer px-1">✕</button>
                    </form>
                </div>
            @empty
                <div class="text-[13px] text-ttn-text2">{{ __('profile.no_hobbies') }}</div>
            @endforelse
        </div>

        <form x-show="open" x-cloak method="POST" action="{{ route('candidate.profile.hobbies.store') }}" class="mt-4 flex gap-2.5 border-t border-ttn-hairline pt-4">
            @csrf
            <input name="name" placeholder="{{ __('profile.hobby_placeholder') }}" required class="flex-1 min-w-0 rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
            <button class="shrink-0 rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('common.add') }}</button>
        </form>
    </div>

    {{-- Career Strength --}}
    <div x-data="aiModal('{{ route('candidate.ai.profile-review') }}')" class="rounded-2xl p-4 sm:p-6 mb-4 text-white" style="background: linear-gradient(135deg, var(--color-ttn-primary), var(--color-ttn-primary-dark))">
        <div class="flex justify-between items-start gap-3.5 flex-wrap">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wide opacity-80 mb-1.5">{{ __('profile.career_strength') }}</div>
                <div class="font-display text-[34px] font-extrabold">{{ $candidate->trust_score }}<span class="text-lg opacity-70">/100</span></div>
            </div>
            <button @click="show()" class="rounded-lg bg-white/20 px-4 py-2.5 text-xs font-bold cursor-pointer">{{ __('profile.review_profile') }}</button>
        </div>

        <div x-show="open" x-cloak @click.self="close()" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-3 sm:p-5">
            <div @click.stop class="w-[440px] max-w-full max-h-[80vh] overflow-y-auto rounded-2xl bg-ttn-card p-5 sm:p-7 text-ttn-text">
                <div class="font-display text-base font-bold mb-1">{{ __('profile.ai_review_title') }}</div>
                <div class="text-[12.5px] text-ttn-text2 mb-4" x-show="!loading && !error">{{ __('profile.ai_review_desc') }}</div>
                <div x-show="loading" class="text-[13px] text-ttn-text2">{{ __('common.loading') }}</div>
                <div x-show="error" x-text="error" class="text-[13px] text-ttn-red"></div>
                <template x-if="data">
                    <div class="flex flex-col gap-2">
                        <template x-for="item in data.items" :key="item.label">
                            <div class="flex justify-between items-center gap-2.5 rounded-lg bg-ttn-subtle px-3.5 py-3">
                                <span class="text-[12.5px] font-semibold" x-text="item.label"></span>
                                <span class="text-xs font-bold text-ttn-primary-dark whitespace-nowrap" x-text="item.impact"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <div @click="close()" class="text-center text-xs font-semibold text-ttn-text2 cursor-pointer mt-4">{{ __('common.close') }}</div>
            </div>
        </div>
    </div>

    {{-- Verification --}}
    @if (config('services.verification_enabled'))
        <div id="verification" class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden mb-4">
            @foreach ($candidate->verificationItems()->with('verificationType')->get() as $item)
                <div class="flex items-center justify-between gap-2 px-4 sm:px-5 py-4 border-b border-ttn-border last:border-b-0 flex-wrap">
                    <div>
                        <div class="text-[13.5px] font-bold">{{ $item->verificationType->name }}</div>
                        <div class="text-[11.5px] text-ttn-text2">{{ __('profile.method', ['method' => $item->method ?: '—']) }}</div>
                    </div>
                    <div class="rounded-full px-3 py-1.5 text-xs font-bold {{ $item->status === \App\Services\Verification\VerificationStatus::VERIFIED ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'bg-ttn-subtle text-ttn-text2' }}">
                        {{ $item->status === \App\Services\Verification\VerificationStatus::VERIFIED ? __('common.verified') : \App\Services\Verification\VerificationStatus::label($item->status) }}
                    </div>
                </div>
            @endforeach
            @if ($candidate->verificationItems->isEmpty())
                <div class="px-5 py-6 text-center text-[13px] text-ttn-text2">
                    {{ __('profile.verification_soon') }}
                </div>
            @endif
        </div>
    @endif
</x-candidate-shell>
