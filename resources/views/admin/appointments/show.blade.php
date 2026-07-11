<x-app-layout>
    @php $recordPaymentModal = 'admin-appointment-payment-'.$appointment->id; $editAppointmentModal = 'admin-appointment-edit-'.$appointment->id; @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Appointment detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="button" class="casa-button-secondary" x-data="" x-on:click="$dispatch('open-modal', '{{ $recordPaymentModal }}')">{{ __('Record payment') }}</button>
            <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $editAppointmentModal }}')">{{ __('Edit') }}</button>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Booking') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Schedule and assignment') }}</h2>
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
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Staff') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->staffProfile?->user?->name ?: __('Pending') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                        <dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Customer and internal notes') }}</h2>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <h3 class="font-bold text-casa-text">{{ __('Customer') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No customer notes.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <h3 class="font-bold text-casa-text">{{ __('Internal') }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->internal_notes ?: __('No internal notes.') }}</p>
                    </div>
                </div>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Status log') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Audit trail') }}</h2>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($appointment->statusLogs as $log)
                        <div class="rounded-2xl border border-casa-border bg-casa-bg p-4 text-sm">
                            <p class="font-bold text-casa-text">{{ ucfirst(str_replace('_', ' ', $log->from_status ?: 'new')) }} → {{ ucfirst(str_replace('_', ' ', $log->to_status)) }}</p>
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
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ $appointment->customerProfile?->user?->name }}</h2>
                <p class="mt-3 text-sm text-casa-muted">{{ $appointment->customerProfile?->user?->phone ?: __('No phone') }}</p>
                @if ($appointment->customerProfile)
                    <a href="{{ route('admin.customers.show', $appointment->customerProfile) }}" class="mt-5 casa-button-secondary w-full">{{ __('Open customer') }}</a>
                @endif
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Related records') }}</p>
                <div class="mt-4 space-y-2 text-sm text-casa-muted">
                    <p>{{ trans_choice(':count transaction|:count transactions', $appointment->transactions->count()) }}</p>
                    <p>{{ $appointment->feedback ? __('Feedback submitted') : __('No feedback yet') }}</p>
                </div>
            </x-app-card>
        </aside>
    </div>

    <x-modal :name="$editAppointmentModal" :show="old('_modal') === $editAppointmentModal" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.form', ['appointment' => $appointment, 'action' => route('admin.appointments.update', $appointment), 'method' => 'PATCH', 'submitLabel' => __('Save appointment'), 'modalName' => $editAppointmentModal])</div></x-modal>
    <x-modal :name="$recordPaymentModal" :show="old('_modal') === $recordPaymentModal" maxWidth="5xl" focusable><div class="p-5">@include('admin.transactions.partials.form', ['transaction' => $transaction, 'appointments' => $transactionAppointments, 'action' => route('admin.transactions.store'), 'method' => 'POST', 'submitLabel' => __('Create transaction'), 'modalName' => $recordPaymentModal])</div></x-modal>
</x-app-layout>
