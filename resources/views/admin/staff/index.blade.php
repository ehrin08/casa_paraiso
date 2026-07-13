<x-app-layout>
    @php $createStaffModal = 'admin-staff-create'; @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Team') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Manage operational staff profiles, treatment eligibility, and the people available for appointment scheduling.') }}
            </p>
        </div>

        @if (auth()->user()->isSuperAdmin() && $staffProfiles->isNotEmpty())
            <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $createStaffModal }}')">{{ __('Add staff') }}</button>
        @endif
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Active accounts" :value="$activeAccountCount" meta="Can sign in" tone="green" />
            <x-metric-card label="Inactive accounts" :value="$inactiveAccountCount" meta="Access disabled" tone="gold" />
            <x-metric-card label="Bookable" :value="$bookableCount" meta="Available for appointments" tone="brown" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Team') }}" title="{{ __('Staff profiles') }}" :count="$staffProfiles->total()" :reset-url="route('admin.staff.index')" default-sort="status" default-direction="desc">
                <form method="GET" action="{{ route('admin.staff.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto_auto] lg:min-w-[48rem]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search staff') }}" aria-label="{{ __('Search staff') }}">
                    <select name="status" class="casa-input">
                        <option value="">{{ __('All accounts') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    <select name="bookable" class="casa-input">
                        <option value="">{{ __('Any bookable') }}</option>
                        <option value="yes" @selected($bookable === 'yes')>{{ __('Bookable') }}</option>
                        <option value="no" @selected($bookable === 'no')>{{ __('Not bookable') }}</option>
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($staffProfiles->isEmpty())
                    <x-empty-state
                        title="{{ __('No staff profiles yet') }}"
                        description="{{ __('Create staff accounts before assigning services, schedules, and appointment responsibilities.') }}"
                    >
                        <x-slot name="action">
                            @if (auth()->user()->isSuperAdmin())
                                <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $createStaffModal }}')">{{ __('Add staff') }}</button>
                            @endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="name">{{ __('Staff') }}</x-sortable-th>
                                <x-sortable-th sort="position">{{ __('Role') }}</x-sortable-th>
                                <x-sortable-th sort="services">{{ __('Services') }}</x-sortable-th>
                                <x-sortable-th sort="appointments">{{ __('Appointments') }}</x-sortable-th>
                                <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($staffProfiles as $staffProfile)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="font-bold text-casa-ink hover:text-casa-palm">
                                            {{ $staffProfile->user->name }}
                                        </a>
                                        <p class="mt-1 text-sm text-casa-muted">{{ $staffProfile->user->email }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        <p class="font-semibold text-casa-ink">{{ $staffProfile->position ?: __('Staff') }}</p>
                                        <p class="mt-1 text-sm">{{ $staffProfile->specialization ?: __('No specialization yet') }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse ($staffProfile->services as $service)
                                                <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">{{ $service->name }}</x-status-badge>
                                            @empty
                                                <span class="text-casa-muted">{{ __('No services') }}</span>
                                            @endforelse
                                        </div>
                                        <p class="mt-2 text-sm text-casa-muted">{{ trans_choice(':count service|:count services', $staffProfile->services_count) }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count appointment|:count appointments', $staffProfile->appointments_count) }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :tone="$staffProfile->user->is_active ? 'success' : 'dark'">
                                                {{ $staffProfile->user->is_active ? __('Active') : __('Inactive') }}
                                            </x-status-badge>
                                            <x-status-badge :tone="$staffProfile->is_bookable ? 'success' : 'warning'">
                                                {{ $staffProfile->is_bookable ? __('Bookable') : __('Not bookable') }}
                                            </x-status-badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.staff.edit', $staffProfile) }}" class="font-bold text-casa-palm hover:text-casa-palm-dark">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">{{ $staffProfiles->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>

    @if (auth()->user()->isSuperAdmin())
        <x-modal :name="$createStaffModal" :show="old('_modal') === $createStaffModal" :label="__('Add staff member')" maxWidth="5xl" focusable>
            <div class="p-5">
                @include('admin.staff.partials.form', ['staffProfile' => $newStaffProfile, 'staffUser' => $newStaffUser, 'services' => $staffAssignableServices, 'assignedServiceIds' => [], 'action' => route('admin.staff.store'), 'method' => 'POST', 'submitLabel' => __('Create staff'), 'modalName' => $createStaffModal])
            </div>
        </x-modal>
    @endif

</x-app-layout>
