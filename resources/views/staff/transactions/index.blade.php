<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Transactions') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Review transactions linked to your assigned appointments.') }}</p>
        </div>
    </x-slot>

    @include('transactions.partials.index-table', [
        'title' => __('Recorded transactions'),
        'indexRouteName' => 'staff.transactions.index',
        'showRouteName' => 'staff.transactions.show',
        'emptyDescription' => __('Payments linked to your appointments will appear here.'),
    ])
</x-app-layout>
