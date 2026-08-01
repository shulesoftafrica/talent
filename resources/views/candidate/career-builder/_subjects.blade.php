@php
    $addedSubjectIds = $builder['teachingSubjects']->pluck('subject_id')->all();
@endphp

<div class="border-t border-ttn-hairline pt-4">
    <div class="text-[12.5px] font-bold mb-1">{!! __('subjects.title') !!}</div>
    <div class="text-[11.5px] text-ttn-text2 mb-3">{{ __('subjects.desc') }}</div>

    @if ($builder['teachingSubjects']->isNotEmpty())
        <div class="flex flex-col gap-2.5 mb-4">
            @foreach ($builder['teachingSubjects'] as $ts)
                <div class="rounded-lg bg-ttn-subtle p-3.5">
                    <div class="flex items-center justify-between gap-2.5 flex-wrap">
                        <div class="text-[13px] font-bold">{{ $ts->subject->subject_name }}</div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-ttn-text2">{{ trans_choice('subjects.years_experience', $ts->years_experience, ['n' => $ts->years_experience]) }}</span>
                            <form method="POST" action="{{ route('candidate.career.subjects.remove', $ts) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-bold text-ttn-red cursor-pointer">{{ __('subjects.remove') }}</button>
                            </form>
                        </div>
                    </div>
                    @if ($ts->classes->isNotEmpty())
                        <div class="text-[11.5px] text-ttn-text2 mt-1.5">
                            {{ __('subjects.classes_label', ['list' => $ts->classes->pluck('referClass.name')->filter()->implode(', ')]) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if (empty($answers->get('teachLevel')))
        <div class="text-xs text-ttn-text2">{{ __('subjects.select_level_first') }}</div>
    @else
        <form method="POST" action="{{ route('candidate.career.subjects.save') }}" class="rounded-lg bg-ttn-subtle p-4 flex flex-col gap-3">
            @csrf
            <select name="subject_id" required class="rounded-lg border border-ttn-border px-3 py-2 text-[13px]">
                <option value="">{{ __('subjects.choose_subject') }}</option>
                @foreach ($builder['subjectOptions'] as $subject)
                    <option value="{{ $subject->subject_id }}" @selected(in_array($subject->subject_id, $addedSubjectIds))>
                        {{ $subject->subject_name }}
                    </option>
                @endforeach
            </select>

            <div class="flex flex-wrap gap-2">
                @forelse ($builder['classOptions'] as $class)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="class_ids[]" value="{{ $class->id }}" class="peer hidden">
                        <span class="inline-block rounded-full border border-ttn-border bg-ttn-card px-3 py-1.5 text-[11.5px] font-semibold text-ttn-text2 peer-checked:bg-ttn-primary peer-checked:text-white peer-checked:border-ttn-primary">
                            {{ $class->name }}
                        </span>
                    </label>
                @empty
                    <div class="text-xs text-ttn-text2">{{ __('subjects.no_classes') }}</div>
                @endforelse
            </div>

            <div class="flex items-center gap-2.5">
                <label class="text-xs font-semibold text-ttn-text2">{{ __('subjects.years_experience_label') }}</label>
                <input type="number" name="years_experience" value="1" min="1" max="40" class="w-20 rounded-lg border border-ttn-border px-2 py-1.5 text-[13px]">
            </div>

            <button class="self-start rounded-lg bg-ttn-primary px-4 py-2 text-xs font-bold text-white cursor-pointer">{{ __('subjects.save_subject') }}</button>
        </form>
    @endif
</div>
