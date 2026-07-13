@props([
    'startTime' => null,
    'endTime' => null,
    'endsNextDay' => false,
    'timesRequired' => true,
    'midnightDescription' => null,
    'idPrefix' => '',
])

@php
    $startId = $idPrefix.'start_time';
    $endId = $idPrefix.'end_time';
    $endsNextDayId = $idPrefix.'ends_next_day';
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <x-input-label :for="$startId" :value="__('Start time')" />
        <x-text-input id="{{ $startId }}" name="start_time" type="time" class="mt-2" :value="old('start_time', $startTime ? substr((string) $startTime, 0, 5) : null)" @required($timesRequired) />
        <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
    </div>

    <div>
        <x-input-label :for="$endId" :value="__('End time')" />
        <x-text-input id="{{ $endId }}" name="end_time" type="time" class="mt-2" :value="old('end_time', $endTime ? substr((string) $endTime, 0, 5) : null)" @required($timesRequired) />
        <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
    </div>
</div>

<label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-sand/45 p-4">
    <input type="hidden" name="ends_next_day" value="0">
    <input :id="$endsNextDayId" type="checkbox" name="ends_next_day" value="1" @checked(old('ends_next_day', $endsNextDay)) class="mt-1 rounded border-casa-control-border text-casa-palm shadow-sm focus:ring-casa-palm-dark">
    <span>
        <span class="block text-sm font-bold text-casa-ink">{{ __('Ends at midnight') }}</span>
        <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ $midnightDescription }}</span>
    </span>
</label>
<x-input-error class="mt-2" :messages="$errors->get('ends_next_day')" />
