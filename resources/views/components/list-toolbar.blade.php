@props([
    'eyebrow' => null,
    'title' => null,
    'count' => null,
    'resetUrl' => null,
    'hasActiveFilters' => null,
    'defaultSort' => null,
    'defaultDirection' => null,
    'contextQueryKeys' => [],
])

@php
    $requestedSort = request()->query('sort');
    $requestedDirection = request()->query('direction');
    $hasNonDefaultSort = filled($requestedSort)
        && ($defaultSort === null || (string) $requestedSort !== (string) $defaultSort);
    $hasNonDefaultDirection = filled($requestedDirection)
        && ($defaultDirection === null || strtolower((string) $requestedDirection) !== strtolower((string) $defaultDirection));
    $hasActiveFilters ??= collect(request()->query())
        ->except(array_merge(['page', 'sort', 'direction'], (array) $contextQueryKeys))
        ->contains(fn ($value) => filled($value))
        || $hasNonDefaultSort
        || $hasNonDefaultDirection;
@endphp

<div {{ $attributes->merge(['class' => 'casa-list-toolbar']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="casa-section-label">{{ $eyebrow }}</p>
        @endif

        @if ($title)
            <h2 class="mt-2 text-xl font-extrabold text-casa-ink">{{ $title }}</h2>
        @endif

        @if ($count !== null || ($resetUrl && $hasActiveFilters))
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if ($count !== null)
                    <span class="casa-filter-chip">{{ trans_choice(':count record|:count records', (int) $count) }}</span>
                @endif

                @if ($resetUrl && $hasActiveFilters)
                    <a href="{{ $resetUrl }}" class="casa-filter-chip hover:border-casa-brass hover:text-casa-palm">
                        {{ __('Clear filters') }}
                    </a>
                @endif
            </div>
        @endif
    </div>

    <div class="w-full lg:w-auto">
        {{ $slot }}
    </div>
</div>
