@php
    $user = Auth::user();
    $dashboardRoute = $user->homeRouteName();
    $isCustomer = $user->isCustomer();

    $roleLabel = match (true) {
        $user->isSuperAdmin() => 'Super admin workspace',
        $user->isAdmin() => 'Admin workspace',
        $user->isStaff() => 'Staff workspace',
        default => 'Customer lounge',
    };

    $navGroups = match (true) {
        $user->isAdmin() => [
            [
                'label' => 'Manage',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                    ['label' => 'Schedule', 'icon' => 'calendar', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*'],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
                    ['label' => 'Team', 'icon' => 'team', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*'],
                    ['label' => 'Services', 'icon' => 'services', 'route' => 'admin.services.index', 'active' => 'admin.services.*'],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'admin.transactions.index', 'active' => 'admin.transactions.*'],
                    ['label' => 'Insights', 'icon' => 'insights', 'route' => 'admin.promotions.index', 'active' => ['admin.promotions.*', 'admin.rfm-segments.*', 'admin.promotion-rules.*', 'admin.feedback.*', 'admin.reports.*']],
                ],
            ],
        ],
        $user->isStaff() => [
            [
                'label' => 'Today',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
                    ['label' => 'My Schedule', 'icon' => 'calendar', 'route' => 'staff.appointments.index', 'active' => 'staff.appointments.*'],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'staff.customers.index', 'active' => 'staff.customers.*'],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'staff.transactions.index', 'active' => 'staff.transactions.*'],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'staff.feedback.index', 'active' => 'staff.feedback.*'],
                ],
            ],
        ],
        default => [
            [
                'label' => 'My wellness',
                'items' => [
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'customer.appointments.index', 'active' => ['customer.appointments.index', 'customer.appointments.show', 'customer.appointments.create']],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'customer.feedback.index', 'active' => 'customer.feedback.*'],
                ],
            ],
        ],
    };

    $accountLinks = $user->isAdmin()
        ? [
            ...($user->isSuperAdmin() ? [['label' => 'User access', 'icon' => 'team', 'route' => 'admin.users.index', 'active' => 'admin.users.*']] : []),
            ['label' => 'Profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*'],
        ]
        : [
            ['label' => 'Profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*'],
        ];

    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<nav
    x-data="{
        open: false,
        opener: null,
        openDrawer(event) {
            this.opener = event.currentTarget;
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.mobileDrawer?.querySelector('a, button, input, select, textarea, [tabindex]')?.focus());
        },
        closeDrawer(restoreFocus = true) {
            this.open = false;
            document.body.style.overflow = '';
            if (restoreFocus) this.$nextTick(() => this.opener?.focus());
        },
        trapDrawerFocus(event) {
            const controls = [...this.$refs.mobileDrawer.querySelectorAll('a, button, input, select, textarea, [tabindex]')]
                .filter((control) => !control.disabled && control.tabIndex >= 0 && control.offsetParent !== null);
            if (!controls.length) return;
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    }"
    x-on:keydown.escape.window="if (open) closeDrawer()"
    data-role-navigation="{{ $user->role }}"
>
    <div class="sticky top-0 z-40 border-b border-casa-border/80 bg-casa-paper/94 px-4 py-2.5 backdrop-blur-xl lg:hidden">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route($dashboardRoute) }}" class="inline-flex min-h-11 min-w-0 items-center rounded-xl bg-white px-2 py-1">
                <x-application-logo class="origin-left scale-[0.72]" />
            </a>

            <div class="flex items-center gap-2">
                <span class="hidden text-right sm:block">
                    <span class="block text-sm font-extrabold uppercase tracking-[0.12em] text-casa-cacao">{{ $roleLabel }}</span>
                    <span class="block max-w-36 truncate text-sm font-semibold text-casa-muted">{{ $user->name }}</span>
                </span>
                @unless ($isCustomer)
                    <button type="button" class="casa-icon-button" aria-label="Open account navigation" aria-controls="mobile-workspace-navigation" x-bind:aria-expanded="open" @click="openDrawer($event)">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                @endunless
            </div>
        </div>
    </div>

    <aside data-desktop-sidebar class="casa-wood-panel fixed inset-y-0 start-0 z-30 hidden w-72 flex-col overflow-y-auto border-e border-white/10 p-4 lg:flex">
        <a href="{{ route($dashboardRoute) }}" class="rounded-2xl bg-casa-paper px-3 py-2.5 shadow-casa-card">
            <x-application-logo class="origin-left scale-[0.88]" />
        </a>

        <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.065] p-3.5">
            <div class="flex items-center gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full border border-casa-brass/35 bg-casa-brass/15 text-sm font-extrabold tracking-[0.08em] text-casa-sand">{{ $initials }}</span>
                <span class="min-w-0">
                    <span class="block text-sm font-extrabold uppercase tracking-[0.15em] text-casa-brass-light">{{ $roleLabel }}</span>
                    <span class="mt-1 block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                    <span class="mt-0.5 block truncate text-[0.7rem] text-white/65">{{ $user->email }}</span>
                </span>
            </div>
        </div>

        <div class="mt-5">
            @include('layouts.partials.navigation-sections')
        </div>

        <div class="mt-auto pt-6">
            <p class="mb-3 px-2 text-sm leading-5 text-white/65">{{ config('casa.business_hours.summary') }}<br><span class="font-bold text-white/80">{{ config('casa.business_hours.window') }}</span></p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/8 text-white hover:bg-white/14 hover:text-white">
                    Log out
                </button>
            </form>
        </div>
    </aside>

    @unless ($isCustomer)
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 lg:hidden" style="display: none;">
            <div class="absolute inset-0 bg-casa-charcoal/72 backdrop-blur-sm" @click="closeDrawer()"></div>
            <aside id="mobile-workspace-navigation" x-ref="mobileDrawer" x-show="open" x-on:keydown.tab="trapDrawerFocus($event)" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="casa-wood-panel absolute inset-y-0 start-0 flex w-[min(21rem,90vw)] flex-col overflow-y-auto p-4 shadow-casa-lift" aria-label="Mobile workspace navigation">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route($dashboardRoute) }}" class="rounded-xl bg-casa-paper px-2 py-1.5" @click="closeDrawer(false)">
                    <x-application-logo class="origin-left scale-[0.72]" />
                </a>
                <button type="button" class="casa-icon-button border-white/15 bg-white/10 text-white" aria-label="Close navigation" @click="closeDrawer()">
                    <x-nav-icon name="close" />
                </button>
            </div>

            <div class="mt-5 rounded-2xl border border-white/10 bg-white/[0.065] p-3.5">
                <p class="text-sm font-extrabold uppercase tracking-[0.16em] text-casa-brass-light">{{ $roleLabel }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                <p class="mt-0.5 truncate text-sm text-white/65">{{ $user->email }}</p>
            </div>

            <div class="mt-5">
                @include('layouts.partials.navigation-sections', ['closeOnClick' => true])
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-6" data-turbo="false" x-on:submit="closeDrawer(false)">
                @csrf
                <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">Log out</button>
            </form>
            </aside>
        </div>
    @endunless

    @if ($isCustomer)
        <div data-mobile-customer-navigation data-customer-mobile-dock class="casa-mobile-dock lg:hidden" aria-label="Customer navigation">
            <a href="{{ route('customer.appointments.index') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('customer.appointments.*')]) @if(request()->routeIs('customer.appointments.*')) aria-current="page" @endif>
                <x-nav-icon name="calendar" class="size-5" />
                <span>Appointments</span>
            </a>
            <a href="{{ route('customer.feedback.index') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('customer.feedback.*')]) @if(request()->routeIs('customer.feedback.*')) aria-current="page" @endif>
                <x-nav-icon name="feedback" class="size-5" />
                <span>Feedback</span>
            </a>
            <a href="{{ route('profile.edit') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('profile.*')]) @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                <x-nav-icon name="profile" class="size-5" />
                <span>Profile</span>
            </a>
        </div>
    @endif
</nav>
