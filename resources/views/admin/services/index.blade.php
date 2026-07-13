<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Services') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Maintain Casa Paraiso treatments, pricing, duration, and booking availability.') }}
            </p>
        </div>

        @if ($services->isNotEmpty())
            <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
        @endif
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Active" :value="$activeCount" meta="Bookable treatments" tone="green" />
            <x-metric-card label="Inactive" :value="$inactiveCount" meta="Hidden from booking" tone="gold" />
            <x-metric-card label="Catalog" :value="$services->total()" meta="Total services" tone="brown" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Catalog') }}" title="{{ __('Treatment list') }}" :count="$services->total()" :reset-url="route('admin.services.index')" default-sort="status" default-direction="desc">
                <x-filter-form
                    :action="route('admin.services.index')"
                    :sort="$sort"
                    :direction="$direction"
                    :search="$search"
                    :search-placeholder="__('Search service, slug, description')"
                    :search-label="__('Search services')"
                    class="sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]"
                >
                    <x-active-status-filter :value="$status" />
                </x-filter-form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($services->isEmpty())
                    <x-empty-state
                        title="{{ __('No services yet') }}"
                        description="{{ __('Add the first treatment with duration and pricing before staff assignments and appointments are connected.') }}"
                    >
                        <x-slot name="action">
                            <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="name">{{ __('Service') }}</x-sortable-th>
                                <x-sortable-th sort="duration">{{ __('Duration') }}</x-sortable-th>
                                <x-sortable-th sort="price">{{ __('Price') }}</x-sortable-th>
                                <x-sortable-th sort="staff">{{ __('Usage') }}</x-sortable-th>
                                <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($services as $service)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.services.show', $service) }}" class="font-bold text-casa-ink hover:text-casa-palm">
                                            {{ $service->name }}
                                        </a>
                                        <p class="mt-1 text-sm text-casa-muted">{{ $service->slug }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $service->duration_minutes }} {{ __('min') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-ink">PHP {{ number_format((float) $service->price, 2) }}</td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        {{ trans_choice(':count staff|:count staff', $service->staff_profiles_count) }},
                                        {{ trans_choice(':count appointment|:count appointments', $service->appointments_count) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">
                                            {{ $service->is_active ? __('Active') : __('Inactive') }}
                                        </x-status-badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('admin.services.edit', $service) }}" class="font-bold text-casa-palm hover:text-casa-palm-dark">
                                                {{ __('Edit') }}
                                            </a>
                                            <x-confirm-action
                                                :action="route('admin.services.toggle', $service)"
                                                method="PATCH"
                                                :label="$service->is_active ? __('Deactivate') : __('Activate')"
                                                :confirm-title="$service->is_active ? __('Deactivate service?') : __('Activate service?')"
                                                :confirm-message="$service->is_active ? __('Customers will no longer be able to book this service until it is activated again.') : __('Customers will be able to request this service once it is active.')"
                                                :confirm-button="$service->is_active ? __('Deactivate') : __('Activate')"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $services->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
