@props(['candidate', 'active', 'title', 'subtitle' => null, 'feedbackContext' => null])

<x-layout :title="$title . ' — ShuleSoft Talent Network'">
    {{-- At lg+, the whole layout is pinned to the viewport height and only
         the main column scrolls internally — the sidebar (so logout/nav is
         always reachable) and the right rail stay in place regardless of
         how long the middle content grows (e.g. an infinite-scrolling job
         list). Below lg, the sidebar is hidden anyway (bottom tab bar takes
         over), so the page just flows and scrolls normally there. --}}
    <div class="flex min-h-screen flex-col lg:h-screen lg:flex-row lg:overflow-hidden">
        {{-- Mobile top bar --}}
        <div class="flex items-center justify-between border-b border-ttn-border bg-ttn-sidebar px-4 py-3 lg:hidden">
            <div class="flex items-center gap-2">
<x-brand-logo size="h-7 w-7" />
                <span class="font-display text-[13.5px] font-bold">{{ __('nav.brand') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <x-theme-toggle />
                <x-language-switcher />
            </div>
        </div>

        {{-- Sidebar (desktop / tablet landscape) --}}
        <div class="hidden lg:flex w-[240px] xl:w-[250px] shrink-0 border-r border-ttn-border bg-ttn-sidebar flex-col p-4 lg:h-full lg:overflow-y-auto">
            <div class="flex items-center gap-2 px-2 pb-5">
<x-brand-logo size="h-7 w-7" />
                <span class="font-display text-[13.5px] font-bold">{{ __('nav.brand') }}</span>
            </div>

            <nav class="flex flex-col gap-0.5 flex-1">
                <a href="{{ route('candidate.jobs') }}"
                   class="rounded-lg px-3 py-2.5 text-[13.5px] font-semibold {{ $active === 'jobs' ? 'bg-ttn-primary text-white' : 'text-ttn-text2 hover:bg-ttn-subtle' }}">
                    🎯 {{ __('nav.jobs') }}
                </a>
                <a href="{{ route('candidate.applications.index') }}"
                   class="rounded-lg px-3 py-2.5 text-[13.5px] font-semibold {{ $active === 'applications' ? 'bg-ttn-primary text-white' : 'text-ttn-text2 hover:bg-ttn-subtle' }}">
                    📋 {{ __('nav.applications') }}
                </a>
                <a href="{{ route('candidate.profile') }}"
                   class="rounded-lg px-3 py-2.5 text-[13.5px] font-semibold {{ $active === 'profile' ? 'bg-ttn-primary text-white' : 'text-ttn-text2 hover:bg-ttn-subtle' }}">
                    👤 {{ __('nav.profile') }}
                </a>
            </nav>

            <div class="flex items-center gap-1.5 pb-3">
                <x-theme-toggle />
                <x-language-switcher />
            </div>

            <div class="flex items-center gap-2.5 border-t border-ttn-border pt-3 px-1">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ttn-primary-dark text-white text-xs font-bold">
                    {{ collect(explode(' ', $candidate->full_name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[12.5px] font-bold truncate">{{ $candidate->full_name }}</div>
                    <div class="text-[11px] text-ttn-text2 truncate">{{ $candidate->profession ?? __('nav.candidate_fallback') }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-[11px] font-semibold text-ttn-text2 cursor-pointer">{{ __('nav.exit') }}</button>
                </form>
            </div>
        </div>

        {{-- Main content: the only column that scrolls at lg+ --}}
        <div class="flex-1 min-w-0 px-4 py-5 pb-24 lg:px-7 lg:py-6 lg:pb-6 lg:max-w-[700px] lg:h-full lg:overflow-y-auto">
            <div class="mb-5">
                <h1 class="font-display text-lg lg:text-xl font-extrabold mb-0.5">{{ $title }}</h1>
                @if ($subtitle)
                    <div class="text-[12.5px] text-ttn-text2">{{ $subtitle }}</div>
                @endif
            </div>

            {{ $slot }}
        </div>

        {{-- Right rail: stacks below main content on mobile/tablet, own fixed-height scroll region at lg+ --}}
        <div class="w-full lg:w-[280px] xl:w-[290px] shrink-0 border-t lg:border-t-0 lg:border-l border-ttn-border p-4 pb-24 lg:p-5 lg:pb-5 lg:h-full lg:overflow-y-auto">
            <div class="flex flex-col gap-3.5">
                {{ $rail ?? '' }}
            </div>
        </div>

        {{-- Bottom tab bar (mobile / tablet portrait) --}}
        <nav class="fixed bottom-0 inset-x-0 z-40 flex border-t border-ttn-border bg-ttn-card pb-[env(safe-area-inset-bottom)] lg:hidden">
            <a href="{{ route('candidate.jobs') }}"
               class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[10.5px] font-semibold {{ $active === 'jobs' ? 'text-ttn-primary-dark' : 'text-ttn-text2' }}">
                <span class="text-base leading-none">🎯</span>{{ __('nav.jobs') }}
            </a>
            <a href="{{ route('candidate.applications.index') }}"
               class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[10.5px] font-semibold {{ $active === 'applications' ? 'text-ttn-primary-dark' : 'text-ttn-text2' }}">
                <span class="text-base leading-none">📋</span>{{ __('nav.applications') }}
            </a>
            <a href="{{ route('candidate.profile') }}"
               class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[10.5px] font-semibold {{ $active === 'profile' ? 'text-ttn-primary-dark' : 'text-ttn-text2' }}">
                <span class="text-base leading-none">👤</span>{{ __('nav.profile') }}
            </a>
            <form method="POST" action="{{ route('logout') }}" class="flex flex-1">
                @csrf
                <button class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[10.5px] font-semibold text-ttn-text2 cursor-pointer">
                    <span class="text-base leading-none">🚪</span>{{ __('nav.exit') }}
                </button>
            </form>
        </nav>
    </div>

    <x-feedback-bubble :context-label="$feedbackContext ?? $title" />
</x-layout>
