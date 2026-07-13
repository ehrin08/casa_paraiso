<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $customer->user->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Operational context for appointment service and customer care.') }}
            </p>
        </div>

        <a href="{{ route('staff.customers.index') }}" class="casa-button-secondary">{{ __('Back to lookup') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            @include('customers.partials.contact-card', ['title' => __('Contact and care context')])

            @include('customers.partials.history-table', ['title' => __('Appointment history'), 'records' => $customer->appointments, 'type' => 'appointments', 'emptyDescription' => __('Related operational records will appear here.')])
            @include('customers.partials.history-table', ['title' => __('Feedback history'), 'records' => $customer->feedback, 'type' => 'feedback', 'emptyDescription' => __('Related operational records will appear here.')])
        </section>

        <aside class="space-y-4">
            <x-metric-card label="Appointments" :value="$customer->appointments_count" meta="Known visits" tone="brown" />
            <x-metric-card label="Feedback" :value="$customer->feedback_count" meta="Service reviews" tone="gold" />
            <x-app-card>
                <p class="casa-section-label">{{ __('Note') }}</p>
                <p class="mt-3 text-sm leading-6 text-casa-muted">
                    {{ __('Staff view is operational only. User account settings and internal admin controls stay in the admin workspace.') }}
                </p>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
