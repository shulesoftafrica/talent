@props(['eventKey', 'contextLabel' => null])

<div x-data="quickRating(@js([
    'endpoint' => route('candidate.feedback.store'),
    'eventKey' => $eventKey,
    'contextLabel' => $contextLabel,
    'contextPath' => request()->path(),
]))">
    <template x-if="!thanked && !askingWhy">
        <div class="rounded-lg bg-ttn-subtle px-3.5 py-3">
            <div class="mb-2 text-[12px] font-bold text-ttn-text2">{{ __('feedback.quick_rating_prompt') }}</div>
            <div class="flex gap-2">
                <button type="button" @click="rate('like')" class="flex-1 cursor-pointer rounded-lg border border-ttn-border bg-ttn-card py-2 text-base">👍</button>
                <button type="button" @click="rate('neutral')" class="flex-1 cursor-pointer rounded-lg border border-ttn-border bg-ttn-card py-2 text-base">😐</button>
                <button type="button" @click="rate('dislike')" class="flex-1 cursor-pointer rounded-lg border border-ttn-border bg-ttn-card py-2 text-base">👎</button>
            </div>
        </div>
    </template>

    <template x-if="askingWhy">
        <div class="rounded-lg bg-ttn-subtle px-3.5 py-3">
            <div class="mb-2 text-[12px] font-bold text-ttn-text2">{{ __('feedback.quick_rating_what_wrong') }}</div>
            <textarea x-model="why" rows="2" maxlength="500" placeholder="{{ __('feedback.quick_rating_placeholder') }}" class="mb-2 w-full rounded-lg border border-ttn-border bg-ttn-card px-2.5 py-2 text-[12px]"></textarea>
            <div class="flex gap-2">
                <button type="button" @click="skipWhy()" class="flex-1 cursor-pointer rounded-lg border border-ttn-border py-2 text-[11.5px] font-bold text-ttn-text2">{{ __('feedback.quick_rating_skip') }}</button>
                <button type="button" @click="sendWhy()" class="flex-1 cursor-pointer rounded-lg bg-ttn-primary py-2 text-[11.5px] font-bold text-white">{{ __('feedback.quick_rating_send') }}</button>
            </div>
        </div>
    </template>

    <template x-if="thanked">
        <div class="rounded-lg bg-ttn-subtle px-3.5 py-2.5 text-center text-[12px] font-semibold text-ttn-text2">{{ __('feedback.quick_rating_thanks') }}</div>
    </template>
</div>
