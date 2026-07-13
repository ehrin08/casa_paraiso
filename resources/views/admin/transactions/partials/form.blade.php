@php
    $modalName = $modalName ?? null;
    $idPrefix = $modalName ? $modalName.'-' : '';
    $isUpdate = strtoupper($method) !== 'POST';
    $linkedAppointment = $transaction->appointment;
    $idempotencyKey = old('idempotency_key', (string) \Illuminate\Support\Str::uuid());
@endphp

<x-form-shell
    :action="$action"
    :method="$method"
    :modal-name="$modalName"
    @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])
    x-data="{
        appointmentId: @js((string) old('appointment_id', $transaction->appointment_id)),
        appointmentCharges: @js($appointments->mapWithKeys(fn ($appointment) => [(string) $appointment->id => (string) ($appointment->quoted_amount ?? $appointment->service?->price ?? '')])->all())
    }"
>
    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Payment details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ $isUpdate ? __('Correct transaction details') : __('Record a transaction') }}</h2>
            <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Payment status updates automatically from the charge and collected totals.') }}</p>
        </div>

        <div class="mt-5 grid gap-5">
            @if ($isUpdate)
                @if ($linkedAppointment)
                    <input type="hidden" name="appointment_id" value="{{ $linkedAppointment->id }}">
                    <input type="hidden" name="customer_profile_id" value="{{ $linkedAppointment->customer_profile_id }}">
                    <input type="hidden" name="service_id" value="{{ $linkedAppointment->service_id }}">
                    <div class="rounded-2xl border border-casa-control-border bg-casa-sand/35 p-4">
                        <p class="casa-section-label">{{ __('Linked visit') }}</p>
                        <p class="mt-2 font-extrabold text-casa-ink">{{ $linkedAppointment->appointment_number }} · {{ $linkedAppointment->customerProfile?->user?->name }}</p>
                        <p class="mt-1 text-sm text-casa-muted">{{ $linkedAppointment->service?->name }} · {{ __('Identity is derived from this appointment; the total charge can be corrected with a reason.') }}</p>
                    </div>
                @else
                    <fieldset class="grid gap-5 sm:grid-cols-2">
                        <x-customer-select :customers="$customers" :selected-id="$transaction->customer_profile_id" :id-prefix="$idPrefix" />

                        <div>
                            <x-input-label :for="$idPrefix.'service_id'" :value="__('Service')" />
                            <select :id="$idPrefix.'service_id'" name="service_id" class="casa-input mt-2">
                                <option value="">{{ __('General service') }}</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected((int) old('service_id', $transaction->service_id) === $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                        </div>
                    </fieldset>
                @endif
            @else
                <div>
                    <x-input-label :for="$idPrefix.'appointment_id'" :value="__('Linked appointment')" />
                    <select :id="$idPrefix.'appointment_id'" name="appointment_id" class="casa-input mt-2" x-model="appointmentId">
                        <option value="">{{ __('No appointment link') }}</option>
                        @foreach ($appointments as $appointment)
                            <option value="{{ $appointment->id }}" @selected((int) old('appointment_id', $transaction->appointment_id) === $appointment->id)>
                                {{ $appointment->appointment_number }} · {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('When linked, the customer, service, and quoted charge come from the appointment.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('appointment_id')" />
                </div>

                <fieldset class="grid gap-5 sm:grid-cols-2" x-show="! appointmentId" x-bind:disabled="Boolean(appointmentId)">
                    <x-customer-select :customers="$customers" :selected-id="$transaction->customer_profile_id" :id-prefix="$idPrefix" />

                    <div>
                        <x-input-label :for="$idPrefix.'service_id'" :value="__('Service')" />
                        <select :id="$idPrefix.'service_id'" name="service_id" class="casa-input mt-2">
                            <option value="">{{ __('General service') }}</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected((int) old('service_id', $transaction->service_id) === $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                    </div>
                </fieldset>
            @endif

            @if ($isUpdate)
                <div>
                    <x-input-label :for="$idPrefix.'amount'" :value="__('Total charge')" />
                    <x-text-input :id="$idPrefix.'amount'" name="amount" type="number" step="0.01" min="0.01" class="mt-2" :value="old('amount', $transaction->amount)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                </div>
            @else
                <fieldset x-show="! appointmentId" x-bind:disabled="Boolean(appointmentId)">
                    <x-input-label :for="$idPrefix.'amount'" :value="__('Total charge')" />
                    <x-text-input :id="$idPrefix.'amount'" name="amount" type="number" step="0.01" min="0.01" class="mt-2" :value="old('amount', $transaction->amount)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                </fieldset>
                <div x-show="appointmentId" x-cloak>
                    <x-input-label :for="$idPrefix.'quoted_amount_display'" :value="__('Quoted appointment charge')" />
                    <input :id="$idPrefix.'quoted_amount_display'" type="text" class="casa-input mt-2" x-bind:value="appointmentCharges[appointmentId] ? `PHP ${Number(appointmentCharges[appointmentId]).toFixed(2)}` : '{{ __('Set from appointment') }}'" readonly>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('The server uses the appointment’s saved quote; no duplicate amount entry is needed.') }}</p>
                </div>
            @endif

            @unless ($isUpdate)
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <x-input-label :for="$idPrefix.'payment_amount'" :value="__('Payment received now')" />
                        <x-text-input :id="$idPrefix.'payment_amount'" name="payment_amount" type="number" step="0.01" min="0.01" class="mt-2" :value="old('payment_amount')" />
                        <p class="mt-2 text-sm text-casa-muted">{{ __('Leave blank to create an unpaid charge.') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->get('payment_amount')" />
                    </div>
                    <div>
                        <x-input-label :for="$idPrefix.'payment_method'" :value="__('Payment method')" />
                        <select :id="$idPrefix.'payment_method'" name="payment_method" class="casa-input mt-2">
                            <option value="">{{ __('Select when paid') }}</option>
                            @foreach (\App\Models\Transaction::PAYMENT_METHODS as $option)
                                <option value="{{ $option }}" @selected(old('payment_method') === $option)>{{ $option }}</option>
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
            @endunless

            @if ($isUpdate)
                <div>
                    <x-input-label :for="$idPrefix.'reason'" :value="__('Correction reason')" />
                    <textarea :id="$idPrefix.'reason'" name="reason" rows="3" class="casa-input mt-2" required>{{ old('reason') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                </div>
            @endif

            <div>
                <x-input-label :for="$idPrefix.'notes'" :value="__('Notes')" />
                <textarea :id="$idPrefix.'notes'" name="notes" rows="4" class="casa-input mt-2">{{ old('notes', $transaction->notes) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ $isUpdate ? __('Audited correction') : __('Manual payment') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ $isUpdate
                    ? __('Corrections preserve the previous values in the adjustment history. Payments use the separate Record payment action.')
                    : __('Use this for cash, GCash, bank transfer, or another payment received outside the system.') }}
            </p>
        </x-app-card>
        <x-form-actions :submit-label="$submitLabel" :modal-name="$modalName" :cancel-url="route('admin.transactions.index')" />
    </aside>
</x-form-shell>
