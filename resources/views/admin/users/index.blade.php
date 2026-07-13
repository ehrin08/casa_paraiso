<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">Protected administration</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-ink">User access</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">Pre-authorize team emails and assign their workspace before team members sign in.</p>
        </div>
    </x-slot>

    @include('admin.shared.eligibility-conflicts', [
        'message' => __('Resolve these confirmed appointments before changing staff access'),
        'class' => 'mb-5',
    ])

    <div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <x-app-card>
            <p class="casa-section-label">Add access</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">Pre-authorize a user</h2>
            @php $duplicateCustomerWarnings = collect(session('duplicateCustomerWarnings', [])); @endphp
            <form method="post" action="{{ route('admin.users.store') }}" class="mt-5 space-y-4" x-data="{ createRole: @js(old('role', $creatableRoles[0] ?? 'customer')) }">
                @csrf
                <div><x-input-label for="new-name" value="Full name"/><x-text-input id="new-name" name="name" class="mt-2" :value="old('name')" required/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
                <div><x-input-label for="new-email" value="Sign-in email"/><x-text-input id="new-email" name="email" type="email" class="mt-2" :value="old('email')" required/><x-input-error class="mt-2" :messages="$errors->get('email')"/></div>
                <div><x-input-label for="new-phone" value="Phone (optional)"/><x-text-input id="new-phone" name="phone" type="tel" class="mt-2" :value="old('phone')" autocomplete="tel"/><x-input-error class="mt-2" :messages="$errors->get('phone')"/></div>
                <div><x-input-label for="new-role" value="Workspace"/><select id="new-role" name="role" class="casa-input mt-2" x-model="createRole" required>@foreach($creatableRoles as $role)<option value="{{ $role }}" @selected(old('role') === $role)>{{ str($role)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                @if ($duplicateCustomerWarnings->isNotEmpty())
                    <div class="rounded-2xl border border-casa-warning/40 bg-casa-warning/10 p-4" x-show="createRole === 'customer'">
                        <p class="font-extrabold text-casa-warning">{{ __('Possible customer match') }}</p>
                        <ul class="mt-3 space-y-2 text-sm leading-6 text-casa-muted">
                            @foreach ($duplicateCustomerWarnings as $warning)
                                <li>
                                    <strong class="text-casa-ink">{{ $warning['name'] }}</strong>
                                    · {{ $warning['customer_code'] }}
                                    · {{ collect($warning['match_types'])->join(', ') }}
                                </li>
                            @endforeach
                        </ul>
                        <label class="mt-4 flex min-h-11 items-start gap-3 text-sm font-semibold text-casa-ink">
                            <input type="checkbox" name="duplicate_reviewed" value="1" @checked(old('duplicate_reviewed')) class="mt-1 rounded border-casa-control-border text-casa-palm focus:ring-casa-palm-dark">
                            <span>{{ __('I reviewed the possible match and intend to create a separate customer') }}</span>
                        </label>
                        <x-input-error class="mt-2" :messages="$errors->get('duplicate_reviewed')"/>
                    </div>
                @endif
                <label class="flex min-h-11 items-center gap-3 text-sm font-semibold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', true)) class="rounded border-casa-control-border text-casa-palm focus:ring-casa-palm-dark"> Active account</label>
                <x-primary-button class="w-full justify-center">Pre-authorize email</x-primary-button>
            </form>
        </x-app-card>

        <div class="space-y-3">
            @foreach($users as $managedUser)
                <form method="post" action="{{ route('admin.users.update', $managedUser) }}" class="casa-card grid gap-4 p-4 lg:grid-cols-[minmax(10rem,1fr)_minmax(13rem,1fr)_10rem_8rem_auto] lg:items-end">
                    @csrf @method('put')
                    <div><p class="casa-label">{{ __('Name') }}</p><p class="mt-2 min-h-11 content-center font-semibold text-casa-ink">{{ $managedUser->name }}</p></div>
                    <div>
                        <x-input-label :for="'email-'.$managedUser->id" value="Sign-in email"/>
                        <x-text-input :id="'email-'.$managedUser->id" name="email" type="email" class="mt-1" :value="$managedUser->email" :disabled="$managedUser->isSuperAdmin() || filled($managedUser->google_id)" required/>
                        @if (filled($managedUser->google_id) && ! $managedUser->isSuperAdmin())
                            <input type="hidden" name="email" value="{{ $managedUser->email }}">
                            <p class="mt-1 text-sm leading-5 text-casa-muted">{{ __('Linked emails are updated only through Google sign-in.') }}</p>
                        @endif
                    </div>
                    <div><x-input-label :for="'role-'.$managedUser->id" value="Role"/><select :id="'role-'.$managedUser->id" name="role" class="casa-input mt-1" @disabled($managedUser->isSuperAdmin())>@if($managedUser->isSuperAdmin())<option value="super_admin">Super admin</option>@else @foreach($managedUser->isStaff() ? $assignableRoles : $creatableRoles as $role)<option value="{{ $role }}" @selected($managedUser->role === $role)>{{ str($role)->title() }}</option>@endforeach @endif</select></div>
                    <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($managedUser->is_active) @disabled($managedUser->isSuperAdmin()) class="rounded border-casa-border text-casa-palm focus:ring-casa-brass"> Active</label>
                    <div class="flex gap-2">@if(!$managedUser->isSuperAdmin())<x-primary-button>Save</x-primary-button>@else<span class="rounded-full bg-casa-sand px-3 py-2 text-sm font-extrabold text-casa-cacao">Protected</span>@endif @if($managedUser->isStaff() && $managedUser->staffProfile)<a class="casa-button-secondary" href="{{ route('admin.staff.edit', $managedUser->staffProfile) }}">Staff profile</a>@endif</div>
                </form>
            @endforeach
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
