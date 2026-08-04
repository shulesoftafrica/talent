<x-candidate-shell :candidate="$candidate" active="profile" :title="__('verification.license.title')" :subtitle="__('verification.license.subtitle')">
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

    <form method="POST" action="{{ route('candidate.verification.license.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
        @csrf

        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6 flex flex-col gap-3">
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.license.name') }}</label>
                <input type="text" name="license_name" value="{{ old('license_name', $detail->license_name) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.license.number') }}</label>
                <input type="text" name="license_number" value="{{ old('license_number', $detail->license_number) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.license.issuing_authority') }}</label>
                <input type="text" name="issuing_authority" value="{{ old('issuing_authority', $detail->issuing_authority) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.license.expiry_date') }}</label>
                <input type="date" name="expiry_date" value="{{ old('expiry_date', optional($detail->expiry_date)->toDateString()) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.license.verification_url') }}</label>
                <input type="url" name="verification_url" value="{{ old('verification_url', $detail->verification_url) }}" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[12.5px]">
            </div>
            <div>
                <label class="text-[12.5px] font-semibold block mb-1">{{ __('verification.license.certificate') }}</label>
                <input type="file" name="certificate" accept="application/pdf" class="w-full rounded-lg border border-dashed border-ttn-border bg-ttn-subtle px-3 py-2.5 text-[12.5px] text-ttn-text2 cursor-pointer transition-colors hover:border-ttn-primary file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-ttn-primary file:px-3.5 file:py-1.5 file:text-[12px] file:font-bold file:text-white">
                @if ($detail->certificate_path)
                    <div class="mt-1 text-[11.5px] text-ttn-primary-dark font-semibold">{{ __('verification.identity.file_on_file') }}</div>
                @endif
                <div class="text-[11px] text-ttn-text2 mt-1.5">{{ __('verification.pdf_only_2mb') }}</div>
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
