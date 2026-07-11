<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin schedule') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Bookings & therapist coverage') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review requested visits, place confirmed appointments, and maintain therapist availability from one weekly calendar.') }}
            </p>
        </div>

        <a href="{{ route('admin.appointments.create') }}" class="casa-button-primary">
            <x-nav-icon name="calendar" class="size-4" />
            {{ __('Add appointment') }}
        </a>
    </x-slot>

    <div class="space-y-5">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Pending" :value="$summary['pending']" meta="Requests awaiting review" tone="gold" />
            <x-metric-card label="Confirmed" :value="$summary['confirmed']" meta="Placed on therapist calendars" tone="green" />
            <x-metric-card label="Completed" :value="$summary['completed']" meta="Finished services" tone="brown" />
        </section>

        <x-operational-calendar
            :feed-url="route('admin.appointments.calendar')"
            :create-url="route('admin.appointments.create')"
            :initial-week="$initialWeek"
            :initial-mode="$mode"
            role="admin"
            :services="$services"
            :staff-profiles="$staffProfiles"
        />
    </div>
</x-app-layout>
