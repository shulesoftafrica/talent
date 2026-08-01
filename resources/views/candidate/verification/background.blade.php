<x-candidate-shell :candidate="$candidate" active="profile" :title="__('verification.background.title')" :subtitle="__('verification.background.subtitle')">
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

    <div class="mb-4 rounded-lg bg-ttn-red-bg px-4 py-3 text-[12px] text-ttn-red">{{ __('verification.background.warning') }}</div>

    <form method="POST" action="{{ route('candidate.verification.background.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
        @csrf

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 flex flex-col gap-3">
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.background.certificate') }}</label>
                <input type="file" name="certificate" accept="application/pdf" class="w-full text-[12.5px]">
                @if ($detail->certificate_path)
                    <div class="mt-1 text-[11.5px] text-ttn-primary-dark font-semibold">{{ __('verification.identity.file_on_file') }}</div>
                @endif
                <div class="text-[11px] text-ttn-text2 mt-1.5">{{ __('verification.pdf_only_2mb') }}</div>
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.background.country_issued') }}</label>
                <input type="text" name="country_issued" value="{{ old('country_issued', $detail->country_issued) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.background.certificate_number') }}</label>
                <input type="text" name="certificate_number" value="{{ old('certificate_number', $detail->certificate_number) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.background.issue_date') }}</label>
                <input type="date" name="issue_date" value="{{ old('issue_date', optional($detail->issue_date)->toDateString()) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.background.expiry_date') }}</label>
                <input type="date" name="expiry_date" value="{{ old('expiry_date', optional($detail->expiry_date)->toDateString()) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
        </div>

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
</x-candidate-shell>
