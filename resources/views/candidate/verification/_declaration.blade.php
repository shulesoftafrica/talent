<div class="rounded-2xl border border-ttn-border bg-ttn-card p-4 sm:p-6">
    <div class="text-[12px] text-ttn-text2 mb-3">{{ __('verification.declaration_text') }}</div>
    <label class="flex items-center gap-2.5 text-[13px] font-semibold cursor-pointer">
        <input type="checkbox" name="declaration" value="1" class="h-4 w-4 cursor-pointer" @checked(old('declaration'))>
        {{ __('verification.declaration_checkbox') }}
    </label>
</div>
