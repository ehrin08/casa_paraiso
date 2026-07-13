@props([
    'value',
    'allLabel' => null,
    'label' => null,
])

<select name="status" class="casa-input" @if($label) aria-label="{{ $label }}" @endif>
    <option value="">{{ $allLabel ?: __('All statuses') }}</option>
    <option value="active" @selected($value === 'active')>{{ __('Active') }}</option>
    <option value="inactive" @selected($value === 'inactive')>{{ __('Inactive') }}</option>
</select>
