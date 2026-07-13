@props([
    'customers',
    'selectedId' => null,
    'idPrefix' => '',
])

@php $customerSelectId = $idPrefix.'customer_profile_id'; @endphp

<div>
    <x-input-label :for="$customerSelectId" :value="__('Customer')" />
    <select :id="$customerSelectId" name="customer_profile_id" class="casa-input mt-2" required>
        <option value="">{{ __('Select customer') }}</option>
        @foreach ($customers as $customer)
            <option value="{{ $customer->id }}" @selected((int) old('customer_profile_id', $selectedId) === $customer->id)>
                {{ $customer->user->name }} ({{ $customer->customer_code }})
            </option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get('customer_profile_id')" />
</div>
