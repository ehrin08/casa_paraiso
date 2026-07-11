<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('Request an appointment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Choose the care you need and a time that feels right. Our team will review the request before your visit is final.') }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary">{{ __('Back to appointments') }}</a>
    </x-slot>

    @include('customer.appointments.partials.form')
</x-app-layout>
