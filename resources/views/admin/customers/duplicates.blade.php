<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer records') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ __('Possible duplicate customers') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review shared names and phone numbers before creating another profile. No profiles are merged automatically.') }}
            </p>
        </div>
        <a href="{{ route('admin.customers.index') }}" class="casa-button-secondary">{{ __('Back to customers') }}</a>
    </x-slot>

    <div class="space-y-5">
        @forelse ($duplicateGroups as $group)
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ str($group['match_type'])->title() }} {{ __('match') }}</p>
                        <h2 class="mt-2 text-xl font-extrabold text-casa-ink">{{ $group['normalized_value'] }}</h2>
                    </div>
                    <x-status-badge tone="warning">{{ trans_choice(':count profile|:count profiles', count($group['customers'])) }}</x-status-badge>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    @foreach ($group['customers'] as $customer)
                        <article class="rounded-2xl border border-casa-control-border bg-casa-sand/35 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <h3 class="font-extrabold text-casa-ink">{{ $customer['name'] }}</h3>
                                    <p class="mt-1 text-sm text-casa-muted">{{ $customer['customer_code'] }} · {{ $customer['email'] }}</p>
                                    <p class="mt-1 text-sm text-casa-muted">{{ $customer['phone'] ?: __('No phone') }}</p>
                                </div>
                                <a href="{{ route('admin.customers.show', $customer['id']) }}" class="casa-button-secondary shrink-0">{{ __('Open profile') }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-app-card>
        @empty
            <x-empty-state
                title="{{ __('No possible duplicates found') }}"
                description="{{ __('Customer names and phone numbers do not currently form any review groups.') }}"
            />
        @endforelse
    </div>
</x-app-layout>
