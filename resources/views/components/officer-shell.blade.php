@props(['officer', 'active', 'title'])

<x-layout :title="$title . ' — ShuleSoft Ops'">
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen flex-col lg:flex-row">
        {{-- Mobile top bar --}}
        <div class="flex items-center justify-between p-3 text-white lg:hidden" style="background: var(--color-ttn-navy)">
            <button @click="sidebarOpen = true" type="button" class="flex h-9 w-9 items-center justify-center rounded-lg text-lg text-white/85 hover:bg-white/10 cursor-pointer">
                ☰
            </button>
            <div class="flex items-center gap-2">
<x-brand-logo size="h-6 w-6" />
                <span class="font-display text-[13px] font-bold">{{ __('nav.ops_brand') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <x-theme-toggle :dark="true" />
            </div>
        </div>

        {{-- Mobile drawer backdrop --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

        {{-- Sidebar: off-canvas drawer on mobile/tablet, static column at lg+ --}}
        <div
            @click.outside="sidebarOpen = false"
            class="fixed inset-y-0 left-0 z-50 flex w-[230px] shrink-0 flex-col p-4 text-white transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            style="background: var(--color-ttn-navy)"
        >
            <div class="flex items-center justify-between mb-0.5">
                <div class="flex items-center gap-2">
    <x-brand-logo size="h-6 w-6" />
                    <span class="font-display text-[13px] font-bold">{{ __('nav.ops_brand') }}</span>
                </div>
                <button @click="sidebarOpen = false" type="button" class="flex h-7 w-7 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 cursor-pointer lg:hidden">✕</button>
            </div>
            <div class="text-[10.5px] font-semibold text-ttn-amber mb-5">{{ __('nav.ops_subtitle') }}</div>

            <nav class="flex flex-col gap-0.5">
                <a href="{{ route('officer.dashboard') }}"
                   class="rounded-lg px-3 py-2.5 text-[13.5px] font-semibold {{ $active === 'dashboard' ? 'bg-ttn-amber text-ttn-navy' : 'text-white/75 hover:bg-white/10' }}">
                    📊 {{ __('nav.ops_dashboard') }}
                </a>
                <a href="{{ route('officer.queue') }}"
                   class="rounded-lg px-3 py-2.5 text-[13.5px] font-semibold {{ $active === 'queue' ? 'bg-ttn-amber text-ttn-navy' : 'text-white/75 hover:bg-white/10' }}">
                    {{ __('nav.ops_queue') }}
                </a>
                <a href="{{ route('officer.ai-usage') }}"
                   class="rounded-lg px-3 py-2.5 text-[13.5px] font-semibold {{ $active === 'ai-usage' ? 'bg-ttn-amber text-ttn-navy' : 'text-white/75 hover:bg-white/10' }}">
                    🤖 {{ __('nav.ops_ai_usage') }}
                </a>
            </nav>

            <div class="mt-auto flex flex-col gap-3 border-t border-white/15 pt-3">
                <div class="hidden items-center gap-1.5 lg:flex">
                    <x-theme-toggle :dark="true" />
                    <x-language-switcher :dark="true" />
                </div>
                <div class="flex items-center gap-1.5 lg:hidden">
                    <x-language-switcher :dark="true" />
                </div>
                <form method="POST" action="{{ route('officer.logout') }}">
                    @csrf
                    <button class="text-[11.5px] font-semibold text-white/60 cursor-pointer">{{ __('nav.ops_sign_out', ['name' => $officer->name]) }}</button>
                </form>
            </div>
        </div>

        <div class="flex-1 min-w-0">
            {{ $slot }}
        </div>
    </div>
</x-layout>
