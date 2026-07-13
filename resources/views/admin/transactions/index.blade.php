<x-app-layout>
    @php $createTransactionModal = 'admin-transaction-create'; @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Payments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Record manual payments, manage transaction status, and jump into revenue exports from one workspace.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.reports.index', ['type' => 'transactions']) }}" class="casa-button-secondary">{{ __('Revenue report') }}</a>
            <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $createTransactionModal }}')">{{ __('Record payment') }}</button>
        </div>
    </x-slot>

    @php $createTransaction = $transaction; @endphp
    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <x-metric-card label="Collected" value="PHP {{ number_format((float) $summary['paid'], 2) }}" meta="Net payments received" tone="green" />
            <x-metric-card label="Open balance" value="PHP {{ number_format((float) $summary['unpaid'], 2) }}" meta="Unpaid or partial" tone="gold" />
            <x-metric-card label="Records" :value="$summary['count']" meta="All transactions" tone="brown" />
        </section>

        @include('transactions.partials.index-table', [
            'title' => __('Transaction list'),
            'indexRouteName' => 'admin.transactions.index',
            'showRouteName' => 'admin.transactions.show',
            'emptyDescription' => __('Manual payment records will appear here after an administrator records a service payment.'),
            'editable' => true,
        ])

        <section class="grid gap-4 md:grid-cols-2">
            <x-app-card>
                <p class="casa-section-label">{{ __('Exports') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Download payment records') }}</h2>
                <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Use the filtered revenue report when management needs CSV records without database access.') }}</p>
                <a href="{{ route('admin.reports.export', ['type' => 'transactions']) }}" class="mt-5 casa-button-secondary w-full" data-turbo="false">{{ __('Export transactions CSV') }}</a>
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Manual payment flow') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Record, update, review') }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-casa-bg p-4 text-sm font-semibold text-casa-ink">{{ __('Link appointment') }}</div>
                    <div class="rounded-2xl bg-casa-bg p-4 text-sm font-semibold text-casa-ink">{{ __('Collect payment') }}</div>
                    <div class="rounded-2xl bg-casa-bg p-4 text-sm font-semibold text-casa-ink">{{ __('Export report') }}</div>
                </div>
            </x-app-card>
        </section>
    </div>

    <x-modal :name="$createTransactionModal" :show="old('_modal') === $createTransactionModal" :label="__('Record payment')" maxWidth="5xl" focusable>
        <div class="p-5">@include('admin.transactions.partials.form', ['transaction' => $createTransaction, 'action' => route('admin.transactions.store'), 'method' => 'POST', 'submitLabel' => __('Create transaction'), 'modalName' => $createTransactionModal])</div>
    </x-modal>
</x-app-layout>
