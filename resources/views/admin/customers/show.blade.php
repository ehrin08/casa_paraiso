<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $customer->user->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $customer->customer_code }} · {{ $customer->user->email }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.appointments.index', ['customer_profile_id' => $customer->id]) }}" class="casa-button-primary">{{ __('Add appointment') }}</a>
            <a href="{{ route('admin.customers.index') }}" class="casa-button-secondary">{{ __('All customers') }}</a>
        </div>
    </x-slot>

    @php $customerNotesModal = 'admin-customer-notes-'.$customer->id; @endphp

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-4">
            <x-metric-card label="Appointments" :value="$customer->appointments_count" meta="Booking records" tone="brown" />
            <x-metric-card label="Transactions" :value="$customer->transactions_count" meta="Payment records" tone="green" />
            <x-metric-card label="Feedback" :value="$customer->feedback_count" meta="Submitted reviews" tone="gold" />
            <x-metric-card label="Promos" :value="$customer->promotion_suggestions_count" meta="RFM suggestions" tone="charcoal" />
        </section>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                @include('customers.partials.contact-card', ['title' => __('Contact details'), 'showAdminDetails' => true])

                @include('customers.partials.history-table', ['title' => __('Appointments'), 'records' => $customer->appointments, 'type' => 'appointments'])
                @include('customers.partials.history-table', ['title' => __('Transactions'), 'records' => $customer->transactions, 'type' => 'transactions'])
                @include('customers.partials.history-table', ['title' => __('Feedback'), 'records' => $customer->feedback, 'type' => 'feedback'])
                @include('customers.partials.history-table', ['title' => __('Promotion suggestions'), 'records' => $customer->promotionSuggestions, 'type' => 'promotions'])
            </div>

            <aside class="space-y-4">
                <x-app-card>
                    <p class="casa-section-label">{{ __('Care notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Internal note') }}</h2>
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $customer->notes ?: __('No internal note yet.') }}</p>
                    <button type="button" class="mt-5 casa-button-primary w-full" x-data="" x-on:click="$dispatch('open-modal', '{{ $customerNotesModal }}')">{{ __('Update notes') }}</button>
                </x-app-card>
            </aside>
        </section>
    </div>

    <x-modal :name="$customerNotesModal" :show="old('_modal') === $customerNotesModal" :label="__('Update care notes for :name', ['name' => $customer->user->name])" maxWidth="2xl" focusable>
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="casa-modal-form p-6">@csrf @method('PATCH')
            <input type="hidden" name="_modal" value="{{ $customerNotesModal }}"><p class="casa-section-label">{{ __('Care notes') }}</p><h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Update internal note') }}</h2><x-input-label :for="$customerNotesModal.'-notes'" :value="__('Internal note')" class="sr-only" /><textarea :id="$customerNotesModal.'-notes'" name="notes" rows="8" class="casa-input mt-5">{{ old('notes', $customer->notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('notes')" /><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end" data-modal-actions><button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $customerNotesModal }}')">{{ __('Cancel') }}</button><button type="submit" class="casa-button-primary">{{ __('Save notes') }}</button></div>
        </form>
    </x-modal>
</x-app-layout>
