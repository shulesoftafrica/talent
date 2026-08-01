@props(['dark' => false])

<button
    @click="dark = !dark"
    type="button"
    title="{{ __('common.toggle_theme') }}"
    class="flex h-8 w-8 items-center justify-center rounded-lg text-sm cursor-pointer {{ $dark ? 'text-white/75 hover:bg-white/10' : 'text-ttn-text2 hover:bg-ttn-subtle border border-ttn-border' }}"
>
    <span x-text="dark ? '☀️' : '🌙'"></span>
</button>
