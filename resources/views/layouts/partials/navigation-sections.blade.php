@php
    $closeOnClick = $closeOnClick ?? false;
    $sections = [...$navGroups, ['label' => 'Account', 'items' => $accountLinks]];
@endphp

<div class="space-y-5">
    @foreach ($sections as $section)
        <section aria-label="{{ $section['label'] }} navigation">
            <p class="px-3 text-sm font-extrabold uppercase tracking-[0.17em] text-white/60">{{ $section['label'] }}</p>
            <div class="mt-2 space-y-1">
                @foreach ($section['items'] as $item)
                    @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                    <a href="{{ route($item['route']) }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($closeOnClick) x-on:click="closeDrawer(false)" @endif>
                        <x-nav-icon :name="$item['icon']" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
