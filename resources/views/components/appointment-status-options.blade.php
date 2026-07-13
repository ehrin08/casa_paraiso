@props(['allLabel' => null, 'statuses' => \App\Models\Appointment::ACTIVE_STATUSES])

<option value="">{{ $allLabel ?: __('All statuses') }}</option>
@foreach ($statuses as $status)
    <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
@endforeach
