@props([
    'value',
    'label' => null,
])

<select name="sentiment_label" class="casa-input" @if($label) aria-label="{{ $label }}" @endif>
    <option value="">{{ __('All sentiment') }}</option>
    @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
        <option value="{{ $option }}" @selected($value === $option)>{{ ucfirst($option) }}</option>
    @endforeach
</select>
