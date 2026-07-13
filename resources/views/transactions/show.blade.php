@php
    $isAdminView = (bool) $editRouteName;
    $canReceivePayment = $isAdminView
        && ! in_array($transaction->payment_status, \App\Models\Transaction::TERMINAL_PAYMENT_STATUSES, true)
        && (float) $transaction->open_balance > 0;
    $canRefund = $isAdminView
        && ! in_array($transaction->payment_status, \App\Models\Transaction::TERMINAL_PAYMENT_STATUSES, true)
        && (float) $transaction->amount_paid > 0;
    $canVoid = $isAdminView
        && ! in_array($transaction->payment_status, \App\Models\Transaction::TERMINAL_PAYMENT_STATUSES, true);
    $paymentModal = 'transaction-payment-'.$transaction->id;
    $refundModal = 'transaction-refund-'.$transaction->id;
    $voidModal = 'transaction-void-'.$transaction->id;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Transaction detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $transaction->transaction_number }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $transaction->customerProfile?->user?->name }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if ($canReceivePayment)
                <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $paymentModal }}')">{{ __('Record payment') }}</button>
            @endif
            @if ($editRouteName)
                <a href="{{ route($editRouteName, $transaction) }}" @class(['casa-button-primary' => ! $canReceivePayment, 'casa-button-secondary' => $canReceivePayment])>{{ __('Correct details') }}</a>
            @endif
            <a href="{{ route($indexRouteName) }}" class="casa-button-secondary">{{ __('All transactions') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-app-card>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-casa-bg p-4">
                    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Charge') }}</dt>
                    <dd class="mt-2 font-display text-2xl font-black text-casa-ink">PHP {{ number_format((float) $transaction->amount, 2) }}</dd>
                </div>
                <div class="rounded-2xl bg-casa-bg p-4">
                    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Collected') }}</dt>
                    <dd class="mt-2 font-display text-2xl font-black text-casa-ink">PHP {{ number_format((float) $transaction->amount_paid, 2) }}</dd>
                </div>
                <div class="rounded-2xl bg-casa-bg p-4">
                    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Open balance') }}</dt>
                    <dd class="mt-2 font-display text-2xl font-black text-casa-ink">PHP {{ number_format((float) $transaction->open_balance, 2) }}</dd>
                </div>
                <div class="rounded-2xl bg-casa-bg p-4">
                    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                    <dd class="mt-2"><x-status-badge :status="$transaction->payment_status">{{ ucfirst($transaction->payment_status) }}</x-status-badge></dd>
                </div>
                <div class="rounded-2xl bg-casa-bg p-4">
                    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Service') }}</dt>
                    <dd class="mt-2 font-semibold text-casa-ink">{{ $transaction->service?->name ?: __('General service') }}</dd>
                </div>
                @if ($showAccountingDetails)
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Latest method') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-ink">{{ $transaction->payment_method ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Latest payment') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-ink">{{ $transaction->paid_at?->format('M d, Y g:i A') ?: __('Not paid') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Recorded by') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-ink">{{ $transaction->recorder?->name }}</dd>
                    </div>
                @endif
            </dl>
            @if ($showAccountingDetails && $transaction->notes)
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $transaction->notes }}</p>
            @endif
        </x-app-card>

        @if ($showAccountingDetails)
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Audit history') }}</p>
                        <h2 class="mt-2 text-xl font-extrabold text-casa-ink">{{ __('Payment adjustments') }}</h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($canRefund)<button type="button" class="casa-button-secondary" x-data="" x-on:click="$dispatch('open-modal', '{{ $refundModal }}')">{{ __('Refund in full') }}</button>@endif
                        @if ($canVoid)<button type="button" class="casa-button-secondary" x-data="" x-on:click="$dispatch('open-modal', '{{ $voidModal }}')">{{ __('Void transaction') }}</button>@endif
                    </div>
                </div>

                <div class="mt-5">
                    @if ($transaction->adjustments->isEmpty())
                        <x-empty-state title="{{ __('No adjustments recorded') }}" description="{{ __('Payments and corrections will appear here as append-only audit entries.') }}" />
                    @else
                        <x-table-shell>
                            <thead class="text-left text-sm font-black uppercase tracking-[0.1em] text-casa-muted">
                                <tr><th class="px-4 py-3">{{ __('Action') }}</th><th class="px-4 py-3">{{ __('Change') }}</th><th class="px-4 py-3">{{ __('New totals') }}</th><th class="px-4 py-3">{{ __('Actor and time') }}</th><th class="px-4 py-3">{{ __('Reason') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-casa-border text-sm">
                                @foreach ($transaction->adjustments as $adjustment)
                                    <tr class="casa-table-row">
                                        <td class="px-4 py-4"><x-status-badge :status="$adjustment->action">{{ str($adjustment->action)->replace('_', ' ')->title() }}</x-status-badge></td>
                                        <td class="px-4 py-4 font-semibold text-casa-ink">PHP {{ number_format((float) $adjustment->payment_delta, 2) }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ __('Charge PHP :charge · Paid PHP :paid', ['charge' => number_format((float) $adjustment->new_amount, 2), 'paid' => number_format((float) $adjustment->new_amount_paid, 2)]) }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $adjustment->actor?->name ?: __('System') }}<br>{{ $adjustment->occurred_at?->format('M d, Y g:i A') }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $adjustment->reason ?: __('No note') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table-shell>
                    @endif
                </div>
            </x-app-card>
        @endif
    </div>

    @if ($canReceivePayment)
        <x-modal :name="$paymentModal" :show="old('_modal') === $paymentModal" :label="__('Record payment for :number', ['number' => $transaction->transaction_number])" maxWidth="4xl" focusable>
            @include('admin.transactions.partials.payment-form', ['transaction' => $transaction, 'modalName' => $paymentModal])
        </x-modal>
    @endif

    @foreach ([
        ['show' => $canRefund, 'modal' => $refundModal, 'title' => __('Refund transaction in full'), 'description' => __('This returns the complete collected amount and closes the balance. Partial refunds are not supported.'), 'route' => 'admin.transactions.refund', 'button' => __('Refund in full')],
        ['show' => $canVoid, 'modal' => $voidModal, 'title' => __('Void transaction'), 'description' => __('This closes the transaction without deleting its history.'), 'route' => 'admin.transactions.void', 'button' => __('Void transaction')],
    ] as $action)
        @if ($action['show'])
            <x-modal :name="$action['modal']" :show="old('_modal') === $action['modal']" :label="$action['title']" maxWidth="2xl" focusable>
                <form method="POST" action="{{ route($action['route'], $transaction) }}" class="casa-modal-form p-5 sm:p-6">
                    @csrf @method('PATCH')
                    <input type="hidden" name="_modal" value="{{ $action['modal'] }}">
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <p class="casa-section-label">{{ __('Audited action') }}</p>
                    <h2 class="mt-2 font-display text-2xl font-black text-casa-ink">{{ $action['title'] }}</h2>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ $action['description'] }}</p>
                    <div class="mt-5">
                        <x-input-label :for="$action['modal'].'-reason'" :value="__('Reason')" />
                        <textarea id="{{ $action['modal'] }}-reason" name="reason" rows="4" class="casa-input mt-2" required>{{ old('reason') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end" data-modal-actions>
                        <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $action['modal'] }}')">{{ __('Cancel') }}</button>
                        <button type="submit" class="casa-danger-button">{{ $action['button'] }}</button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endforeach
</x-app-layout>
