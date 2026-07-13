<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review ratings, comments, and simple sentiment labels from completed visits.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Positive" :value="$summary['positive']" meta="Satisfied feedback" tone="green" />
            <x-metric-card label="Neutral" :value="$summary['neutral']" meta="Middle rating" tone="gold" />
            <x-metric-card label="Negative" :value="$summary['negative']" meta="Needs attention" tone="charcoal" />
        </section>

        @include('feedback.partials.index-table', [
            'title' => __('Customer feedback'),
            'indexRouteName' => 'admin.feedback.index',
            'showRouteName' => 'admin.feedback.show',
            'emptyTitle' => __('No feedback yet'),
            'emptyDescription' => __('Customer feedback appears after completed appointments.'),
        ])
    </div>
</x-app-layout>
