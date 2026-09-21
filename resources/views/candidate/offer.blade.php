<x-candidate-shell :candidate="$candidate" active="applications" title="Job offer" subtitle="Review your formal offer and reply">
    <x-slot:rail>
        @include('candidate._rail')
    </x-slot:rail>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-ttn-primary-light px-4 py-3 text-[13px] font-semibold text-ttn-primary-dark">{{ session('status') }}</div>
    @endif

    @php
        $money = fn ($v) => number_format((float) $v);
        $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('j F Y') : null;
        $rows = array_filter([
            'Position' => $job->title ?? null,
            'Salary' => $hr->offer_salary !== null ? $money($hr->offer_salary) : null,
            'Start date' => $date($hr->offer_start_date),
            'Probation' => $hr->offer_probation_months !== null ? $hr->offer_probation_months.' months' : null,
            'Reporting to' => $hr->offer_reporting_to,
            'Work location' => $hr->offer_work_location,
            'Additional terms' => $hr->offer_terms,
            'Please reply by' => $state === 'open' ? $date($hr->offer_token_expires_at) : null,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp

    <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
        <div class="text-[11px] font-bold uppercase tracking-wide text-ttn-text2 mb-1">Formal job offer</div>
        <div class="font-display text-[18px] font-extrabold mb-1">{{ $job->title ?? 'Your offer' }}</div>
        <div class="text-[13px] text-ttn-text2 mb-4">Dear {{ $candidate->full_name }}, the school has made you the following offer.</div>

        <dl class="divide-y divide-ttn-hairline text-[13px]">
            @foreach ($rows as $label => $value)
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-ttn-text2">{{ $label }}</dt><dd class="font-bold text-right">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-4 text-[12px] text-ttn-text2">The full offer letter was sent to you by email. Accepting does not activate your staff account yet: after you accept, you upload your onboarding documents and the school activates your account once they are approved.</p>
    </div>

    @if ($state === 'open')
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 mb-4">
            <form method="POST" action="{{ route('candidate.applications.offer.accept', $application) }}">
                @csrf
                <label class="block text-[13px] font-bold mb-1.5" for="signed_name">Type your full name to accept and sign</label>
                <input id="signed_name" name="signed_name" type="text" required minlength="3" maxlength="150" value="{{ old('signed_name') }}"
                       class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2.5 text-[13.5px] mb-2" autocomplete="name">
                @error('signed_name')<div class="text-[12px] font-semibold text-ttn-red mb-2">{{ $message }}</div>@enderror
                <button type="submit" class="rounded-lg bg-ttn-primary px-5 py-2.5 text-[13.5px] font-bold text-white cursor-pointer">Accept offer</button>
            </form>
        </div>

        <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6">
            <button type="button" @click="open = !open" class="text-[13px] font-bold text-ttn-text2 cursor-pointer">Decline this offer</button>
            <form x-show="open" x-cloak method="POST" action="{{ route('candidate.applications.offer.decline', $application) }}" class="mt-3"
                  onsubmit="return confirm('Are you sure you want to decline this offer?');">
                @csrf
                <textarea name="reason" rows="2" maxlength="500" placeholder="Reason (optional)" class="w-full rounded-lg border border-ttn-border bg-ttn-card px-3 py-2.5 text-[13.5px] mb-2"></textarea>
                @error('reason')<div class="text-[12px] font-semibold text-ttn-red mb-2">{{ $message }}</div>@enderror
                <button type="submit" class="rounded-lg border border-ttn-border px-4 py-2 text-[13px] font-bold cursor-pointer">Decline offer</button>
            </form>
        </div>
    @else
        <div class="rounded-2xl border border-ttn-border bg-ttn-card p-5 sm:p-6 text-[13.5px]">
            @if ($hr->offer_status === 'accepted')
                <div class="font-bold text-ttn-primary-dark mb-2">You accepted this offer{{ $hr->offer_responded_at ? ' on '.\Illuminate\Support\Carbon::parse($hr->offer_responded_at)->format('j F Y') : '' }}.</div>
                <a href="{{ route('candidate.applications.onboarding', $application) }}" class="inline-block rounded-lg bg-ttn-primary px-5 py-2.5 text-[13.5px] font-bold text-white">Continue to onboarding</a>
            @elseif ($hr->offer_status === 'declined')
                <div class="font-bold">You declined this offer.</div>
            @elseif ($hr->offer_status === 'withdrawn')
                <div class="font-bold">The school has withdrawn this offer. Please contact their HR department for details.</div>
            @else
                <div class="font-bold">This offer has expired. Please contact the school if you are still interested.</div>
            @endif
        </div>
    @endif
</x-candidate-shell>
