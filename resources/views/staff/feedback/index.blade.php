<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('View feedback related to your assigned appointments.') }}</p>
        </div>
    </x-slot>

    @include('feedback.partials.index-table', [
        'title' => __('Related feedback'),
        'indexRouteName' => 'staff.feedback.index',
        'showRouteName' => 'staff.feedback.show',
        'emptyTitle' => __('No related feedback yet'),
        'emptyDescription' => __('Customer reviews for your completed appointments will appear here.'),
    ])
</x-app-layout>
