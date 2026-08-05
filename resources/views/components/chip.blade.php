@props(['type' => 'checkbox', 'name', 'value', 'label', 'checked' => false])

<label class="cursor-pointer">
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @checked($checked) {{ $attributes }} class="peer hidden">
    <span class="inline-block rounded-full border border-ttn-border bg-ttn-card px-3.5 py-2 text-xs font-semibold text-ttn-text2 peer-checked:bg-ttn-primary peer-checked:text-white peer-checked:border-ttn-primary">
        {{ $label }}
    </span>
</label>
