<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ $page['eyebrow'] }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $page['title'] }}</h1>

            @isset($page['description'])
                <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ $page['description'] }}</p>
            @endisset
        </div>

        @isset($page['backUrl'])
            <a href="{{ $page['backUrl'] }}" class="casa-button-secondary">{{ $page['backLabel'] }}</a>
        @endisset
    </x-slot>

    @include($form['partial'], [
        'action' => $form['action'],
        'method' => $form['method'],
        'submitLabel' => $form['submitLabel'],
    ] + ($form['data'] ?? []))
</x-app-layout>
