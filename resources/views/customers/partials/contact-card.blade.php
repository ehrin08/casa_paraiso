<x-app-card>
    <div class="border-b border-casa-border pb-5">
        <p class="casa-section-label">{{ __('Profile') }}</p>
        <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ $title }}</h2>
    </div>
    <dl class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl bg-casa-bg p-4">
            <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Phone') }}</dt>
            <dd class="mt-2 font-semibold text-casa-ink">{{ $customer->user->phone ?: __('Not set') }}</dd>
        </div>
        <div class="rounded-2xl bg-casa-bg p-4">
            <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Preference') }}</dt>
            <dd class="mt-2 font-semibold text-casa-ink">{{ $customer->contact_preference ?: __('Not set') }}</dd>
        </div>
        @if ($showAdminDetails ?? false)
            <div class="rounded-2xl bg-casa-bg p-4">
                <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Birth date') }}</dt>
                <dd class="mt-2 font-semibold text-casa-ink">{{ $customer->birth_date?->format('M d, Y') ?: __('Not set') }}</dd>
            </div>
            <div class="rounded-2xl bg-casa-bg p-4">
                <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('First visit') }}</dt>
                <dd class="mt-2 font-semibold text-casa-ink">{{ $customer->first_visit_at?->format('M d, Y') ?: __('Not yet') }}</dd>
            </div>
        @endif
        <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
            <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Address') }}</dt>
            <dd class="mt-2 text-sm leading-6 text-casa-muted">{{ $customer->address ?: __('No address on file.') }}</dd>
        </div>
    </dl>
</x-app-card>
