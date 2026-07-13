@props([
    'action',
    'method' => 'POST',
    'modalName' => null,
])

<form method="POST" action="{{ $action }}" {{ $attributes }}>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($modalName)
        <input type="hidden" name="_modal" value="{{ $modalName }}">
    @endif

    {{ $slot }}
</form>
