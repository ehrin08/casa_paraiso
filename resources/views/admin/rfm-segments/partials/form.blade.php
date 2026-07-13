<x-form-shell :action="$action" :method="$method" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Segment definition') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Customer thresholds') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="name" :value="__('Segment name')" />
                <x-text-input id="name" name="name" type="text" class="mt-2" :value="old('name', $rfmSegment->name)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>
            <div>
                <x-input-label for="description" :value="__('Description')" />
                <textarea id="description" name="description" rows="3" class="casa-input mt-2">{{ old('description', $rfmSegment->description) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('description')" />
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                @foreach ([
                    ['code' => 'R', 'title' => __('Recency'), 'description' => __('Days since the latest paid completed visit.'), 'min' => 'recency_min_days', 'max' => 'recency_max_days', 'step' => '1'],
                    ['code' => 'F', 'title' => __('Frequency'), 'description' => __('Number of paid completed visits.'), 'min' => 'frequency_min', 'max' => 'frequency_max', 'step' => '1'],
                    ['code' => 'M', 'title' => __('Monetary'), 'description' => __('Total paid completed spending in PHP.'), 'min' => 'monetary_min', 'max' => 'monetary_max', 'step' => '0.01'],
                ] as $threshold)
                    <fieldset class="rounded-2xl border border-casa-border bg-casa-bg p-4">
                        <legend class="sr-only">{{ $threshold['title'] }}</legend>
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-casa-palm text-sm font-black text-white">{{ $threshold['code'] }}</span>
                            <div>
                                <p class="font-bold text-casa-ink">{{ $threshold['title'] }}</p>
                                <p class="mt-1 text-sm leading-5 text-casa-muted">{{ $threshold['description'] }}</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label :for="$threshold['min']" :value="__('Minimum')" />
                                <x-text-input :id="$threshold['min']" :name="$threshold['min']" type="number" min="0" :step="$threshold['step']" class="mt-2" :value="old($threshold['min'], $rfmSegment->{$threshold['min']})" />
                                <x-input-error class="mt-2" :messages="$errors->get($threshold['min'])" />
                            </div>
                            <div>
                                <x-input-label :for="$threshold['max']" :value="__('Maximum')" />
                                <x-text-input :id="$threshold['max']" :name="$threshold['max']" type="number" min="0" :step="$threshold['step']" class="mt-2" :value="old($threshold['max'], $rfmSegment->{$threshold['max']})" />
                                <x-input-error class="mt-2" :messages="$errors->get($threshold['max'])" />
                            </div>
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rfmSegment->is_active)) class="mt-1 rounded border-casa-border text-casa-palm shadow-sm focus:ring-casa-brass">
                <span>
                    <span class="block text-sm font-bold text-casa-ink">{{ __('Active for promotion generation') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Inactive segments remain visible for audit history but cannot receive new suggestions.') }}</span>
                </span>
            </label>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('Zero-visit customers') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Use F = 0 explicitly') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('A segment includes customers without a paid completed visit only when its minimum frequency is 0 and its maximum allows 0. Recency is then treated as unavailable.') }}</p>
        </x-app-card>
        <x-form-actions :submit-label="$submitLabel" :cancel-url="route('admin.rfm-segments.index')" />
    </aside>
</x-form-shell>
