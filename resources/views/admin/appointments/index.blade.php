<x-app-layout>
    @php $createAppointmentModal = 'admin-appointment-create'; @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review requests, assign staff, confirm schedules, and track visit completion.') }}
            </p>
        </div>

        <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $createAppointmentModal }}')">{{ __('Add appointment') }}</button>
    </x-slot>

    @php $createAppointment = $appointment; @endphp
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Pending" :value="$summary['pending']" meta="Requests awaiting review" tone="gold" />
            <x-metric-card label="Confirmed" :value="$summary['confirmed']" meta="Scheduled visits" tone="green" />
            <x-metric-card label="Completed" :value="$summary['completed']" meta="Finished services" tone="brown" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Queue') }}" title="{{ __('Appointment list') }}" :count="$appointments->total()" :reset-url="route('admin.appointments.index')">
                <form method="GET" action="{{ route('admin.appointments.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search number, customer, service, staff') }}" aria-label="{{ __('Search appointments') }}">
                    <select name="status" class="casa-input">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\Appointment::STATUSES as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($appointments->isEmpty())
                    <x-empty-state title="{{ __('No appointments found') }}" description="{{ __('Requests and scheduled visits will appear here once customers book or staff create records.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="number">{{ __('No.') }}</x-sortable-th>
                                <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                                <x-sortable-th sort="schedule">{{ __('Schedule') }}</x-sortable-th>
                                <x-sortable-th sort="staff">{{ __('Staff') }}</x-sortable-th>
                                <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($appointments as $appointment)
                                @php
                                    $tone = match ($appointment->status) {
                                        \App\Models\Appointment::STATUS_CONFIRMED,
                                        \App\Models\Appointment::STATUS_COMPLETED => 'success',
                                        \App\Models\Appointment::STATUS_CANCELLED,
                                        \App\Models\Appointment::STATUS_NO_SHOW => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->appointment_number }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->customerProfile?->user?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->staffProfile?->user?->name ?? __('Pending') }}</td>
                                    <td class="px-4 py-4"><x-status-badge :tone="$tone">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.appointments.show', $appointment) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
                                            <button type="button" class="font-bold text-casa-muted hover:text-casa-primary" x-data="" x-on:click="$dispatch('open-modal', 'admin-appointment-edit-{{ $appointment->id }}')">{{ __('Edit') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $appointments->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>

    <x-modal :name="$createAppointmentModal" :show="old('_modal') === $createAppointmentModal" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.form', ['appointment' => $createAppointment, 'action' => route('admin.appointments.store'), 'method' => 'POST', 'submitLabel' => __('Create appointment'), 'modalName' => $createAppointmentModal])</div></x-modal>
    @foreach ($appointments as $appointment)
        @php $editAppointmentModal = 'admin-appointment-edit-'.$appointment->id; @endphp
        <x-modal :name="$editAppointmentModal" :show="old('_modal') === $editAppointmentModal" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.form', ['appointment' => $appointment, 'action' => route('admin.appointments.update', $appointment), 'method' => 'PATCH', 'submitLabel' => __('Save appointment'), 'modalName' => $editAppointmentModal])</div></x-modal>
    @endforeach
</x-app-layout>
