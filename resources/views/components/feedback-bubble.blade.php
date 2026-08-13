@props(['contextLabel' => null])

@php
    $subcategoryKeys = [
        'help' => ['profile_completion', 'verification', 'cannot_apply', 'job_matches', 'other'],
        'problem' => ['job_application_problem', 'profile_problem', 'verification_problem', 'incorrect_information', 'notification_problem', 'website_error', 'other'],
        'feedback' => ['job_matching', 'profile', 'applications', 'verification', 'notifications', 'user_experience', 'other'],
    ];

    $subcategories = collect($subcategoryKeys)->mapWithKeys(fn ($keys, $category) => [
        $category => collect($keys)->map(fn ($key) => ['key' => $key, 'label' => __("feedback.{$category}_{$key}")])->values()->all(),
    ])->all();

    $bubbleConfig = [
        'contextLabel' => $contextLabel,
        'contextPath' => request()->path(),
        'endpoint' => route('candidate.feedback.store'),
        'historyEndpoint' => route('candidate.feedback.index'),
        'subcategories' => $subcategories,
        'strings' => [
            'help' => ['messageLabel' => __('feedback.help_message_label'), 'messagePlaceholder' => __('feedback.help_message_placeholder')],
            'problem' => ['messageLabel' => __('feedback.problem_message_label'), 'messagePlaceholder' => __('feedback.problem_message_placeholder')],
            'feedback' => ['messageLabel' => __('feedback.feedback_message_label'), 'messagePlaceholder' => __('feedback.feedback_message_placeholder')],
            'idea' => ['messageLabel' => __('feedback.idea_prompt'), 'messagePlaceholder' => __('feedback.idea_message_placeholder')],
        ],
        'errors' => [
            'messageRequired' => __('feedback.validation_message_required'),
            'generic' => __('feedback.error_generic'),
        ],
    ];
@endphp

<div x-data="feedbackBubble(@js($bubbleConfig))">
    <button @click="openChooser()" type="button"
            class="fixed bottom-20 right-4 z-[80] rounded-full bg-ttn-primary px-4 py-2.5 text-[12px] font-bold text-white shadow-lg cursor-pointer lg:bottom-6 lg:right-6">
        {{ __('feedback.bubble_label') }}
    </button>

    <div x-show="open" x-cloak @click.self="close()" class="fixed inset-0 z-[90] flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-5">
        <div @click.stop class="max-h-[88vh] w-full max-w-full overflow-y-auto rounded-t-2xl bg-ttn-card p-5 text-ttn-text sm:w-[420px] sm:rounded-2xl sm:p-6">

            {{-- Step: choose --}}
            <div x-show="step === 'choose'">
                <div class="mb-4 flex items-center justify-between">
                    <div class="font-display text-base font-bold">{{ __('feedback.modal_title') }}</div>
                    <button @click="close()" type="button" class="cursor-pointer text-ttn-text2">✕</button>
                </div>

                <div class="mb-5 grid grid-cols-2 gap-2.5">
                    <button @click="chooseCategory('help')" type="button" class="cursor-pointer rounded-xl border border-ttn-border p-3.5 text-left hover:border-ttn-primary">
                        <div class="mb-1 text-xl">🆘</div>
                        <div class="mb-0.5 text-[12.5px] font-bold">{{ __('feedback.option_help') }}</div>
                        <div class="text-[10.5px] text-ttn-text2">{{ __('feedback.option_help_desc') }}</div>
                    </button>
                    <button @click="chooseCategory('feedback')" type="button" class="cursor-pointer rounded-xl border border-ttn-border p-3.5 text-left hover:border-ttn-primary">
                        <div class="mb-1 text-xl">💡</div>
                        <div class="mb-0.5 text-[12.5px] font-bold">{{ __('feedback.option_feedback') }}</div>
                        <div class="text-[10.5px] text-ttn-text2">{{ __('feedback.option_feedback_desc') }}</div>
                    </button>
                    <button @click="chooseCategory('problem')" type="button" class="cursor-pointer rounded-xl border border-ttn-border p-3.5 text-left hover:border-ttn-primary">
                        <div class="mb-1 text-xl">🚨</div>
                        <div class="mb-0.5 text-[12.5px] font-bold">{{ __('feedback.option_problem') }}</div>
                        <div class="text-[10.5px] text-ttn-text2">{{ __('feedback.option_problem_desc') }}</div>
                    </button>
                    <button @click="chooseCategory('idea')" type="button" class="cursor-pointer rounded-xl border border-ttn-border p-3.5 text-left hover:border-ttn-primary">
                        <div class="mb-1 text-xl">💭</div>
                        <div class="mb-0.5 text-[12.5px] font-bold">{{ __('feedback.option_idea') }}</div>
                        <div class="text-[10.5px] text-ttn-text2">{{ __('feedback.option_idea_desc') }}</div>
                    </button>
                </div>

                <div>
                    <div class="mb-2 text-[11.5px] font-bold text-ttn-text2">{{ __('feedback.previous_requests_title') }}</div>
                    <div class="flex flex-col gap-1.5">
                        <template x-if="historyLoading">
                            <div class="text-[11.5px] text-ttn-text2">{{ __('common.loading') }}</div>
                        </template>
                        <template x-if="!historyLoading && history.length === 0">
                            <div class="text-[11.5px] text-ttn-text2">{{ __('feedback.previous_requests_empty') }}</div>
                        </template>
                        <template x-for="h in history" :key="h.id">
                            <div class="flex items-center justify-between rounded-lg bg-ttn-subtle px-3 py-2">
                                <span class="truncate text-[11.5px] font-semibold" x-text="h.label"></span>
                                <span class="ml-2 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusClass(h.status)" x-text="h.status_label"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Step: form --}}
            <div x-show="step === 'form'">
                <button @click="step = 'choose'" type="button" class="mb-3 cursor-pointer text-[11.5px] font-bold text-ttn-text2">← {{ __('feedback.back') }}</button>

                <template x-if="{{ $contextLabel ? 'true' : 'false' }}">
                    <div class="mb-3 rounded-lg bg-ttn-subtle px-3 py-2 text-[11px] text-ttn-text2">
                        {{ __('feedback.context_prefix') }}: {{ $contextLabel }}
                    </div>
                </template>

                {{-- Subcategory list: help / problem --}}
                <template x-if="category === 'help' || category === 'problem'">
                    <div class="mb-4">
                        <div class="mb-2 text-[12.5px] font-bold" x-text="category === 'help' ? '{{ __('feedback.help_subcategory_prompt') }}' : '{{ __('feedback.problem_prompt') }}'"></div>
                        <div class="flex flex-col gap-1.5">
                            <template x-for="opt in subcategoryOptions" :key="opt.key">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-[12.5px] font-semibold"
                                       :class="subcategory === opt.key ? 'border-ttn-primary bg-ttn-primary-light' : 'border-ttn-border'">
                                    <input type="radio" name="subcategory" :value="opt.key" x-model="subcategory" class="h-4 w-4 shrink-0">
                                    <span x-text="opt.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Sentiment + optional topic chips: feedback --}}
                <template x-if="category === 'feedback'">
                    <div class="mb-4">
                        <div class="mb-2 text-[12.5px] font-bold">{{ __('feedback.feedback_sentiment_prompt') }}</div>
                        <div class="mb-4 flex gap-2">
                            <button type="button" @click="sentiment = 'like'" class="flex-1 cursor-pointer rounded-lg border px-2 py-2.5 text-[12px] font-bold" :class="sentiment === 'like' ? 'border-ttn-primary bg-ttn-primary-light' : 'border-ttn-border'">{{ __('feedback.sentiment_like') }}</button>
                            <button type="button" @click="sentiment = 'neutral'" class="flex-1 cursor-pointer rounded-lg border px-2 py-2.5 text-[12px] font-bold" :class="sentiment === 'neutral' ? 'border-ttn-primary bg-ttn-primary-light' : 'border-ttn-border'">{{ __('feedback.sentiment_neutral') }}</button>
                            <button type="button" @click="sentiment = 'dislike'" class="flex-1 cursor-pointer rounded-lg border px-2 py-2.5 text-[12px] font-bold" :class="sentiment === 'dislike' ? 'border-ttn-primary bg-ttn-primary-light' : 'border-ttn-border'">{{ __('feedback.sentiment_dislike') }}</button>
                        </div>

                        <div class="mb-2 text-[12.5px] font-bold">{{ __('feedback.feedback_category_prompt') }}</div>
                        <div class="mb-4 flex flex-wrap gap-1.5">
                            <template x-for="opt in subcategoryOptions" :key="opt.key">
                                <button type="button" @click="subcategory = (subcategory === opt.key ? null : opt.key)"
                                        class="cursor-pointer rounded-full px-2.5 py-1 text-[10.5px] font-semibold"
                                        :class="subcategory === opt.key ? 'bg-ttn-primary text-white' : 'bg-ttn-subtle text-ttn-text2'"
                                        x-text="opt.label"></button>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="mb-2">
                    <div class="mb-1.5 text-[12.5px] font-bold" x-text="messageLabel"></div>
                    <textarea x-model="message" rows="4" :placeholder="messagePlaceholder" maxlength="2000" class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px]"></textarea>
                </div>

                <template x-if="error">
                    <div class="mb-3 text-[11.5px] font-semibold text-ttn-red" x-text="error"></div>
                </template>

                <button @click="submit()" :disabled="submitting" type="button" class="w-full cursor-pointer rounded-lg bg-ttn-primary px-4 py-3 text-[13px] font-bold text-white disabled:opacity-60">
                    <span x-show="!submitting">{{ __('feedback.submit') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('feedback.submitting') }}</span>
                </button>
            </div>

            {{-- Step: success --}}
            <div x-show="step === 'success'">
                <div class="mx-auto mb-4 flex h-13 w-13 items-center justify-center rounded-full bg-ttn-primary-light text-2xl text-ttn-primary-dark">✓</div>
                <div class="mb-1 text-center font-display text-base font-bold">{{ __('feedback.success_title') }}</div>
                <div class="mb-5 text-center text-[12.5px] text-ttn-text2">{{ __('feedback.success_body') }}</div>
                <button @click="close()" type="button" class="w-full cursor-pointer rounded-lg bg-ttn-primary px-4 py-3 text-[13px] font-bold text-white">{{ __('feedback.success_done') }}</button>
            </div>
        </div>
    </div>
</div>
