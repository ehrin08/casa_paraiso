@props([
    'action',
    'sort',
    'direction',
    'search',
    'searchPlaceholder',
    'searchLabel',
])

<form method="GET" action="{{ $action }}" {{ $attributes->class(['casa-filter-grid']) }}>
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="direction" value="{{ $direction }}">
    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchLabel }}">
    {{ $slot }}
    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
</form>
