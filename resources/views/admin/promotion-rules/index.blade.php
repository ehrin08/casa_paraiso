<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Promotion configuration') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Promotion rules') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Connect each customer segment to the offer shown in the promotion review queue.') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Review queue') }}</a>
            <a href="{{ route('admin.rfm-segments.index') }}" class="casa-button-secondary">{{ __('RFM segments') }}</a>
            <a href="{{ route('admin.promotion-rules.create') }}" class="casa-button-primary">{{ __('Add rule') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Rule library') }}" title="{{ __('Segment offers') }}" :count="$rules->total()" :reset-url="route('admin.promotion-rules.index')" :active-filters="collect(request()->only(['q', 'rfm_segment_id', 'status']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <x-slot name="meta">
                    <span class="casa-filter-chip">{{ __(':count active', ['count' => $activeCount]) }}</span>
                    <span class="casa-filter-chip">{{ __(':count inactive', ['count' => $inactiveCount]) }}</span>
                </x-slot>
                <form method="GET" action="{{ route('admin.promotion-rules.index') }}" class="casa-filter-grid sm:grid-cols-2 lg:min-w-[48rem] lg:grid-cols-[minmax(12rem,1fr)_auto_auto_auto]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search rule, segment, or offer') }}" aria-label="{{ __('Search promotion rules') }}">
                    <select name="rfm_segment_id" class="casa-input" aria-label="{{ __('RFM segment') }}">
                        <option value="">{{ __('All segments') }}</option>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment->id }}" @selected($segmentId === $segment->id)>{{ $segment->name }}{{ $segment->is_active ? '' : ' (inactive)' }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="casa-input" aria-label="{{ __('Rule status') }}">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($rules->isEmpty())
                    <x-empty-state title="{{ __('No promotion rules') }}" description="{{ __('Create a rule to connect an RFM segment with a reviewable offer.') }}">
                        <x-slot name="action"><a href="{{ route('admin.promotion-rules.create') }}" class="casa-button-primary">{{ __('Add rule') }}</a></x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="name">{{ __('Rule') }}</x-sortable-th>
                                <x-sortable-th sort="segment">{{ __('Segment') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Suggested offer') }}</th>
                                <x-sortable-th sort="suggestions">{{ __('Usage') }}</x-sortable-th>
                                <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($rules as $rule)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <p class="font-bold text-casa-text">{{ $rule->name }}</p>
                                        <p class="mt-1 max-w-sm text-xs leading-5 text-casa-muted">{{ $rule->description ?: __('No description') }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $rule->rfmSegment?->name ?: __('Deleted segment') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $rule->suggested_offer }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count suggestion|:count suggestions', $rule->promotion_suggestions_count) }}</td>
                                    <td class="px-4 py-4"><x-status-badge :tone="$rule->is_active ? 'success' : 'dark'">{{ $rule->is_active ? __('Active') : __('Inactive') }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.promotion-rules.edit', $rule) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Edit') }}</a>
                                            <x-confirm-action
                                                :action="route('admin.promotion-rules.toggle', $rule)"
                                                method="PATCH"
                                                :label="$rule->is_active ? __('Deactivate') : __('Activate')"
                                                :confirm-title="$rule->is_active ? __('Deactivate this rule?') : __('Activate this rule?')"
                                                :confirm-message="$rule->is_active ? __('New generation will stop using this offer. Existing suggestions remain unchanged.') : __('New generation may use this offer for matching customers.')"
                                                :confirm-button="$rule->is_active ? __('Deactivate') : __('Activate')"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $rules->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
