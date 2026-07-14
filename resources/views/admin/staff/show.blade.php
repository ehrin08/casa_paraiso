<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $staffProfile->user->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $staffProfile->specialization ?: __('Therapist access, treatment eligibility, and future schedule connections.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.staff.index') }}" class="casa-button-secondary">{{ __('All therapists') }}</a>
            <a href="{{ route('admin.staff.edit', $staffProfile) }}" class="casa-button-primary">{{ __('Edit therapist') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @php
            $weeklySchedulesByDay = $staffProfile->weeklySchedules->groupBy('day_of_week');
            $formatTime = fn ($time) => $time ? substr((string) $time, 0, 5) : null;
            $createShiftModal = 'admin-staff-shift-create-'.$staffProfile->id;
            $createExceptionModal = 'admin-staff-exception-create-'.$staffProfile->id;
            $newWeeklySchedule = new \App\Models\StaffWeeklySchedule(['is_available' => true]);
            $newScheduleException = new \App\Models\StaffScheduleException(['exception_type' => \App\Models\StaffScheduleException::TYPE_UNAVAILABLE]);
        @endphp

        <x-input-error :messages="$errors->all()" />

        @if (session('schedule_conflicts'))
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5" role="alert">
                <p class="text-sm font-extrabold text-red-800">{{ __('Resolve these confirmed appointments first') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (session('schedule_conflicts') as $conflict)
                        <a href="{{ $conflict['url'] }}" class="rounded-full border border-red-200 bg-white px-3 py-2 text-xs font-extrabold text-red-800 hover:border-red-400">
                            {{ $conflict['number'] }} · {{ \Illuminate\Support\Carbon::parse($conflict['starts_at'])->format('M d, g:i A') }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-stat-strip :items="[
            ['label' => __('Services'), 'value' => $staffProfile->services_count, 'meta' => __('Assigned treatments'), 'tone' => 'green'],
            ['label' => __('Schedules'), 'value' => $staffProfile->weekly_schedules_count, 'meta' => __('Availability entries'), 'tone' => 'gold'],
            ['label' => __('Appointments'), 'value' => $staffProfile->appointments_count, 'meta' => __('Linked bookings'), 'tone' => 'brown'],
            ['label' => __('Access'), 'value' => $staffProfile->user->is_active ? __('Active') : __('Inactive'), 'meta' => __('Login status'), 'tone' => 'dark'],
        ]" />

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Profile') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Therapist information') }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :tone="$staffProfile->user->is_active ? 'success' : 'dark'">
                            {{ $staffProfile->user->is_active ? __('Active account') : __('Inactive account') }}
                        </x-status-badge>
                        <x-status-badge :tone="$staffProfile->is_bookable ? 'success' : 'warning'">
                            {{ $staffProfile->is_bookable ? __('Bookable') : __('Not bookable') }}
                        </x-status-badge>
                        <x-status-badge>{{ ucfirst($staffProfile->staff_type) }}</x-status-badge>
                    </div>
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Email') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->user->email }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->user->phone ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Position') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->position ?: __('Therapist') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Hire date') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $staffProfile->hire_date?->format('M d, Y') ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Bio') }}</dt>
                        <dd class="mt-2 text-sm leading-6 text-casa-muted">{{ $staffProfile->bio ?: __('No bio has been added yet.') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            <aside class="space-y-4">
                <x-app-card>
                    <p class="casa-section-label">{{ __('Assigned services') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Treatment eligibility') }}</h2>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @forelse ($staffProfile->services as $service)
                            <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">{{ $service->name }}</x-status-badge>
                        @empty
                            <p class="text-sm leading-6 text-casa-muted">{{ __('No services assigned yet.') }}</p>
                        @endforelse
                    </div>
                </x-app-card>
            </aside>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Weekly schedule') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Recurring availability') }}</h2>
                    </div>
                    <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $createShiftModal }}')">{{ __('Add shift') }}</button>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @foreach (\App\Models\StaffWeeklySchedule::DAYS as $dayValue => $dayLabel)
                        <div class="rounded-2xl border border-casa-border bg-casa-bg p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-display text-lg font-black text-casa-text">{{ $dayLabel }}</h3>
                                <x-status-badge>{{ trans_choice(':count shift|:count shifts', ($weeklySchedulesByDay[$dayValue] ?? collect())->count()) }}</x-status-badge>
                            </div>

                            <div class="mt-4 space-y-3">
                                @forelse ($weeklySchedulesByDay[$dayValue] ?? [] as $weeklySchedule)
                                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="font-bold text-casa-text">
                                                    {{ $formatTime($weeklySchedule->start_time) }} - {{ $weeklySchedule->ends_next_day ? __('12:00 midnight') : $formatTime($weeklySchedule->end_time) }}
                                                </p>
                                                <x-status-badge class="mt-2" :tone="$weeklySchedule->is_available ? 'success' : 'dark'">
                                                    {{ $weeklySchedule->is_available ? __('Available') : __('Unavailable') }}
                                                </x-status-badge>
                                            </div>
                                            <div class="flex gap-3 text-sm">
                                                <button type="button" class="font-bold text-casa-primary hover:text-casa-primary-dark" x-data="" x-on:click="$dispatch('open-modal', 'admin-staff-shift-edit-{{ $weeklySchedule->id }}')">{{ __('Edit') }}</button>
                                                <x-confirm-action
                                                    :action="route('admin.staff.weekly-schedules.destroy', [$staffProfile, $weeklySchedule])"
                                                    method="DELETE"
                                                    label="{{ __('Remove') }}"
                                                    confirm-title="{{ __('Remove weekly shift?') }}"
                                                    confirm-message="{{ __('This recurring availability shift will no longer be used for future appointment availability.') }}"
                                                    confirm-button="{{ __('Remove') }}"
                                                    button-class="font-bold text-casa-muted hover:text-red-700"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm leading-6 text-casa-muted">{{ __('No recurring shift set.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-app-card>

            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Exceptions') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Upcoming overrides') }}</h2>
                    </div>
                    <button type="button" class="casa-button-secondary" x-data="" x-on:click="$dispatch('open-modal', '{{ $createExceptionModal }}')">{{ __('Add exception') }}</button>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse ($staffProfile->scheduleExceptions as $scheduleException)
                        <div class="rounded-2xl border border-casa-border bg-casa-bg p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-display text-lg font-black text-casa-text">{{ $scheduleException->exception_date->format('M d, Y') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <x-status-badge :tone="$scheduleException->exception_type === \App\Models\StaffScheduleException::TYPE_AVAILABLE ? 'success' : 'warning'">
                                            {{ ucfirst($scheduleException->exception_type) }}
                                        </x-status-badge>
                                        <x-status-badge>
                                            @if ($scheduleException->start_time && $scheduleException->end_time)
                                                {{ $formatTime($scheduleException->start_time) }} - {{ $scheduleException->ends_next_day ? __('12:00 midnight') : $formatTime($scheduleException->end_time) }}
                                            @else
                                                {{ __('Full day') }}
                                            @endif
                                        </x-status-badge>
                                    </div>
                                    @if ($scheduleException->reason)
                                        <p class="mt-3 text-sm leading-6 text-casa-muted">{{ $scheduleException->reason }}</p>
                                    @endif
                                </div>
                                <div class="flex gap-3 text-sm">
                                    <button type="button" class="font-bold text-casa-primary hover:text-casa-primary-dark" x-data="" x-on:click="$dispatch('open-modal', 'admin-staff-exception-edit-{{ $scheduleException->id }}')">{{ __('Edit') }}</button>
                                    <x-confirm-action
                                        :action="route('admin.staff.schedule-exceptions.destroy', [$staffProfile, $scheduleException])"
                                        method="DELETE"
                                        label="{{ __('Remove') }}"
                                        confirm-title="{{ __('Remove schedule exception?') }}"
                                        confirm-message="{{ __('This date-specific schedule override will no longer affect appointment availability.') }}"
                                        confirm-button="{{ __('Remove') }}"
                                        button-class="font-bold text-casa-muted hover:text-red-700"
                                    />
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-empty-state
                            title="{{ __('No upcoming exceptions') }}"
                            description="{{ __('Add date-specific availability changes for leaves, special openings, or partial-day blocks.') }}"
                        />
                    @endforelse
                </div>
            </x-app-card>
        </section>
    </div>
    <x-modal :name="$createShiftModal" :show="old('_modal') === $createShiftModal" maxWidth="4xl" focusable><div class="p-5">@include('admin.staff.weekly-schedules.partials.form', ['staffProfile' => $staffProfile, 'weeklySchedule' => $newWeeklySchedule, 'action' => route('admin.staff.weekly-schedules.store', $staffProfile), 'method' => 'POST', 'submitLabel' => __('Create shift'), 'modalName' => $createShiftModal])</div></x-modal>
    <x-modal :name="$createExceptionModal" :show="old('_modal') === $createExceptionModal" maxWidth="4xl" focusable><div class="p-5">@include('admin.staff.schedule-exceptions.partials.form', ['staffProfile' => $staffProfile, 'scheduleException' => $newScheduleException, 'action' => route('admin.staff.schedule-exceptions.store', $staffProfile), 'method' => 'POST', 'submitLabel' => __('Create exception'), 'modalName' => $createExceptionModal])</div></x-modal>
    @foreach ($staffProfile->weeklySchedules as $weeklySchedule) @php $editShiftModal = 'admin-staff-shift-edit-'.$weeklySchedule->id; @endphp <x-modal :name="$editShiftModal" :show="old('_modal') === $editShiftModal" maxWidth="4xl" focusable><div class="p-5">@include('admin.staff.weekly-schedules.partials.form', ['staffProfile' => $staffProfile, 'weeklySchedule' => $weeklySchedule, 'action' => route('admin.staff.weekly-schedules.update', [$staffProfile, $weeklySchedule]), 'method' => 'PATCH', 'submitLabel' => __('Save shift'), 'modalName' => $editShiftModal])</div></x-modal> @endforeach
    @foreach ($staffProfile->scheduleExceptions as $scheduleException) @php $editExceptionModal = 'admin-staff-exception-edit-'.$scheduleException->id; @endphp <x-modal :name="$editExceptionModal" :show="old('_modal') === $editExceptionModal" maxWidth="4xl" focusable><div class="p-5">@include('admin.staff.schedule-exceptions.partials.form', ['staffProfile' => $staffProfile, 'scheduleException' => $scheduleException, 'action' => route('admin.staff.schedule-exceptions.update', [$staffProfile, $scheduleException]), 'method' => 'PATCH', 'submitLabel' => __('Save exception'), 'modalName' => $editExceptionModal])</div></x-modal> @endforeach
</x-app-layout>
