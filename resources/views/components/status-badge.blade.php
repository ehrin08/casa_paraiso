@props(['tone' => null, 'status' => null])

@php
    $normalizedStatus = str($status ?? '')->lower()->replace(' ', '_')->toString();
    $resolvedTone = $tone ?? match ($normalizedStatus) {
        'completed', 'paid', 'positive', 'active', 'available', 'bookable', 'sent' => 'success',
        'confirmed', 'information', 'info' => 'information',
        'partial', 'unpaid', 'neutral', 'not_bookable' => 'warning',
        'cancelled', 'no_show', 'void', 'refunded', 'negative', 'inactive', 'unavailable' => 'danger',
        default => 'neutral',
    };
    $classes = match ($resolvedTone) {
        'success' => 'border-casa-success/35 bg-casa-success/12 text-casa-success',
        'warning' => 'border-casa-warning/35 bg-casa-warning/12 text-casa-warning',
        'danger' => 'border-casa-danger/35 bg-casa-danger/10 text-casa-danger',
        'information' => 'border-casa-info/35 bg-casa-info/10 text-casa-info',
        'dark' => 'border-casa-charcoal/20 bg-casa-charcoal/10 text-casa-charcoal',
        default => 'border-casa-border bg-casa-sand/55 text-casa-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'casa-badge '.$classes]) }}>
    {{ $slot }}
</span>
