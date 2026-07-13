<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Feedback detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $feedback->customerProfile?->user?->name }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $feedback->service?->name }}</p>
        </div>
        <a href="{{ route($indexRouteName) }}" class="casa-button-secondary">{{ __('All feedback') }}</a>
    </x-slot>

    <x-app-card>
        <div @class(['grid gap-4', 'sm:grid-cols-3' => $showScore, 'sm:grid-cols-2' => ! $showScore])>
            <x-metric-card label="Rating" value="{{ $feedback->rating }}/5" meta="Customer score" tone="gold" />
            <x-metric-card label="Sentiment" value="{{ ucfirst($feedback->sentiment_label) }}" meta="Rule-based label" tone="green" />
            @if ($showScore)
                <x-metric-card label="Score" value="{{ number_format((float) $feedback->sentiment_score, 2) }}" meta="Simple scale" tone="brown" />
            @endif
        </div>
        <p class="mt-6 whitespace-pre-line rounded-2xl bg-casa-bg p-5 text-sm leading-7 text-casa-muted">{{ $feedback->comment ?: __('No written comment.') }}</p>
    </x-app-card>
</x-app-layout>
