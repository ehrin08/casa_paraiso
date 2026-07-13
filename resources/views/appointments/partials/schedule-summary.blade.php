<div class="rounded-2xl bg-casa-bg p-4">
    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Scheduled') }}</dt>
    <dd class="mt-2 font-semibold text-casa-ink">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') ?: $scheduledFallback }}</dd>
</div>
<div class="rounded-2xl bg-casa-bg p-4">
    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
    <dd class="mt-2"><x-status-badge :status="$appointment->status">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
</div>
<div class="rounded-2xl bg-casa-bg p-4">
    <dt class="text-sm font-black uppercase tracking-[0.12em] text-casa-muted">{{ $fourthLabel }}</dt>
    <dd class="mt-2 font-semibold text-casa-ink">{{ $fourthValue }}</dd>
</div>
