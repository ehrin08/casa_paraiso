<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Staff appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
            </p>
        </div>

        <a href="{{ route('staff.appointments.index') }}" class="casa-button-secondary">{{ __('My schedule') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Details') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Service schedule') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    @include('appointments.partials.schedule-summary', [
                        'scheduledFallback' => __('Not confirmed'),
                        'fourthLabel' => __('Customer phone'),
                        'fourthValue' => $appointment->customerProfile?->user?->phone ?: __('Not set'),
                    ])
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Therapist preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-ink">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Customer notes') }}</h2>
                </div>
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No customer notes.') }}</p>
            </x-app-card>
        </section>

        <aside>
            <x-app-card>
                <p class="casa-section-label">{{ __('Customer') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ $appointment->customerProfile?->user?->name }}</h2>
                @if ($appointment->customerProfile)
                    <a href="{{ route('staff.customers.show', $appointment->customerProfile) }}" class="mt-5 casa-button-secondary w-full">{{ __('Open customer') }}</a>
                @endif
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
