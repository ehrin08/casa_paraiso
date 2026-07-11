<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('My appointment calendar') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Follow requested, confirmed, and completed visits in one calm monthly view.') }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary">
            <x-nav-icon name="calendar" class="size-4" />
            {{ __('Request appointment') }}
        </a>
    </x-slot>

    <div
        x-data="customerAppointmentCalendar({ feedUrl: @js(route('customer.appointments.calendar')), initialMonth: @js($initialMonth) })"
        x-init="init()"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]"
        data-customer-appointment-calendar
    >
        <section class="space-y-5">
            <div class="casa-editorial-card overflow-hidden">
                <div class="casa-dark-panel relative overflow-hidden p-6 sm:p-8">
                    <svg class="absolute -end-5 -top-8 size-36 text-casa-brass/20" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                        <path d="M18 104C43 70 69 44 105 18" stroke="currentColor" stroke-width="2"/>
                        <path d="M45 76C27 75 20 62 20 49c18 0 31 10 25 27Zm23-23C55 39 57 24 67 13c13 13 14 28 1 40Zm18-16c1-17 13-27 26-30 3 17-6 29-26 30Z" fill="currentColor"/>
                    </svg>
                    <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-casa-brass-light">{{ __('Your wellness rhythm') }}</p>
                    <h2 class="mt-3 max-w-xl font-editorial text-4xl font-semibold leading-none text-white">{{ __('Every visit, held in one place.') }}</h2>
                    <p class="mt-4 max-w-xl text-sm leading-7 text-white/68">{{ __('Choose a day to see its request or confirmed schedule. A pending request is not final until the spa team confirms it.') }}</p>
                </div>
                <dl class="grid grid-cols-3 divide-x divide-casa-border bg-casa-paper p-1">
                    <div class="p-4 text-center sm:p-5"><dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-casa-muted">{{ __('Upcoming') }}</dt><dd class="mt-2 text-2xl font-extrabold text-casa-palm sm:text-3xl">{{ $summary['upcoming'] }}</dd></div>
                    <div class="p-4 text-center sm:p-5"><dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-casa-muted">{{ __('Pending') }}</dt><dd class="mt-2 text-2xl font-extrabold text-casa-cacao sm:text-3xl">{{ $summary['pending'] }}</dd></div>
                    <div class="p-4 text-center sm:p-5"><dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-casa-muted">{{ __('Completed') }}</dt><dd class="mt-2 text-2xl font-extrabold text-casa-text sm:text-3xl">{{ $summary['completed'] }}</dd></div>
                </dl>
            </div>

            <section class="casa-editorial-card p-4 sm:p-6" x-bind:aria-busy="loading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <button type="button" class="casa-icon-button" x-on:click="previousMonth()" aria-label="{{ __('Previous month') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <h2 class="min-w-48 text-center font-editorial text-3xl font-semibold text-casa-text" x-text="monthLabel"></h2>
                        <button type="button" class="casa-icon-button" x-on:click="nextMonth()" aria-label="{{ __('Next month') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                    <label class="block sm:min-w-44">
                        <span class="sr-only">{{ __('Filter appointment status') }}</span>
                        <select class="casa-input" x-model="statusFilter" x-on:change="load()">
                            <option value="">{{ __('All appointment statuses') }}</option>
                            @foreach (\App\Models\Appointment::STATUSES as $status)
                                <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-show="error" x-text="error" role="alert"></p>

                <div class="mt-5 grid grid-cols-7 gap-1 text-center text-[0.62rem] font-extrabold uppercase tracking-[0.08em] text-casa-muted sm:gap-2 sm:text-xs">
                    <template x-for="dayName in weekDays" x-bind:key="dayName"><span x-text="dayName"></span></template>
                </div>
                <div class="mt-2 grid grid-cols-7 gap-1 sm:gap-2" role="grid" aria-label="{{ __('Appointment month') }}">
                    <template x-for="day in calendarDays" x-bind:key="day.date">
                        <button
                            type="button"
                            class="min-h-14 rounded-xl border p-1.5 text-left transition sm:min-h-24 sm:rounded-2xl sm:p-2.5"
                            x-on:click="selectDate(day.date)"
                            x-on:keydown.right.prevent="moveDate(day.date, 1)"
                            x-on:keydown.left.prevent="moveDate(day.date, -1)"
                            x-on:keydown.down.prevent="moveDate(day.date, 7)"
                            x-on:keydown.up.prevent="moveDate(day.date, -7)"
                            x-bind:aria-label="`${day.date}, ${day.events.length} appointments`"
                            x-bind:tabindex="selectedDate === day.date ? 0 : -1"
                            x-bind:data-customer-calendar-day="day.date"
                            x-bind:class="selectedDate === day.date
                                ? 'border-casa-palm bg-casa-palm text-white shadow-md'
                                : day.inMonth
                                    ? 'border-casa-border bg-casa-paper text-casa-text hover:border-casa-brass'
                                    : 'border-casa-border/50 bg-casa-sand/30 text-casa-muted/45'"
                            role="gridcell"
                        >
                            <span class="text-xs font-extrabold sm:text-sm" x-text="day.label"></span>
                            <span class="mt-2 flex flex-wrap gap-1" x-show="day.statuses.length">
                                <template x-for="status in day.statuses" x-bind:key="status">
                                    <span
                                        class="size-2 rounded-full ring-1 ring-white/70"
                                        x-bind:class="{
                                            'bg-casa-brass': status === 'pending',
                                            'bg-casa-palm': status === 'confirmed',
                                            'bg-casa-cacao': status === 'completed',
                                            'bg-red-500': status === 'cancelled' || status === 'no_show'
                                        }"
                                        x-bind:title="status.replaceAll('_', ' ')"
                                    ></span>
                                </template>
                            </span>
                            <span class="mt-2 hidden text-[0.62rem] font-extrabold uppercase tracking-[0.06em] sm:block" x-show="day.events.length" x-text="`${day.events.length} visit${day.events.length === 1 ? '' : 's'}`"></span>
                        </button>
                    </template>
                </div>
            </section>
        </section>

        <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <x-app-card>
                <div class="flex items-start justify-between gap-3 border-b border-casa-border pb-4">
                    <div>
                        <p class="casa-eyebrow">{{ __('Selected day') }}</p>
                        <h2 class="mt-2 font-editorial text-2xl font-semibold text-casa-text" x-text="selectedDateLabel"></h2>
                    </div>
                    <x-status-badge x-show="loading">{{ __('Loading') }}</x-status-badge>
                </div>

                <div class="mt-4 space-y-3" x-show="selectedEvents.length">
                    <template x-for="event in selectedEvents" x-bind:key="event.id">
                        <a x-bind:href="event.detail_url" class="block rounded-2xl border border-casa-border bg-casa-sand/40 p-4 transition hover:border-casa-brass hover:bg-casa-paper">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-extrabold uppercase tracking-[0.08em] text-casa-cacao" x-text="event.status.replaceAll('_', ' ')"></span>
                                <span class="text-xs font-bold text-casa-muted" x-text="eventTime(event)"></span>
                            </div>
                            <strong class="mt-2 block text-sm text-casa-text" x-text="event.title"></strong>
                            <span class="mt-1 block text-xs leading-5 text-casa-muted" x-text="event.subtitle"></span>
                        </a>
                    </template>
                </div>

                <div class="mt-4 rounded-2xl border border-dashed border-casa-border bg-casa-sand/35 p-5 text-center" x-show="!loading && !selectedEvents.length">
                    <p class="text-sm font-extrabold text-casa-text">{{ __('No visit on this day') }}</p>
                    <p class="mt-2 text-xs leading-5 text-casa-muted">{{ __('Choose another date or start a new appointment request.') }}</p>
                </div>

                <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary mt-5 w-full">{{ __('Request a visit') }}</a>
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Calendar key') }}</p>
                <div class="mt-4 space-y-3 text-sm text-casa-muted">
                    <p class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-casa-brass"></span>{{ __('Requested · awaiting review') }}</p>
                    <p class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-casa-palm"></span>{{ __('Confirmed · final schedule') }}</p>
                    <p class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-casa-cacao"></span>{{ __('Completed · visit finished') }}</p>
                </div>
                <div class="casa-divider my-5"></div>
                <p class="text-sm leading-6 text-casa-muted">{{ __('Open every day, 1:00 PM to 12:00 midnight.') }}</p>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
