@php
    $idPrefix = $modalName.'-';
    $charge = (float) ($appointment->transaction?->amount ?? $appointment->quoted_amount ?? $appointment->service?->price ?? 0);
    $alreadyPaid = (float) ($appointment->transaction?->amount_paid ?? 0);
    $remaining = max($charge - $alreadyPaid, 0);
@endphp

<x-form-shell :action="route('admin.appointments.complete', $appointment)" :modal-name="$modalName" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Finish service') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Complete the visit') }}</h2>
            <p class="mt-2 text-sm text-casa-muted">{{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}</p>
        </div>

        <dl class="mt-5 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Charge') }}</dt><dd class="mt-2 font-extrabold text-casa-ink">PHP {{ number_format($charge, 2) }}</dd></div>
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Already paid') }}</dt><dd class="mt-2 font-extrabold text-casa-ink">PHP {{ number_format($alreadyPaid, 2) }}</dd></div>
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Balance') }}</dt><dd class="mt-2 font-extrabold text-casa-ink">PHP {{ number_format($remaining, 2) }}</dd></div>
        </dl>

        @if ($remaining > 0)
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <x-input-label :for="$idPrefix.'payment_amount'" :value="__('Payment received now')" />
                    <x-text-input :id="$idPrefix.'payment_amount'" name="payment_amount" type="number" step="0.01" min="0.01" :max="$remaining" class="mt-2" :value="old('payment_amount', number_format($remaining, 2, '.', ''))" />
                    <p class="mt-2 text-sm text-casa-muted">{{ __('Clear this field to complete without collecting payment.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_amount')" />
                </div>
                <div>
                    <x-input-label :for="$idPrefix.'payment_method'" :value="__('Payment method')" />
                    <select :id="$idPrefix.'payment_method'" name="payment_method" class="casa-input mt-2">
                        <option value="">{{ __('Select when paid') }}</option>
                        @foreach (\App\Models\Transaction::PAYMENT_METHODS as $option)
                            <option value="{{ $option }}" @selected(old('payment_method', \App\Models\Transaction::METHOD_CASH) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                </div>
                <div>
                    <x-input-label :for="$idPrefix.'paid_at'" :value="__('Payment date')" />
                    <x-text-input :id="$idPrefix.'paid_at'" name="paid_at" type="datetime-local" class="mt-2" :value="old('paid_at', now()->format('Y-m-d\TH:i'))" />
                    <x-input-error class="mt-2" :messages="$errors->get('paid_at')" />
                </div>
            </div>
        @endif

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label :for="$idPrefix.'reason'" :value="__('Payment note')" />
                <textarea :id="$idPrefix.'reason'" name="reason" rows="3" class="casa-input mt-2">{{ old('reason') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('reason')" />
            </div>
            <div>
                <x-input-label :for="$idPrefix.'notes'" :value="__('Transaction notes')" />
                <textarea :id="$idPrefix.'notes'" name="notes" rows="3" class="casa-input mt-2">{{ old('notes') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('One recorded outcome') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Completion reuses the linked transaction when one exists and never creates a second charge for this visit.') }}</p>
        </x-app-card>
        <button type="submit" class="casa-button-primary w-full">{{ __('Complete visit') }}</button>
        <button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Keep service open') }}</button>
    </aside>
</x-form-shell>
