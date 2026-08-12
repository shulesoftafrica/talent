<x-layout :title="$unavailable ? 'Opportunity Unavailable — ShuleSoft Talent Network' : ($job['title'] . ' at ' . $schoolName . ' — ShuleSoft Talent Network')">
    <div class="min-h-screen">
        <header class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4 md:px-12 border-b border-ttn-border">
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5">
                <x-brand-logo size="h-8 w-8" />
                <span class="font-display text-[13.5px] sm:text-[15px] font-extrabold">ShuleSoft Talent Network</span>
            </a>
            <div class="flex items-center gap-1.5">
                <x-theme-toggle />
                <x-language-switcher />
            </div>
        </header>

        @if($unavailable)
            <div class="mx-auto max-w-[560px] px-4 sm:px-6 md:px-12 pt-16 pb-16 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-ttn-subtle text-2xl">🔍</div>
                <h1 class="font-display text-xl sm:text-2xl font-extrabold mb-2">Opportunity Unavailable</h1>
                <p class="text-[13.5px] text-ttn-text2 leading-relaxed mb-6">
                    This vacancy link is no longer valid — it may have been removed, or the link was mistyped. Explore other open roles on the ShuleSoft Talent Network instead.
                </p>
                <a href="{{ route('landing') }}" class="inline-block rounded-[10px] bg-ttn-primary px-6 py-3.5 text-[14.5px] font-bold text-white">
                    Explore the Talent Network
                </a>
            </div>
        @else
        <div class="mx-auto max-w-[760px] px-4 sm:px-6 md:px-12 pt-8 pb-16">
            <div class="rounded-2xl border border-ttn-border bg-ttn-card p-6 sm:p-8 shadow-lg">
                <div class="inline-flex items-center gap-1.5 rounded-full bg-ttn-primary-light px-3 py-1.5 text-xs font-bold text-ttn-primary-dark mb-3">
                    We Are Hiring
                </div>

                <div class="flex items-center justify-between gap-3 mb-1.5">
                    <h1 class="font-display text-2xl sm:text-3xl font-extrabold leading-tight tracking-tight">
                        {{ $job['title'] }}
                    </h1>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $applicationsClosed ? 'bg-ttn-subtle text-ttn-text2' : 'bg-ttn-primary-light text-ttn-primary-dark' }}">
                        {{ $applicationsClosed ? 'Closed' : 'Active' }}
                    </span>
                </div>
                <div class="flex items-center gap-2.5 mb-3">
                    @if($schoolLogoUrl)
                        <img
                            src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }}"
                            class="h-9 w-9 shrink-0 rounded-full border border-ttn-border object-cover bg-white"
                            onerror="this.remove()"
                        >
                    @endif
                    <div class="text-[15px] font-bold text-ttn-primary-dark">{{ $schoolName }}</div>
                </div>

                <div class="flex flex-wrap gap-2 mb-5">
                    @if(!empty($job['location']))
                        <span class="rounded-full bg-ttn-subtle px-3 py-1.5 text-xs font-bold">📍 {{ $job['location'] }}</span>
                    @endif
                    @if(!empty($job['employment_type']))
                        <span class="rounded-full bg-ttn-subtle px-3 py-1.5 text-xs font-bold">{{ ucwords(str_replace('_', ' ', $job['employment_type'])) }}</span>
                    @endif
                    <span class="rounded-full bg-ttn-subtle px-3 py-1.5 text-xs font-bold">{{ $salaryLabel }}</span>
                    @if(!empty($job['positions_available']))
                        <span class="rounded-full bg-ttn-subtle px-3 py-1.5 text-xs font-bold">{{ (int) $job['positions_available'] }} {{ (int) $job['positions_available'] === 1 ? 'opening' : 'openings' }}</span>
                    @endif
                    @if($deadline)
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $deadlinePassed ? 'bg-ttn-red/10 text-ttn-red' : 'bg-ttn-subtle' }}">
                            {{ $deadlinePassed ? 'Deadline passed' : 'Apply before ' . $deadline->format('d M Y') }}
                        </span>
                    @endif
                </div>

                @if($description !== '')
                    <div class="mb-5">
                        <h2 class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-1.5">About this role</h2>
                        <p class="text-[13.5px] leading-relaxed whitespace-pre-line">{{ $description }}</p>
                    </div>
                @endif

                @php
                    $sectionLabels = [
                        'responsibilities' => 'Responsibilities',
                        'requirements' => 'Requirements',
                        'qualifications' => 'Preferred Qualifications',
                        'benefits' => 'Benefits',
                    ];
                @endphp

                @foreach($sectionLabels as $key => $label)
                    @if(!empty($sections[$key]))
                        <div class="mb-5">
                            <h2 class="text-xs font-bold uppercase tracking-wide text-ttn-text2 mb-1.5">{{ $label }}</h2>
                            <p class="text-[13.5px] leading-relaxed whitespace-pre-line">{{ $sections[$key] }}</p>
                        </div>
                    @endif
                @endforeach

                <div class="rounded-xl bg-ttn-subtle p-4 mb-5">
                    <p class="text-xs font-medium text-ttn-text2">
                        Anyone with this link can view this vacancy. You'll need to create or verify a Talent Network account before applying.
                    </p>
                </div>

                @if($applicationsClosed)
                    <div class="w-full rounded-[10px] bg-ttn-subtle py-3.5 text-center text-[14.5px] font-bold text-ttn-text2">
                        Applications are closed for this position
                    </div>
                @elseif($alreadyApplied)
                    <a href="{{ route('candidate.applications.index') }}" class="block w-full rounded-[10px] bg-ttn-primary py-3.5 text-center text-[14.5px] font-bold text-white">
                        You already applied for this position — View Application
                    </a>
                @elseif(Auth::guard('candidate')->check())
                    <form method="POST" action="{{ route('candidate.applications.apply') }}">
                        @csrf
                        <input type="hidden" name="source_schema" value="{{ $job['source_schema'] }}">
                        <input type="hidden" name="job_posting_id" value="{{ $job['id'] }}">
                        <input type="hidden" name="ref" value="{{ request('ref') }}">
                        <button type="submit" class="w-full rounded-[10px] bg-ttn-primary py-3.5 text-[14.5px] font-bold text-white cursor-pointer">
                            Apply Now
                        </button>
                    </form>
                @else
                    <a
                        href="{{ route('landing', ['apply_uuid' => $uuid, 'apply_ref' => request('ref')]) }}"
                        class="block w-full rounded-[10px] bg-ttn-primary py-3.5 text-center text-[14.5px] font-bold text-white"
                    >
                        Log in or Create Account to Apply
                    </a>
                @endif

                <div x-data="{ open: false, copied: false }" class="relative mt-2.5">
                    <button
                        @click="open = !open" type="button"
                        class="w-full rounded-[10px] border border-ttn-border py-3 text-[13.5px] font-bold text-ttn-text cursor-pointer"
                    >
                        📤 Share Job
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute left-0 right-0 z-10 mt-2 rounded-xl border border-ttn-border bg-ttn-card p-2 shadow-lg">
                        <a
                            href="https://wa.me/?text={{ urlencode($job['title'] . ' at ' . $schoolName . ' — apply on ShuleSoft Talent Network: ' . url()->current() . '?ref=whatsapp') }}"
                            target="_blank" rel="noopener"
                            class="block rounded-lg px-3.5 py-2.5 text-[13px] font-semibold hover:bg-ttn-subtle"
                        >
                            💬 Share via WhatsApp
                        </a>
                        <button
                            type="button" @click="navigator.clipboard.writeText(window.location.origin + window.location.pathname + '?ref=copied_link'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="block w-full text-left rounded-lg px-3.5 py-2.5 text-[13px] font-semibold hover:bg-ttn-subtle cursor-pointer"
                        >
                            <span x-show="!copied">🔗 Copy Link</span>
                            <span x-show="copied" x-cloak>✓ Link copied</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="text-center text-[11.5px] font-medium text-ttn-text2 mt-4">
                Powered by ShuleSoft Talent Network
            </div>
        </div>
        @endif
    </div>
</x-layout>
