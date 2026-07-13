@props([
    'submitLabel',
    'modalName' => null,
    'cancelUrl',
])

<x-app-card>
    <div class="flex flex-col gap-3">
        <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
        @if ($modalName)
            <button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>
        @else
            <a href="{{ $cancelUrl }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
        @endif
    </div>
</x-app-card>
