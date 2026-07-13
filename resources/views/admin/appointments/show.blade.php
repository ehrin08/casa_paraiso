<x-app-layout>
    @php
        $completionModal = 'admin-appointment-completion-'.$appointment->id;
        $recordPaymentModal = 'admin-appointment-payment-'.$appointment->id;
        $editAppointmentModal = 'admin-appointment-edit-'.$appointment->id;
        $linkedTransaction = $appointment->transaction;
    @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Appointment detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED && $appointment->scheduled_start_at?->lte(now()))
                <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $completionModal }}')">{{ __('Finish service') }}</button>
            @endif
            @if ($appointment->status === \App\Models\Appointment::STATUS_COMPLETED && (! $linkedTransaction || $linkedTransaction->open_balance > 0))
                <button type="button" class="casa-button-secondary" x-data="" x-on:click="$dispatch('open-modal', '{{ $recordPaymentModal }}')">{{ __('Record payment') }}</button>
            @endif
            <button type="button" class="casa-button-secondary" x-data="" x-on:click="$dispatch('open-modal', '{{ $editAppointmentModal }}')">{{ __('Edit') }}</button>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Booking') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Schedule and assignment') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    @include('appointments.partials.schedule-summary', [
                        'scheduledFallback' => __('Not confirmed'),
                        'fourthLabel' => __('Assigned therapist'),
                        'fourthValue' => $appointment->staffProfile?->user?->name ?: __('Unassigned'),
                    ])
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Customer preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-ink">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd>
                        @if ($appointment->preferred_staff_profile_id && $appointment->staff_profile_id && $appointment->preferred_staff_profile_id !== $appointment->staff_profile_id)
                            <p class="mt-2 text-sm font-bold text-casa-cacao">{{ __('The confirmed therapist differs from the customer preference.') }}</p>
                        @endif
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Customer and internal notes') }}</h2>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <h3 class="font-bold text-casa-ink">{{ __('Customer') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No customer notes.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <h3 class="font-bold text-casa-ink">{{ __('Internal') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->internal_notes ?: __('No internal notes.') }}</p>
                    </div>
                </div>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Status log') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Audit trail') }}</h2>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($appointment->statusLogs as $log)
                        <div class="rounded-2xl border border-casa-border bg-casa-bg p-4 text-sm">
                            <p class="font-bold text-casa-ink">{{ ucfirst(str_replace('_', ' ', $log->from_status ?: 'new')) }} → {{ ucfirst(str_replace('_', ' ', $log->to_status)) }}</p>
                            <p class="mt-1 text-casa-muted">{{ $log->changedBy?->name ?: __('System') }} · {{ $log->created_at?->format('M d, Y g:i A') }}</p>
                        </div>
                    @empty
                        <x-empty-state title="{{ __('No status changes yet') }}" description="{{ __('Status changes are logged after updates.') }}" />
                    @endforelse
                </div>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Customer') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ $appointment->customerProfile?->user?->name }}</h2>
                <p class="mt-3 text-sm text-casa-muted">{{ $appointment->customerProfile?->user?->phone ?: __('No phone') }}</p>
                @if ($appointment->customerProfile)
                    <a href="{{ route('admin.customers.show', $appointment->customerProfile) }}" class="mt-5 casa-button-secondary w-full">{{ __('Open customer') }}</a>
                @endif
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Related records') }}</p>
                <div class="mt-4 space-y-2 text-sm text-casa-muted">
                    @if ($linkedTransaction)
                        <p><a href="{{ route('admin.transactions.show', $linkedTransaction) }}" class="inline-flex min-h-11 items-center font-bold text-casa-palm hover:text-casa-palm-dark">{{ $linkedTransaction->transaction_number }}</a> · {{ __('PHP :paid paid of PHP :charge', ['paid' => number_format((float) $linkedTransaction->amount_paid, 2), 'charge' => number_format((float) $linkedTransaction->amount, 2)]) }}</p>
                    @else
                        <p>{{ __('No transaction yet') }}</p>
                    @endif
                    <p>{{ $appointment->feedback ? __('Feedback submitted') : __('No feedback yet') }}</p>
                </div>
            </x-app-card>
        </aside>
    </div>

    <x-modal :name="$editAppointmentModal" :show="old('_modal') === $editAppointmentModal" :label="__('Edit appointment :number', ['number' => $appointment->appointment_number])" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.form', ['appointment' => $appointment, 'action' => route('admin.appointments.update', $appointment), 'method' => 'PATCH', 'submitLabel' => __('Save appointment'), 'modalName' => $editAppointmentModal])</div></x-modal>
    <x-modal :name="$completionModal" :show="old('_modal') === $completionModal" :label="__('Complete appointment :number', ['number' => $appointment->appointment_number])" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.completion-form', ['appointment' => $appointment, 'modalName' => $completionModal])</div></x-modal>
    @if ($appointment->status === \App\Models\Appointment::STATUS_COMPLETED && (! $linkedTransaction || $linkedTransaction->open_balance > 0))
        <x-modal :name="$recordPaymentModal" :show="old('_modal') === $recordPaymentModal" :label="__('Record payment for :number', ['number' => $appointment->appointment_number])" maxWidth="5xl" focusable>
            @if ($linkedTransaction)
                @include('admin.transactions.partials.payment-form', ['transaction' => $linkedTransaction, 'modalName' => $recordPaymentModal])
            @else
                <div class="p-5">@include('admin.transactions.partials.form', ['transaction' => $transaction, 'appointments' => $transactionAppointments, 'action' => route('admin.transactions.store'), 'method' => 'POST', 'submitLabel' => __('Create transaction'), 'modalName' => $recordPaymentModal])</div>
            @endif
        </x-modal>
    @endif
</x-app-layout>
