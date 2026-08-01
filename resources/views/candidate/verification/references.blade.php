<x-candidate-shell :candidate="$candidate" active="profile" :title="__('verification.references.title')" :subtitle="__('verification.references.subtitle')">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-ttn-red-bg px-4 py-3 text-[13px] font-semibold text-ttn-red">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4">
        <a href="{{ route('candidate.verification.show') }}" class="text-[12.5px] font-semibold text-ttn-text2">&larr; {{ __('verification.title') }}</a>
    </div>

    <div class="mb-4 rounded-lg bg-ttn-subtle px-4 py-3 text-[12px] text-ttn-text2">
        {{ __('verification.identity.status_badge', ['status' => \App\Services\Verification\VerificationStatus::label($item->status)]) }}
    </div>

    @if ($references->isNotEmpty())
        <div class="rounded-2xl border border-ttn-border bg-ttn-card overflow-hidden mb-4">
            @foreach ($references as $reference)
                <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-t border-ttn-hairline first:border-t-0">
                    <div>
                        <div class="text-[13px] font-bold">{{ $reference->full_name }} &middot; {{ $reference->position }}</div>
                        <div class="text-[11.5px] text-ttn-text2">{{ __('verification.references.relationship_' . $reference->relationship) }} &middot; {{ $reference->experience->organization }}</div>
                        @if ($reference->corporate_email)
                            <div class="text-[11px] text-ttn-text2">{{ $reference->corporate_email }}</div>
                            @if ($reference->low_confidence_email)
                                <div class="text-[11px] text-ttn-amber-text">{{ __('verification.employment.low_confidence_note') }}</div>
                            @endif
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold whitespace-nowrap bg-ttn-subtle text-ttn-text2 border border-ttn-border">
                            {{ \App\Services\Verification\VerificationStatus::label($reference->status) }}
                        </span>
                        @if ($reference->status === \App\Services\Verification\VerificationStatus::WAITING_DOCUMENTS)
                            <form method="POST" action="{{ route('candidate.verification.references.destroy', $reference) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[11.5px] font-bold text-ttn-red cursor-pointer">{{ __('verification.references.remove') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4 text-center text-[12.5px] text-ttn-text2">
            {{ __('verification.references.none_yet') }}
        </div>
    @endif

    @if ($experiences->isEmpty())
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6 text-center">
            <div class="text-[12.5px] text-ttn-text2 mb-3">{{ __('verification.references.no_experiences') }}</div>
            <a href="{{ route('candidate.profile') }}" class="inline-block rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white">{{ __('nav.profile') }}</a>
        </div>
    @else
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 mb-4">
            <div class="font-display text-[14px] font-bold mb-3">{{ __('verification.references.add_title') }}</div>

            <form method="POST" action="{{ route('candidate.verification.references.store') }}" class="flex flex-col gap-3">
                @csrf

                <div>
                    <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.company') }}</label>
                    <select name="candidate_experience_id" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        @foreach ($experiences as $experience)
                            <option value="{{ $experience->id }}">{{ $experience->organization }} &mdash; {{ $experience->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.full_name') }}</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                    </div>
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.position') }}</label>
                        <input type="text" name="position" value="{{ old('position') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                    </div>
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.relationship') }}</label>
                        <select name="relationship" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                            @foreach ($relationships as $value)
                                <option value="{{ $value }}">{{ __('verification.references.relationship_' . $value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.corporate_email') }}</label>
                        <input type="email" name="corporate_email" value="{{ old('corporate_email') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        <div class="text-[11px] text-ttn-text2 mt-1">{{ __('verification.references.email_recommendation') }}</div>
                    </div>
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.phone') }}</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                    </div>
                    <div></div>
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.from') }}</label>
                        <input type="date" name="worked_together_from" value="{{ old('worked_together_from') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                    </div>
                    <div>
                        <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.references.to') }}</label>
                        <input type="date" name="worked_together_to" value="{{ old('worked_together_to') }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                    </div>
                </div>

                <div class="text-[11px] text-ttn-red">{{ __('verification.references.false_warning') }}</div>

                <button type="submit" class="rounded-lg bg-ttn-primary px-4 py-2.5 text-[12.5px] font-bold text-white cursor-pointer">{{ __('verification.references.add') }}</button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('candidate.verification.references.submit') }}" class="flex flex-col gap-4">
        @csrf
        @include('candidate.verification._declaration')

        <button type="submit" class="rounded-lg bg-ttn-primary px-4 py-3 text-center text-sm font-bold text-white cursor-pointer">
            {{ __('verification.submit_for_review') }}
        </button>
    </form>
</x-candidate-shell>
