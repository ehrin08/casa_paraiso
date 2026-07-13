@if (session('eligibility_conflicts'))
    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 {{ $class ?? '' }}" role="alert">
        <p class="text-sm font-extrabold text-red-800">{{ $message }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (session('eligibility_conflicts') as $conflict)
                <a href="{{ $conflict['url'] }}" class="inline-flex min-h-11 items-center rounded-full border border-red-200 bg-white px-3 py-2 text-sm font-extrabold text-red-800 hover:border-red-400">
                    {{ $conflict['number'] }} · {{ \Illuminate\Support\Carbon::parse($conflict['starts_at'])->format('M d, g:i A') }}
                </a>
            @endforeach
        </div>
    </div>
@endif
