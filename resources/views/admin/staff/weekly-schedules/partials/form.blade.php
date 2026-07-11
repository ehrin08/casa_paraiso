@php $modalName = $modalName ?? null; @endphp
<form method="POST" action="{{ $action }}" @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($modalName)<input type="hidden" name="_modal" value="{{ $modalName }}">@endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Recurring availability') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Shift details') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="day_of_week" :value="__('Day of week')" />
                <select id="day_of_week" name="day_of_week" class="casa-input mt-2" required>
                    @foreach (\App\Models\StaffWeeklySchedule::DAYS as $dayValue => $dayLabel)
                        <option value="{{ $dayValue }}" @selected((int) old('day_of_week', $weeklySchedule->day_of_week) === $dayValue)>
                            {{ $dayLabel }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('day_of_week')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_time" :value="__('Start time')" />
                    <x-text-input id="start_time" name="start_time" type="time" class="mt-2" :value="old('start_time', substr((string) $weeklySchedule->start_time, 0, 5))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                </div>

                <div>
                    <x-input-label for="end_time" :value="__('End time')" />
                    <x-text-input id="end_time" name="end_time" type="time" class="mt-2" :value="old('end_time', substr((string) $weeklySchedule->end_time, 0, 5))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                <input type="hidden" name="is_available" value="0">
                <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $weeklySchedule->is_available ?? true)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                <span>
                    <span class="block text-sm font-bold text-casa-text">{{ __('Available for bookings') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Leave enabled for normal working hours. Disabled rows remain visible for operational planning.') }}</span>
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('is_available')" />
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Overlap rule') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Split shifts are allowed') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Create multiple rows for the same day when shifts do not overlap, such as morning and afternoon windows.') }}
            </p>
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                @if ($modalName)<button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>@else<a href="{{ route('admin.staff.show', $staffProfile) }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>@endif
            </div>
        </x-app-card>
    </aside>
</form>
