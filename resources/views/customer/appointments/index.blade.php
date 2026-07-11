<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('My appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Your requests, confirmed visits, and wellness history—kept together in one calm place.') }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary">
            <x-nav-icon name="calendar" class="size-4" />
            {{ __('Request appointment') }}
        </a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="space-y-6">
            <div class="casa-editorial-card overflow-hidden">
                <div class="casa-dark-panel relative overflow-hidden p-6 sm:p-8">
                    <svg class="absolute -end-5 -top-8 size-36 text-casa-brass/20" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                        <path d="M18 104C43 70 69 44 105 18" stroke="currentColor" stroke-width="2"/>
                        <path d="M45 76C27 75 20 62 20 49c18 0 31 10 25 27Zm23-23C55 39 57 24 67 13c13 13 14 28 1 40Zm18-16c1-17 13-27 26-30 3 17-6 29-26 30Z" fill="currentColor"/>
                    </svg>
                    <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-casa-brass-light">{{ __('Your wellness rhythm') }}</p>
                    <h2 class="mt-3 max-w-xl font-editorial text-4xl font-semibold leading-none text-white">{{ __('Your next pause starts with a request.') }}</h2>
                    <p class="mt-4 max-w-xl text-sm leading-7 text-white/68">{{ __('Choose a service and preferred time. The Casa Paraiso team will confirm the final schedule with care.') }}</p>
                </div>

                <dl class="grid grid-cols-3 divide-x divide-casa-border bg-casa-paper p-1">
                    <div class="p-4 text-center sm:p-5">
                        <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-casa-muted">{{ __('Upcoming') }}</dt>
                        <dd class="mt-2 text-2xl font-extrabold text-casa-palm sm:text-3xl">{{ $summary['upcoming'] ?? 0 }}</dd>
                    </div>
                    <div class="p-4 text-center sm:p-5">
                        <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-casa-muted">{{ __('Pending') }}</dt>
                        <dd class="mt-2 text-2xl font-extrabold text-casa-cacao sm:text-3xl">{{ $summary['pending'] ?? 0 }}</dd>
                    </div>
                    <div class="p-4 text-center sm:p-5">
                        <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-casa-muted">{{ __('Completed') }}</dt>
                        <dd class="mt-2 text-2xl font-extrabold text-casa-text sm:text-3xl">{{ $summary['completed'] ?? 0 }}</dd>
                    </div>
                </dl>
            </div>

            @if ($appointments->isEmpty() && ! request()->hasAny(['q', 'status']))
                <x-empty-state
                    title="{{ __('No appointment requests yet') }}"
                    description="{{ __('Begin with a treatment and preferred time. Our team will review availability before confirming your visit.') }}"
                >
                    <x-slot name="action">
                        <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary">{{ __('Request your first visit') }}</a>
                    </x-slot>
                </x-empty-state>
            @else
                <x-app-card>
                    <x-list-toolbar eyebrow="{{ __('Appointment history') }}" title="{{ __('Your visits') }}" :count="$appointments->total()" :reset-url="route('customer.appointments.index')">
                        <form method="GET" action="{{ route('customer.appointments.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(10rem,1fr)_auto_auto]">
                            <input type="hidden" name="sort" value="{{ $sort }}">
                            <input type="hidden" name="direction" value="{{ $direction }}">
                            <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search service or number') }}" aria-label="{{ __('Search appointments') }}">
                            <select name="status" class="casa-input" aria-label="{{ __('Filter by status') }}">
                                <option value="">{{ __('All statuses') }}</option>
                                @foreach (\App\Models\Appointment::STATUSES as $option)
                                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                        </form>
                    </x-list-toolbar>

                    <div class="mt-5 space-y-3">
                        @forelse ($appointments as $appointment)
                            @php
                                $statusTone = match ($appointment->status) {
                                    \App\Models\Appointment::STATUS_CONFIRMED,
                                    \App\Models\Appointment::STATUS_COMPLETED => 'success',
                                    \App\Models\Appointment::STATUS_CANCELLED,
                                    \App\Models\Appointment::STATUS_NO_SHOW => 'danger',
                                    default => 'warning',
                                };
                                $visitAt = $appointment->scheduled_start_at ?? $appointment->requested_start_at;
                            @endphp
                            <article class="group rounded-2xl border border-casa-border bg-casa-paper p-4 transition hover:border-casa-brass/55 hover:shadow-casa-card sm:p-5">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-status-badge :tone="$statusTone">{{ __(ucfirst(str_replace('_', ' ', $appointment->status))) }}</x-status-badge>
                                            <span class="text-xs font-bold text-casa-muted">{{ $appointment->appointment_number }}</span>
                                        </div>
                                        <h3 class="mt-3 text-lg font-extrabold text-casa-text">{{ $appointment->service?->name ?? __('Spa service') }}</h3>
                                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-casa-muted">
                                            <span>{{ $visitAt?->format('M d, Y · g:i A') ?? __('Schedule pending') }}</span>
                                            <span>{{ $appointment->staffProfile?->user?->name ?? __('Therapist to be confirmed') }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('customer.appointments.show', $appointment) }}" class="casa-button-secondary shrink-0">{{ __('View details') }}</a>
                                </div>
                            </article>
                        @empty
                            <x-empty-state title="{{ __('No appointments found') }}" description="{{ __('Try clearing your filters to see the rest of your visit history.') }}" />
                        @endforelse
                    </div>

                    @if ($appointments->hasPages())
                        <div class="mt-5">{{ $appointments->links() }}</div>
                    @endif
                </x-app-card>
            @endif
        </section>

        <aside class="space-y-4">
            <x-app-card class="lg:sticky lg:top-6">
                <p class="casa-eyebrow">{{ __('How it works') }}</p>
                <h2 class="mt-4 font-editorial text-3xl font-semibold text-casa-text">{{ __('From request to rest.') }}</h2>
                <ol class="mt-6 space-y-5">
                    <li class="flex gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-casa-cacao text-xs font-extrabold text-white">1</span>
                        <span class="text-sm leading-6 text-casa-muted"><strong class="block text-casa-text">Choose</strong>{{ __('Select a treatment and preferred time.') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-casa-palm text-xs font-extrabold text-white">2</span>
                        <span class="text-sm leading-6 text-casa-muted"><strong class="block text-casa-text">Review</strong>{{ __('Staff checks therapist availability.') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-casa-brass text-xs font-extrabold text-casa-charcoal">3</span>
                        <span class="text-sm leading-6 text-casa-muted"><strong class="block text-casa-text">Confirm</strong>{{ __('Your final schedule appears here.') }}</span>
                    </li>
                </ol>
                <div class="casa-divider my-6"></div>
                <p class="text-sm leading-6 text-casa-muted">{{ __('Open every day, 1:00 PM to 12:00 MN.') }}</p>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
