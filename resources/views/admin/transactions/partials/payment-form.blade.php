@php $idPrefix = $modalName.'-'; @endphp
<form method="POST" action="{{ route('admin.transactions.payments.store', $transaction) }}" class="casa-modal-form p-5 sm:p-6">
    @csrf
    <input type="hidden" name="_modal" value="{{ $modalName }}">
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <p class="casa-section-label">{{ __('Receive payment') }}</p>
    <h2 class="mt-2 font-display text-2xl font-black text-casa-ink">{{ __('Record payment for :number', ['number' => $transaction->transaction_number]) }}</h2>
    <p class="mt-2 text-sm text-casa-muted">{{ __('Open balance: PHP :amount', ['amount' => number_format((float) $transaction->open_balance, 2)]) }}</p>

    <div class="mt-5 grid gap-5 sm:grid-cols-3">
        <div>
            <x-input-label :for="$idPrefix.'payment_amount'" :value="__('Payment amount')" />
            <x-text-input :id="$idPrefix.'payment_amount'" name="payment_amount" type="number" step="0.01" min="0.01" :max="$transaction->open_balance" class="mt-2" :value="old('payment_amount', number_format((float) $transaction->open_balance, 2, '.', ''))" required autofocus />
            <x-input-error class="mt-2" :messages="$errors->get('payment_amount')" />
        </div>
        <div>
            <x-input-label :for="$idPrefix.'payment_method'" :value="__('Payment method')" />
            <select id="{{ $idPrefix }}payment_method" name="payment_method" class="casa-input mt-2" required>
                @foreach (\App\Models\Transaction::PAYMENT_METHODS as $option)
                    <option value="{{ $option }}" @selected(old('payment_method', \App\Models\Transaction::METHOD_CASH) === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
        </div>
        <div>
            <x-input-label :for="$idPrefix.'paid_at'" :value="__('Payment date')" />
            <x-text-input :id="$idPrefix.'paid_at'" name="paid_at" type="datetime-local" class="mt-2" :value="old('paid_at', now()->format('Y-m-d\TH:i'))" required />
            <x-input-error class="mt-2" :messages="$errors->get('paid_at')" />
        </div>
    </div>
    <div class="mt-5">
        <x-input-label :for="$idPrefix.'reason'" :value="__('Note (optional)')" />
        <textarea id="{{ $idPrefix }}reason" name="reason" rows="3" class="casa-input mt-2">{{ old('reason') }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('reason')" />
    </div>
    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end" data-modal-actions>
        <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>
        <button type="submit" class="casa-button-primary">{{ __('Record payment') }}</button>
    </div>
</form>
