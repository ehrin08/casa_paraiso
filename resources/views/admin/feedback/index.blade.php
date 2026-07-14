<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review ratings, comments, and simple sentiment labels from completed visits.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-metric-grid grid gap-3 sm:gap-4 md:grid-cols-3" data-metric-grid>
            <x-metric-card label="Positive" :value="$summary['positive']" meta="Satisfied feedback" tone="green" />
            <x-metric-card label="Neutral" :value="$summary['neutral']" meta="Middle rating" tone="gold" />
            <x-metric-card label="Negative" :value="$summary['negative']" meta="Needs attention" tone="charcoal" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Reviews') }}" title="{{ __('Customer feedback') }}" :count="$feedback->total()" :reset-url="route('admin.feedback.index')" :active-filters="collect(request()->only(['q', 'sentiment_label']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <form method="GET" action="{{ route('admin.feedback.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customer, service, comment') }}" aria-label="{{ __('Search feedback') }}">
                    <select name="sentiment_label" class="casa-input">
                        <option value="">{{ __('All sentiment') }}</option>
                        @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                            <option value="{{ $option }}" @selected($sentiment === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($feedback->isEmpty())
                    <x-empty-state title="{{ __('No feedback yet') }}" description="{{ __('Customer feedback appears after completed appointments.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
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
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $item->customerProfile?->user?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->service?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->rating }}/5</td>
                                    <td class="px-4 py-4"><x-status-badge>{{ ucfirst($item->sentiment_label) }}</x-status-badge></td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->submitted_at?->format('M d, Y') }}</td>
                                    <td class="px-4 py-4"><a href="{{ route('admin.feedback.show', $item) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $feedback->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
