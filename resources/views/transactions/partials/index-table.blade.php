@php $editable = $editable ?? false; @endphp

<x-app-card>
    <x-list-toolbar eyebrow="{{ __('Payments') }}" :title="$title" :count="$transactions->total()" :reset-url="route($indexRouteName)" default-sort="created" default-direction="desc">
        <x-filter-form
            :action="route($indexRouteName)"
            :sort="$sort"
            :direction="$direction"
            :search="$search"
            :search-placeholder="__('Search transaction, customer, service')"
            :search-label="__('Search transactions')"
            class="sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]"
        >
            <select name="payment_status" class="casa-input">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Models\Transaction::PAYMENT_STATUSES as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </x-filter-form>
    </x-list-toolbar>

    <div class="mt-5">
        @if ($transactions->isEmpty())
            <x-empty-state title="{{ __('No transactions yet') }}" :description="$emptyDescription" />
        @else
            <x-table-shell>
                <thead class="bg-casa-bg text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                    <tr>
                        <x-sortable-th sort="number">{{ __('No.') }}</x-sortable-th>
                        <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                        <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                        <x-sortable-th sort="amount">{{ __('Charge and balance') }}</x-sortable-th>
                        <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                        <th class="px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-casa-border text-sm">
                    @foreach ($transactions as $transaction)
                        <tr class="casa-table-row">
                            <td class="px-4 py-4 font-semibold text-casa-ink">{{ $transaction->transaction_number }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $transaction->customerProfile?->user?->name }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $transaction->service?->name ?: __('General service') }}</td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-casa-ink">PHP {{ number_format((float) $transaction->amount, 2) }}</p>
                                <p class="mt-1 text-sm text-casa-muted">{{ __('PHP :paid paid · PHP :balance open', ['paid' => number_format((float) $transaction->amount_paid, 2), 'balance' => number_format((float) $transaction->open_balance, 2)]) }}</p>
                            </td>
                            <td class="px-4 py-4"><x-status-badge :status="$transaction->payment_status">{{ ucfirst($transaction->payment_status) }}</x-status-badge></td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route($showRouteName, $transaction) }}" class="font-bold text-casa-palm hover:text-casa-palm-dark">{{ __('Open') }}</a>
                                    @if ($editable)
                                        <a href="{{ route('admin.transactions.edit', $transaction) }}" class="font-bold text-casa-muted hover:text-casa-palm">{{ __('Edit') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
            <div class="mt-5">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-app-card>
