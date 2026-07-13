<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Promotion suggestion') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">{{ $suggestion->customerProfile?->user?->name }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $suggestion->rfmSegment?->name ?: __('Unsegmented') }}</p>
        </div>

        <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('All promotions') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-metric-card label="Recency" value="{{ $suggestion->recency_days ?? 'N/A' }}" meta="Days since paid visit" tone="gold" />
                    <x-metric-card label="Frequency" :value="$suggestion->frequency_count ?? 0" meta="Paid completed visits" tone="green" />
                    <x-metric-card label="Monetary" value="PHP {{ number_format((float) $suggestion->monetary_total, 2) }}" meta="Paid completed total" tone="brown" />
                </div>

                <div class="mt-6 rounded-2xl bg-casa-bg p-5">
                    <p class="casa-section-label">{{ __('Suggested offer') }}</p>
                    <p class="mt-3 text-lg font-bold text-casa-ink">{{ $suggestion->suggested_offer }}</p>
                    @if ($suggestion->notes)
                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $suggestion->notes }}</p>
                    @endif
                </div>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Review status') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ ucfirst($suggestion->status) }}</h2>
                <form method="POST" action="{{ route('admin.promotions.update', $suggestion) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="casa-input" required>
                        @foreach (\App\Models\PromotionSuggestion::STATUSES as $option)
                            <option value="{{ $option }}" @selected(old('status', $suggestion->status) === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <textarea name="notes" rows="5" class="casa-input" placeholder="{{ __('Review notes') }}">{{ old('notes', $suggestion->notes) }}</textarea>
                    <x-input-error :messages="$errors->all()" />
                    <button type="submit" class="casa-button-primary w-full">{{ __('Save review') }}</button>
                </form>
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Audit') }}</p>
                <div class="mt-4 space-y-2 text-sm text-casa-muted">
                    <p>{{ __('Created') }}: {{ $suggestion->created_at?->format('M d, Y g:i A') }}</p>
                    <p>{{ __('Reviewed by') }}: {{ $suggestion->reviewer?->name ?: __('Not reviewed') }}</p>
                    <p>{{ __('Applied') }}: {{ $suggestion->applied_at?->format('M d, Y') ?: __('No') }}</p>
                    <p>{{ __('Dismissed') }}: {{ $suggestion->dismissed_at?->format('M d, Y') ?: __('No') }}</p>
                </div>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
