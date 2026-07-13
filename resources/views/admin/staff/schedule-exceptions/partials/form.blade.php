@php $modalName = $modalName ?? null; $idPrefix = $modalName ? $modalName.'-' : ''; @endphp
<x-form-shell :action="$action" :method="$method" :modal-name="$modalName" @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])>
    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('One-off override') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Exception details') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label :for="$idPrefix.'exception_date'" :value="__('Date')" />
                <x-text-input :id="$idPrefix.'exception_date'" name="exception_date" type="date" class="mt-2" :value="old('exception_date', $scheduleException->exception_date?->format('Y-m-d'))" required />
                <x-input-error class="mt-2" :messages="$errors->get('exception_date')" />
            </div>

            <div>
                <x-input-label :for="$idPrefix.'exception_type'" :value="__('Type')" />
                <select :id="$idPrefix.'exception_type'" name="exception_type" class="casa-input mt-2" required>
                    <option value="{{ \App\Models\StaffScheduleException::TYPE_UNAVAILABLE }}" @selected(old('exception_type', $scheduleException->exception_type) === \App\Models\StaffScheduleException::TYPE_UNAVAILABLE)>
                        {{ __('Unavailable') }}
                    </option>
                    <option value="{{ \App\Models\StaffScheduleException::TYPE_AVAILABLE }}" @selected(old('exception_type', $scheduleException->exception_type) === \App\Models\StaffScheduleException::TYPE_AVAILABLE)>
                        {{ __('Available') }}
                    </option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('exception_type')" />
            </div>

            <x-schedule-window-fields
                :start-time="$scheduleException->start_time"
                :end-time="$scheduleException->end_time"
                :ends-next-day="$scheduleException->ends_next_day ?? false"
                :id-prefix="$idPrefix"
                :times-required="false"
                :midnight-description="__('Use with a 12:00 AM end time for a partial-day override that reaches closing.')"
            />

            <div>
                <x-input-label :for="$idPrefix.'reason'" :value="__('Reason')" />
                <textarea :id="$idPrefix.'reason'" name="reason" rows="5" class="casa-input mt-2">{{ old('reason', $scheduleException->reason) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('reason')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Exception rules') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Full day or timed') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Unavailable exceptions may leave times blank for a full-day block. Available exceptions always need start and end times.') }}
            </p>
        </x-app-card>

        <x-form-actions :submit-label="$submitLabel" :modal-name="$modalName" :cancel-url="route('admin.staff.show', $staffProfile)" />
    </aside>
</x-form-shell>
