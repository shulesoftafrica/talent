<x-candidate-shell :candidate="$candidate" active="profile" :title="__('verification.employment.title')" :subtitle="__('verification.employment.subtitle')">
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

    @if ($docs->isEmpty())
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6 text-center">
            <div class="text-[13px] font-semibold mb-1">{{ __('verification.employment.no_entries') }}</div>
            <div class="text-[12px] text-ttn-text2 mb-3">{{ __('verification.employment.no_entries_cta') }}</div>
            <a href="{{ route('candidate.profile') }}" class="inline-block rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white">{{ __('nav.profile') }}</a>
        </div>
    @else
        <div class="mb-4 rounded-lg bg-ttn-subtle px-4 py-3 text-[12px] text-ttn-text2">{{ __('verification.employment.contact_note') }}</div>
        <div class="mb-4 rounded-lg bg-ttn-red-bg px-4 py-3 text-[12px] text-ttn-red">{{ __('verification.employment.forged_warning') }}</div>

        <form method="POST" action="{{ route('candidate.verification.employment.store') }}" class="flex flex-col gap-4">
            @csrf

            @foreach ($docs as $doc)
                <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6">
                    <div class="font-display text-[14px] font-bold mb-1">{{ $doc->experience->title }}</div>
                    <div class="text-[12px] text-ttn-text2 mb-3">{{ $doc->experience->organization }}</div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.employer_name') }}</label>
                            <input type="text" name="employer_name_{{ $doc->id }}" value="{{ old("employer_name_{$doc->id}", $doc->employer_name) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        </div>
                        <div>
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.employer_website') }}</label>
                            <input type="url" name="employer_website_{{ $doc->id }}" value="{{ old("employer_website_{$doc->id}", $doc->employer_website) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.employer_address') }}</label>
                            <input type="text" name="employer_address_{{ $doc->id }}" value="{{ old("employer_address_{$doc->id}", $doc->employer_address) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        </div>
                        <div>
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.hr_email') }}</label>
                            <input type="email" name="hr_email_{{ $doc->id }}" value="{{ old("hr_email_{$doc->id}", $doc->hr_email) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                            @if ($doc->hr_email_low_confidence)
                                <div class="text-[11px] text-ttn-amber-text mt-1">{{ __('verification.employment.low_confidence_note') }}</div>
                            @endif
                        </div>
                        <div>
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.supervisor_name') }}</label>
                            <input type="text" name="supervisor_name_{{ $doc->id }}" value="{{ old("supervisor_name_{$doc->id}", $doc->supervisor_name) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        </div>
                        <div>
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.supervisor_email') }}</label>
                            <input type="email" name="supervisor_email_{{ $doc->id }}" value="{{ old("supervisor_email_{$doc->id}", $doc->supervisor_email) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                            @if ($doc->supervisor_email_low_confidence)
                                <div class="text-[11px] text-ttn-amber-text mt-1">{{ __('verification.employment.low_confidence_note') }}</div>
                            @endif
                        </div>
                        <div>
                            <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.employment.supervisor_phone') }}</label>
                            <input type="text" name="supervisor_phone_{{ $doc->id }}" value="{{ old("supervisor_phone_{$doc->id}", $doc->supervisor_phone) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                        </div>
                    </div>
                </div>
            @endforeach

            @include('candidate.verification._declaration')

            <div class="flex flex-col sm:flex-row gap-2.5">
                <button type="submit" name="action" value="draft" class="flex-1 rounded-lg border border-ttn-border px-4 py-3 text-center text-sm font-bold text-ttn-text2 cursor-pointer">
                    {{ __('verification.save_draft') }}
                </button>
                <button type="submit" name="action" value="submit" class="flex-1 rounded-lg bg-ttn-primary px-4 py-3 text-center text-sm font-bold text-white cursor-pointer">
                    {{ __('verification.submit_for_review') }}
                </button>
            </div>
        </form>
    @endif
</x-candidate-shell>
