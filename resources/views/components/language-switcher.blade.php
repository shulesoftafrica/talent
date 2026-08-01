@props(['dark' => false])

@php
    $locales = config('locales.supported');
    $current = $locales[app()->getLocale()] ?? $locales[config('locales.default')];
@endphp

<div x-data="{ langOpen: false }" class="relative">
    <button
        @click="langOpen = !langOpen"
        type="button"
        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[12px] font-semibold cursor-pointer {{ $dark ? 'text-white/75 hover:bg-white/10' : 'text-ttn-text2 hover:bg-ttn-subtle border border-ttn-border' }}"
    >
        <span>{{ $current['flag'] }}</span>
        <span>{{ $current['native'] }}</span>
        <span class="text-[10px]" :class="langOpen ? 'rotate-180' : ''">▾</span>
    </button>

    <div
        x-show="langOpen" x-cloak
        @click.outside="langOpen = false"
        class="absolute right-0 z-30 mt-1.5 w-40 overflow-hidden rounded-xl border border-ttn-border bg-ttn-card p-1 shadow-lg"
    >
        @foreach ($locales as $code => $meta)
            <a
                href="{{ route('language.switch', $code) }}"
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-[12.5px] font-semibold {{ $code === app()->getLocale() ? 'bg-ttn-primary-light text-ttn-primary-dark' : 'text-ttn-text hover:bg-ttn-subtle' }}"
            >
                <span>{{ $meta['flag'] }}</span>
                <span>{{ $meta['native'] }}</span>
            </a>
        @endforeach
    </div>
</div>
