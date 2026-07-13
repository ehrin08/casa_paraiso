@php $modalName = $modalName ?? null; $idPrefix = $modalName ? $modalName.'-' : ''; @endphp
<x-form-shell :action="$action" :method="$method" :modal-name="$modalName" @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])>
    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Recurring availability') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Shift details') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label :for="$idPrefix.'day_of_week'" :value="__('Day of week')" />
                <select :id="$idPrefix.'day_of_week'" name="day_of_week" class="casa-input mt-2" required>
                    @foreach (\App\Models\StaffWeeklySchedule::DAYS as $dayValue => $dayLabel)
                        <option value="{{ $dayValue }}" @selected((int) old('day_of_week', $weeklySchedule->day_of_week) === $dayValue)>
                            {{ $dayLabel }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('day_of_week')" />
            </div>

            <x-schedule-window-fields
                :start-time="$weeklySchedule->start_time"
                :end-time="$weeklySchedule->end_time"
                :ends-next-day="$weeklySchedule->ends_next_day ?? false"
                :id-prefix="$idPrefix"
                :midnight-description="__('Set the end time to 12:00 AM and enable this for a shift that runs through the end of the business day.')"
            />

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                <input type="hidden" name="is_available" value="0">
                <input :id="$idPrefix.'is_available'" type="checkbox" name="is_available" value="1" @checked(old('is_available', $weeklySchedule->is_available ?? true)) class="mt-1 rounded border-casa-control-border text-casa-palm shadow-sm focus:ring-casa-palm-dark">
                <span>
                    <span class="block text-sm font-bold text-casa-ink">{{ __('Available for bookings') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Leave enabled for normal working hours. Disabled rows remain visible for operational planning.') }}</span>
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('is_available')" />
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Overlap rule') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Split shifts are allowed') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Create multiple rows for the same day when shifts do not overlap. Bookable shifts stay within :window.', ['window' => config('casa.business_hours.window')]) }}
            </p>
        </x-app-card>

        <x-form-actions :submit-label="$submitLabel" :modal-name="$modalName" :cancel-url="route('admin.staff.show', $staffProfile)" />
    </aside>
</x-form-shell>
