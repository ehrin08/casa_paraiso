<x-app-layout>
    @php $recordPaymentModal = 'staff-appointment-payment-'.$appointment->id; @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
            </p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-input-error :messages="$errors->all()" />

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Details') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Service schedule') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Requested') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->requested_start_at?->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Scheduled') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') ?: __('Not confirmed') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                        <dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Customer phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->customerProfile?->user?->phone ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Therapist preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Customer notes') }}</h2>
                </div>
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No customer notes.') }}</p>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            @if ($appointment->status === \App\Models\Appointment::STATUS_PENDING)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Confirm') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Accept request') }}</h2>
                    @php
                        $confirmAppointmentFormId = 'confirm-appointment-'.$appointment->id;
                    @endphp
                    <form id="{{ $confirmAppointmentFormId }}" method="POST" action="{{ route('staff.appointments.update', $appointment) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="service_id" value="{{ $appointment->service_id }}">
                        <input type="hidden" name="requested_start_at" value="{{ $appointment->requested_start_at?->format('Y-m-d H:i:s') }}">
                        <input type="hidden" name="status" value="{{ \App\Models\Appointment::STATUS_CONFIRMED }}">
                        <div>
                            <x-input-label for="scheduled_start_at" :value="__('Scheduled time')" />
                            <x-text-input id="scheduled_start_at" name="scheduled_start_at" type="datetime-local" class="mt-2" :value="old('scheduled_start_at', $appointment->requested_start_at?->format('Y-m-d\\TH:i'))" required />
                        </div>
                        <textarea name="internal_notes" rows="4" class="casa-input" placeholder="{{ __('Internal notes') }}">{{ old('internal_notes', $appointment->internal_notes) }}</textarea>
                        <x-confirm-submit
                            :form="$confirmAppointmentFormId"
                            label="{{ __('Confirm appointment') }}"
                            confirm-title="{{ __('Confirm appointment request?') }}"
                            confirm-message="{{ __('This assigns the appointment to you at the scheduled time and moves it into the confirmed queue.') }}"
                            confirm-button="{{ __('Confirm appointment') }}"
                            button-class="casa-button-primary w-full"
                        />
                    </form>
                </x-app-card>
            @elseif ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Service actions') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Update outcome') }}</h2>
                    <div class="mt-5 space-y-3">
                        @foreach ([\App\Models\Appointment::STATUS_COMPLETED => __('Mark completed'), \App\Models\Appointment::STATUS_NO_SHOW => __('Mark no-show'), \App\Models\Appointment::STATUS_CANCELLED => __('Cancel')] as $targetStatus => $label)
                            @php
                                $confirmTitle = match ($targetStatus) {
                                    \App\Models\Appointment::STATUS_COMPLETED => __('Mark appointment completed?'),
                                    \App\Models\Appointment::STATUS_NO_SHOW => __('Mark appointment no-show?'),
                                    default => __('Cancel appointment?'),
                                };
                                $confirmMessage = match ($targetStatus) {
                                    \App\Models\Appointment::STATUS_COMPLETED => __('This records the visit as completed and keeps the appointment in the customer history.'),
                                    \App\Models\Appointment::STATUS_NO_SHOW => __('This records that the customer did not attend the confirmed appointment.'),
                                    default => __('This cancels the confirmed appointment and removes it from the active service queue.'),
                                };
                            @endphp

                            <x-confirm-action
                                :action="route('staff.appointments.update', $appointment)"
                                method="PATCH"
                                :label="$label"
                                :confirm-title="$confirmTitle"
                                :confirm-message="$confirmMessage"
                                :confirm-button="$label"
                                :button-class="$targetStatus === \App\Models\Appointment::STATUS_COMPLETED ? 'casa-button-primary w-full' : 'casa-button-secondary w-full'"
                            >
                                <input type="hidden" name="service_id" value="{{ $appointment->service_id }}">
                                <input type="hidden" name="requested_start_at" value="{{ $appointment->requested_start_at?->format('Y-m-d H:i:s') }}">
                                <input type="hidden" name="status" value="{{ $targetStatus }}">
                            </x-confirm-action>
                        @endforeach
                    </div>
                </x-app-card>

                <button type="button" class="casa-button-secondary w-full" x-data="" x-on:click="$dispatch('open-modal', '{{ $recordPaymentModal }}')">{{ __('Record payment') }}</button>
            @endif

            <x-app-card>
                <p class="casa-section-label">{{ __('Customer') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ $appointment->customerProfile?->user?->name }}</h2>
                @if ($appointment->customerProfile)
                    <a href="{{ route('staff.customers.show', $appointment->customerProfile) }}" class="mt-5 casa-button-secondary w-full">{{ __('Open customer') }}</a>
                @endif
            </x-app-card>
        </aside>
    </div>

    <x-modal :name="$recordPaymentModal" :show="old('_modal') === $recordPaymentModal" maxWidth="4xl" focusable><div class="p-5">@include('staff.transactions.partials.form', ['transaction' => $transaction, 'action' => route('staff.transactions.store'), 'method' => 'POST', 'submitLabel' => __('Create transaction'), 'modalName' => $recordPaymentModal])</div></x-modal>
</x-app-layout>
