<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->service?->name }} · {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary">{{ __('My appointments') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-input-error :messages="$errors->all()" />

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Booking status') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Visit details') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Requested') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->requested_start_at?->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Scheduled') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') ?: __('Waiting for confirmation') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Assigned therapist') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->staffProfile?->user?->name ?: __('Pending') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                        <dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Therapist preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd>
                        <p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('Preferences are considered during review but are not a final assignment.') }}</p>
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Request notes') }}</h2>
                </div>
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No notes added.') }}</p>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            @if ($appointment->status === \App\Models\Appointment::STATUS_PENDING)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Pending request') }}</p>
                    <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('You can cancel this request while staff has not confirmed it yet.') }}</p>
                    <div class="mt-5">
                        <x-confirm-action
                            :action="route('customer.appointments.cancel', $appointment)"
                            method="PATCH"
                            label="{{ __('Cancel request') }}"
                            confirm-title="{{ __('Cancel appointment request?') }}"
                            confirm-message="{{ __('This pending request will be marked cancelled and staff will no longer review it for confirmation.') }}"
                            confirm-button="{{ __('Cancel request') }}"
                            button-class="casa-danger-button w-full"
                        />
                    </div>
                </x-app-card>
            @endif

            @if ($appointment->status === \App\Models\Appointment::STATUS_COMPLETED && ! $appointment->feedback)
                <a href="{{ route('customer.feedback.create', ['appointment_id' => $appointment->id]) }}" class="casa-button-primary w-full">{{ __('Submit feedback') }}</a>
            @elseif ($appointment->feedback)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Feedback') }}</p>
                    <p class="mt-3 text-sm text-casa-muted">{{ __('You already submitted feedback for this appointment.') }}</p>
                </x-app-card>
            @endif
        </aside>
    </div>
</x-app-layout>
