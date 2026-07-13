<x-app-card>
    <x-list-toolbar eyebrow="{{ __('Reviews') }}" :title="$title" :count="$feedback->total()" :reset-url="route($indexRouteName)" default-sort="submitted" default-direction="desc">
        <x-filter-form
            :action="route($indexRouteName)"
            :sort="$sort"
            :direction="$direction"
            :search="$search"
            :search-placeholder="__('Search customer, service, comment')"
            :search-label="__('Search feedback')"
            class="sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]"
        >
            <x-sentiment-filter :value="$sentiment" />
        </x-filter-form>
    </x-list-toolbar>

    <div class="mt-5">
        @if ($feedback->isEmpty())
            <x-empty-state :title="$emptyTitle" :description="$emptyDescription" />
        @else
            <x-table-shell>
                <thead class="bg-casa-bg text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                    <tr>
                        <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                        <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                        <x-sortable-th sort="rating">{{ __('Rating') }}</x-sortable-th>
                        <x-sortable-th sort="sentiment">{{ __('Sentiment') }}</x-sortable-th>
                        <x-sortable-th sort="submitted">{{ __('Submitted') }}</x-sortable-th>
                        <th class="px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-casa-border text-sm">
                    @foreach ($feedback as $item)
                        <tr class="casa-table-row">
                            <td class="px-4 py-4 font-semibold text-casa-ink">{{ $item->customerProfile?->user?->name }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $item->service?->name }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $item->rating }}/5</td>
                            <td class="px-4 py-4"><x-status-badge :status="$item->sentiment_label">{{ ucfirst($item->sentiment_label) }}</x-status-badge></td>
                            <td class="px-4 py-4 text-casa-muted">{{ $item->submitted_at?->format('M d, Y') }}</td>
                            <td class="px-4 py-4"><a href="{{ route($showRouteName, $item) }}" class="font-bold text-casa-palm hover:text-casa-palm-dark">{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
            <div class="mt-5">{{ $feedback->links() }}</div>
        @endif
    </div>
</x-app-card>
