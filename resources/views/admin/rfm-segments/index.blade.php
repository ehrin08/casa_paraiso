<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Promotion configuration') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('RFM segments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Define the recency, frequency, and spending thresholds used to group customers for review.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Review queue') }}</a>
            <a href="{{ route('admin.promotion-rules.index') }}" class="casa-button-secondary">{{ __('Promotion rules') }}</a>
        @if ($segments->isNotEmpty())
            <a href="{{ route('admin.rfm-segments.create') }}" class="casa-button-primary">{{ __('Add segment') }}</a>
        @endif
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Active" :value="$activeCount" meta="Used during generation" tone="green" />
            <x-metric-card label="Inactive" :value="$inactiveCount" meta="Retained for history" tone="gold" />
            <x-metric-card label="Segments" :value="$segments->total()" meta="Configured groups" tone="brown" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('RFM thresholds') }}" title="{{ __('Customer segments') }}" :count="$segments->total()" :reset-url="route('admin.rfm-segments.index')" default-sort="status" default-direction="desc">
                <x-filter-form
                    :action="route('admin.rfm-segments.index')"
                    :sort="$sort"
                    :direction="$direction"
                    :search="$search"
                    :search-placeholder="__('Search name or description')"
                    :search-label="__('Search RFM segments')"
                    class="sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]"
                >
                    <x-active-status-filter :value="$status" :label="__('Segment status')" />
                </x-filter-form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($segments->isEmpty())
                    <x-empty-state title="{{ __('No RFM segments') }}" description="{{ __('Add a segment before creating the promotion rule that will serve it.') }}">
                        <x-slot name="action"><a href="{{ route('admin.rfm-segments.create') }}" class="casa-button-primary">{{ __('Add segment') }}</a></x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="name">{{ __('Segment') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('R / F / M thresholds') }}</th>
                                <x-sortable-th sort="rules">{{ __('Usage') }}</x-sortable-th>
                                <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($segments as $segment)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <p class="font-bold text-casa-ink">{{ $segment->name }}</p>
                                        <p class="mt-1 max-w-sm text-sm leading-5 text-casa-muted">{{ $segment->description ?: __('No description') }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-casa-muted">
                                        <div class="flex flex-wrap gap-2" aria-label="{{ __('RFM threshold summary') }}">
                                            <span class="rounded-xl border border-casa-border bg-casa-bg px-2.5 py-1.5">R {{ $segment->recency_min_days ?? __('any') }} {{ __('to') }} {{ $segment->recency_max_days ?? __('any') }} {{ __('days') }}</span>
                                            <span class="rounded-xl border border-casa-border bg-casa-bg px-2.5 py-1.5">F {{ $segment->frequency_min ?? __('any') }} {{ __('to') }} {{ $segment->frequency_max ?? __('any') }}</span>
                                            <span class="rounded-xl border border-casa-border bg-casa-bg px-2.5 py-1.5">M {{ $segment->monetary_min ?? __('any') }} {{ __('to') }} {{ $segment->monetary_max ?? __('any') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        {{ trans_choice(':count rule|:count rules', $segment->promotion_rules_count) }}<br>
                                        <span class="text-sm">{{ trans_choice(':count suggestion|:count suggestions', $segment->promotion_suggestions_count) }}</span>
                                    </td>
                                    <td class="px-4 py-4"><x-status-badge :tone="$segment->is_active ? 'success' : 'dark'">{{ $segment->is_active ? __('Active') : __('Inactive') }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.rfm-segments.edit', $segment) }}" class="font-bold text-casa-palm hover:text-casa-palm-dark">{{ __('Edit') }}</a>
                                            <a href="{{ route('admin.promotion-rules.create', ['rfm_segment_id' => $segment->id]) }}" class="font-bold text-casa-palm hover:text-casa-palm-dark">{{ __('Add rule') }}</a>
                                            <x-confirm-action
                                                :action="route('admin.rfm-segments.toggle', $segment)"
                                                method="PATCH"
                                                :label="$segment->is_active ? __('Deactivate') : __('Activate')"
                                                :confirm-title="$segment->is_active ? __('Deactivate this segment?') : __('Activate this segment?')"
                                                :confirm-message="$segment->is_active ? __('New promotion generation will skip this segment. Existing suggestions remain available.') : __('New promotion generation may match customers to this segment again.')"
                                                :confirm-button="$segment->is_active ? __('Deactivate') : __('Activate')"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $segments->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
