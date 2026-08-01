<x-candidate-shell :candidate="$candidate" active="profile" :title="__('verification.identity.title')" :subtitle="__('verification.identity.subtitle')">
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

    <form method="POST" action="{{ route('candidate.verification.identity.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
        @csrf

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6">
            <div class="font-display text-[14px] font-bold mb-1">{{ __('verification.identity.primary_title') }}</div>
            <div class="text-[12px] text-ttn-text2 mb-3">{{ __('verification.identity.primary_hint') }}</div>

            <div class="flex flex-col gap-2 mb-3">
                @foreach (['national_id' => __('verification.identity.national_id'), 'passport' => __('verification.identity.passport'), 'driving_licence' => __('verification.identity.driving_licence')] as $value => $label)
                    <label class="flex items-center gap-2 text-[13px] font-semibold cursor-pointer">
                        <input type="radio" name="primary_doc_type" value="{{ $value }}" @checked(old('primary_doc_type', $detail->primary_doc_type) === $value) class="h-4 w-4">
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <input type="file" name="primary_doc" accept="application/pdf" class="w-full text-[12.5px]">
            @if ($detail->primary_doc_path)
                <div class="mt-1.5 text-[11.5px] text-ttn-primary-dark font-semibold">{{ __('verification.identity.file_on_file') }}</div>
            @endif
            <div class="text-[11px] text-ttn-text2 mt-1.5">{{ __('verification.pdf_only_2mb') }}</div>
        </div>

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6">
            <div class="font-display text-[14px] font-bold mb-1">{{ __('verification.identity.additional_title') }}</div>
            <div class="text-[12px] text-ttn-text2 mb-3">{{ __('verification.identity.additional_hint') }}</div>

            <div class="flex flex-col gap-3">
                <div>
                    <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.identity.tin') }}</label>
                    <input type="file" name="tin_certificate" accept="application/pdf" class="w-full text-[12.5px]">
                    @if ($detail->tin_certificate_path)
                        <div class="mt-1 text-[11.5px] text-ttn-primary-dark font-semibold">{{ __('verification.identity.file_on_file') }}</div>
                    @endif
                </div>
                <div>
                    <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.identity.local_govt_letter') }}</label>
                    <input type="file" name="local_government_letter" accept="application/pdf" class="w-full text-[12.5px]">
                    @if ($detail->local_government_letter_path)
                        <div class="mt-1 text-[11.5px] text-ttn-primary-dark font-semibold">{{ __('verification.identity.file_on_file') }}</div>
                    @endif
                </div>
                <div>
                    <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.identity.pension_number') }}</label>
                    <input type="text" name="pension_fund_number" value="{{ old('pension_fund_number', $detail->pension_fund_number) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
                </div>
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
