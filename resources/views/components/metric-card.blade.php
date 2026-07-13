@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'brown',
])

@php
    $accent = match ($tone) {
        'green' => 'bg-casa-green',
        'gold' => 'bg-casa-brass',
        'charcoal' => 'bg-casa-charcoal',
        default => 'bg-casa-cacao',
    };
@endphp

<div {{ $attributes->merge(['class' => 'casa-card-compact relative overflow-hidden p-5']) }}>
    <span class="absolute inset-y-0 start-0 w-1 {{ $accent }}"></span>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-extrabold uppercase tracking-[0.13em] text-casa-muted">{{ $label }}</p>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-casa-ink">{{ $value }}</p>
        </div>
        <span class="mt-1 size-2.5 rounded-full {{ $accent }} shadow-sm"></span>
    </div>

    @if ($meta)
        <p class="mt-3 text-sm font-semibold leading-5 text-casa-muted">{{ $meta }}</p>
    @endif
</div>
