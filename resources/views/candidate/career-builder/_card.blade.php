@php
    $answers = $builder['answers'];
    $progress = $builder['progress'];
@endphp

<div x-data="{ changingProfession: false }" class="rounded-2xl mb-4" style="background: var(--color-ttn-navy)">
    <div class="p-6 text-white">
        @if (!$builder['profession'])
            <div class="font-display text-[15px] font-bold mb-1.5">{{ __('career_builder.title') }}</div>
            <div class="text-[12.5px] leading-relaxed opacity-80 mb-4">{{ __('career_builder.intro') }}</div>
            <form method="POST" action="{{ route('candidate.career.profession') }}" class="flex flex-wrap gap-2">
                @csrf
                @foreach ($builder['professions'] as $profession)
                    <button name="profession" value="{{ $profession }}" class="rounded-full bg-white/10 px-4 py-2 text-xs font-bold cursor-pointer hover:bg-white/20">
                        {{ $profession }}
                    </button>
                @endforeach
            </form>
        @else
            <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                <div class="flex-1 min-w-[180px] sm:min-w-[220px]">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <div class="font-display text-[15px] font-bold">{{ __('career_builder.title') }}</div>
                        <button type="button" @click="changingProfession = !changingProfession" class="shrink-0 rounded-lg bg-white/15 hover:bg-white/25 px-3.5 py-1.5 text-[11.5px] font-bold text-white cursor-pointer whitespace-nowrap">
                            <span x-show="!changingProfession">✎ {{ __('career_builder.change_profession') }}</span>
                            <span x-show="changingProfession" x-cloak>{{ __('common.cancel') }}</span>
                        </button>
                    </div>
                    <div class="text-[12.5px] leading-relaxed opacity-80">
                        {!! __('career_builder.progress', ['count' => '<strong>' . $progress['answered'] . '/' . $progress['total'] . '</strong>', 'profession' => '<strong>' . e($builder['profession']) . '</strong>']) !!}
                    </div>
                </div>
                <div class="min-w-[140px] sm:min-w-[190px]">
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-display text-[34px] font-extrabold">{{ $progress['pct'] }}%</span>
                        <span class="text-[11.5px] font-semibold opacity-70">{{ __('career_builder.match_accuracy') }}</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-white/15 mt-2 mb-1">
                        <div class="h-full rounded-full bg-ttn-amber" style="width: {{ $progress['pct'] }}%"></div>
                    </div>
                </div>
            </div>

            <div x-show="changingProfession" x-cloak class="mt-4 pt-4 border-t border-white/15">
                <div class="text-[11.5px] leading-relaxed opacity-80 mb-3">{{ __('career_builder.change_profession_hint') }}</div>
                <form method="POST" action="{{ route('candidate.career.profession') }}" class="flex flex-wrap gap-2">
                    @csrf
                    @foreach ($builder['professions'] as $profession)
                        <button name="profession" value="{{ $profession }}"
                                class="rounded-full px-4 py-2 text-xs font-bold cursor-pointer {{ $profession === $builder['profession'] ? 'bg-white text-ttn-navy' : 'bg-white/10 hover:bg-white/20' }}">
                            {{ $profession }}
                        </button>
                    @endforeach
                </form>
            </div>
        @endif
    </div>
</div>

@if ($builder['profession'])
    <div class="flex flex-col gap-2.5 mb-4">
        @foreach ($builder['steps'] as $stepKey => $step)
            <div x-data="{ open: false }" class="rounded-2xl border border-ttn-border bg-ttn-card">
                <button type="button" @click="open = !open" class="w-full flex items-center gap-3 px-5 py-4 text-left cursor-pointer">
                    <div class="flex-1 min-w-0">
                        <div class="font-display text-[13.5px] font-bold">{{ $step['title'] }}</div>
                        <div class="text-xs text-ttn-text2 mt-0.5">{{ $step['subtitle'] }}</div>
                    </div>
                    <span class="text-xs text-ttn-text2" x-text="open ? '▲' : '▼'"></span>
                </button>

                <div x-show="open" x-cloak class="px-5 pb-5 flex flex-col gap-5">
                    <form method="POST" action="{{ route('candidate.career.answers') }}" class="flex flex-col gap-5" x-data="{ selectedCountries: @js($answers->get('countries', [])) }">
                        @csrf
                        <input type="hidden" name="step" value="{{ $stepKey }}">

                        @foreach ($step['fields'] as $field)
                            @if ($field['kind'] === 'subjects')
                                @continue
                            @endif
                            @php
                                $isArrayField = in_array($field['kind'], ['multi', 'city-multi'], true);
                                $current = $answers->get($field['key'], $isArrayField ? [] : '');
                                $current = is_array($current) ? $current : ($current === '' ? [] : [$current]);
                            @endphp
                            <div>
                                <div class="text-[12.5px] font-bold mb-1">{{ $field['label'] }}</div>
                                @if (!empty($field['hint']))
                                    <div class="text-[11.5px] text-ttn-text2 mb-2">{{ $field['hint'] }}</div>
                                @endif

                                @if ($field['kind'] === 'money')
                                    <input type="number" name="{{ $field['key'] }}" value="{{ $answers->get($field['key']) }}" step="10000" min="0"
                                           class="w-full rounded-lg border border-ttn-border px-3 py-2 text-[13px] font-semibold">
                                @elseif ($field['kind'] === 'city-multi')
                                    <div x-data="{
                                        selected: @js(array_values($current)),
                                        popular: @js($field['citySamples'] ?? []),
                                        newCity: '',
                                        hasCity(city) {
                                            return this.selected.some(c => c.toLowerCase() === city.toLowerCase());
                                        },
                                        // Only suggest cities from the countries the candidate has
                                        // actually picked as preferred — otherwise these one-click
                                        // chips let them 'select' a city outside that list entirely.
                                        visiblePopular() {
                                            return this.popular.filter(c => !this.hasCity(c.city) && (this.selectedCountries.length === 0 || this.selectedCountries.includes(c.country)));
                                        },
                                        addCity(city) {
                                            const v = (city ?? this.newCity).trim();
                                            if (v && !this.hasCity(v)) {
                                                this.selected.push(v);
                                            }
                                            this.newCity = '';
                                        },
                                        removeCity(city) {
                                            this.selected = this.selected.filter(c => c !== city);
                                        },
                                    }">
                                        <div class="flex flex-wrap gap-2 mb-2.5">
                                            <template x-if="selected.length === 0">
                                                <span class="text-[11.5px] text-ttn-text2">{{ __('career_builder.no_cities_yet') }}</span>
                                            </template>
                                            <template x-for="city in selected" :key="city">
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-ttn-primary px-3 py-1.5 text-[11.5px] font-semibold text-white">
                                                    <input type="hidden" name="{{ $field['key'] }}[]" :value="city">
                                                    <span x-text="city"></span>
                                                    <button type="button" @click="removeCity(city)" class="cursor-pointer leading-none opacity-70 hover:opacity-100">✕</button>
                                                </span>
                                            </template>
                                        </div>
                                        <template x-if="selectedCountries.length === 0">
                                            <div class="text-[11px] text-ttn-amber-text mb-2.5">{{ __('career_builder.pick_countries_first') }}</div>
                                        </template>
                                        <template x-if="visiblePopular().length">
                                            <div class="flex flex-wrap items-center gap-1.5 mb-2.5">
                                                <span class="text-[10.5px] font-semibold text-ttn-text2">{{ __('career_builder.popular') }}</span>
                                                <template x-for="city in visiblePopular()" :key="city.city">
                                                    <button type="button" @click="addCity(city.city)" class="cursor-pointer rounded-full border border-ttn-border px-2.5 py-1 text-[11px] font-semibold text-ttn-text2 hover:bg-ttn-subtle" x-text="city.city"></button>
                                                </template>
                                            </div>
                                        </template>
                                        <div class="flex gap-2">
                                            <input
                                                type="text" x-model="newCity" list="{{ $stepKey }}-{{ $field['key'] }}-cities"
                                                @keydown.enter.prevent="addCity()"
                                                placeholder="{{ __('career_builder.type_a_city') }}"
                                                class="flex-1 min-w-0 rounded-lg border border-ttn-border px-3 py-2 text-[13px]"
                                            >
                                            <datalist id="{{ $stepKey }}-{{ $field['key'] }}-cities">
                                                @foreach ($field['cityOptions'] ?? [] as $cityOption)
                                                    <option value="{{ $cityOption['city'] }}"></option>
                                                @endforeach
                                            </datalist>
                                            <button type="button" @click="addCity()" class="shrink-0 rounded-lg bg-ttn-primary px-4 py-2 text-[12.5px] font-bold text-white cursor-pointer">{{ __('common.add') }}</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($field['options'] as $option)
                                            @php
                                                $value = is_array($option) ? $option['value'] : $option;
                                                $label = is_array($option) ? $option['label'] : $option;
                                            @endphp
                                            <x-chip
                                                :type="$field['kind'] === 'multi' ? 'checkbox' : 'radio'"
                                                :name="$field['kind'] === 'multi' ? $field['key'] . '[]' : $field['key']"
                                                :value="$value"
                                                :label="$label"
                                                :checked="in_array((string) $value, array_map('strval', $current), true)"
                                                @if ($field['key'] === 'countries') x-model="selectedCountries" @endif
                                            />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <button class="self-start rounded-lg bg-ttn-primary px-4 py-2 text-xs font-bold text-white cursor-pointer">{!! __('career_builder.save_continue') !!}</button>
                    </form>

                    @foreach ($step['fields'] as $field)
                        @if ($field['kind'] === 'subjects')
                            @include('candidate.career-builder._subjects')
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
