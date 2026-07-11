@php $modalName = $modalName ?? null; @endphp
<form method="POST" action="{{ $action }}" @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($modalName)<input type="hidden" name="_modal" value="{{ $modalName }}">@endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Appointment details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Booking information') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="customer_profile_id" :value="__('Customer')" />
                    <select id="customer_profile_id" name="customer_profile_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_profile_id', $appointment->customer_profile_id) === $customer->id)>
                                {{ $customer->user->name }} ({{ $customer->customer_code }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('customer_profile_id')" />
                </div>

                <div>
                    <x-input-label for="service_id" :value="__('Service')" />
                    <select id="service_id" name="service_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select service') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) old('service_id', $appointment->service_id) === $service->id)>
                                {{ $service->name }} · {{ $service->duration_minutes }} min
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="requested_start_at" :value="__('Requested time')" />
                    <x-text-input id="requested_start_at" name="requested_start_at" type="datetime-local" class="mt-2" :value="old('requested_start_at', optional($appointment->requested_start_at)->format('Y-m-d\\TH:i'))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('requested_start_at')" />
                </div>

                <div>
                    <x-input-label for="scheduled_start_at" :value="__('Scheduled time')" />
                    <x-text-input id="scheduled_start_at" name="scheduled_start_at" type="datetime-local" class="mt-2" :value="old('scheduled_start_at', optional($appointment->scheduled_start_at)->format('Y-m-d\\TH:i'))" />
                    <x-input-error class="mt-2" :messages="$errors->get('scheduled_start_at')" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="staff_profile_id" :value="__('Assigned staff')" />
                    <select id="staff_profile_id" name="staff_profile_id" class="casa-input mt-2">
                        <option value="">{{ __('Assign during confirmation') }}</option>
                        @foreach ($staffProfiles as $staffProfile)
                            <option value="{{ $staffProfile->id }}" @selected((int) old('staff_profile_id', $appointment->staff_profile_id) === $staffProfile->id)>
                                {{ $staffProfile->user->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('staff_profile_id')" />
                </div>

                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="casa-input mt-2">
                        @foreach (\App\Models\Appointment::STATUSES as $option)
                            <option value="{{ $option }}" @selected(old('status', $appointment->status) === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('status')" />
                </div>
            </div>

            <div>
                <x-input-label for="customer_notes" :value="__('Customer notes')" />
                <textarea id="customer_notes" name="customer_notes" rows="4" class="casa-input mt-2">{{ old('customer_notes', $appointment->customer_notes) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('customer_notes')" />
            </div>

            <div>
                <x-input-label for="internal_notes" :value="__('Internal notes')" />
                <textarea id="internal_notes" name="internal_notes" rows="4" class="casa-input mt-2">{{ old('internal_notes', $appointment->internal_notes) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('internal_notes')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Confirmation rule') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Confirmed appointments require an eligible staff member and a non-overlapping scheduled time. End time is calculated from service duration.') }}
            </p>
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                @if ($modalName)<button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>@else<a href="{{ route('admin.appointments.index') }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>@endif
            </div>
        </x-app-card>
    </aside>
</form>
