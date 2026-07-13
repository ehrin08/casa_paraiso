<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Admin workspace') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Dashboard') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('A calm management overview for bookings, revenue, feedback, and promotion reviews.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.appointments.index') }}" class="casa-button-primary">{{ __('Open schedule') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-metric-card label="Today" :value="$summary['todayAppointments'] ?? 0" meta="Appointments scheduled" tone="brown" />
            <x-metric-card label="Upcoming" :value="$summary['upcomingAppointments'] ?? 0" meta="Confirmed future visits" tone="gold" />
            <x-metric-card label="Revenue" value="PHP {{ number_format((float) ($summary['todayRevenue'] ?? 0), 2) }}" meta="Paid transactions today" tone="green" />
            <x-metric-card label="Feedback" :value="$summary['newFeedback'] ?? 0" meta="New reviews today" tone="charcoal" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.55fr)]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Service queue') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Upcoming confirmed visits') }}</h2>
                    </div>
                    <x-status-badge tone="information">{{ trans_choice(':count upcoming|:count upcoming', $summary['upcomingAppointments'] ?? 0) }}</x-status-badge>
                </div>

                <div class="mt-5">
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('No.') }}</th>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Scheduled') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @forelse ($upcomingAppointments as $appointment)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4 font-semibold text-casa-ink">{{ $appointment->appointment_number }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->customerProfile?->user?->name ?? __('Customer') }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name ?? __('Service') }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') }}</td>
                                    <td class="px-4 py-4"><x-status-badge :status="$appointment->status">{{ __(ucfirst($appointment->status)) }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.appointments.show', $appointment) }}" class="font-bold text-casa-cacao hover:text-casa-cacao-dark" data-panel-link data-turbo="false">
                                            {{ __('Open') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-casa-ink" colspan="6">{{ __('No upcoming confirmed visits') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-table-shell>
                </div>
            </x-app-card>

            <aside class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card">
                <p class="text-sm font-extrabold uppercase tracking-[0.16em] text-casa-brass-light">{{ __('Management focus') }}</p>
                <h2 class="mt-4 text-2xl font-extrabold text-white">{{ __('Keep today moving.') }}</h2>
                <p class="mt-4 text-sm leading-7 text-white/65">{{ __('Review today’s confirmed visits, then move through payments, feedback, and promotion decisions.') }}</p>
                <div class="casa-divider my-6"></div>
                <div class="space-y-2">
                    <a href="{{ route('admin.promotions.index') }}" class="flex min-h-11 items-center justify-between rounded-xl border border-white/10 bg-white/[0.06] px-4 text-sm font-bold text-white transition hover:bg-white/10">
                        <span>{{ __('Promotion reviews') }}</span><span class="text-casa-brass-light">{{ $summary['promotionReviews'] ?? 0 }}</span>
                        <span class="sr-only">{{ trans_choice(':count promotion review waiting|:count promotion reviews waiting', $summary['promotionReviews'] ?? 0) }}</span>
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="flex min-h-11 items-center justify-between rounded-xl border border-white/10 bg-white/[0.06] px-4 text-sm font-bold text-white transition hover:bg-white/10">
                        <span>{{ __('Reports & exports') }}</span><span aria-hidden="true">→</span>
                    </a>
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
