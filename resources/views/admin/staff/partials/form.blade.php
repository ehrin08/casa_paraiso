@php $modalName = $modalName ?? null; @endphp
<form method="POST" action="{{ $action }}" @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($modalName)<input type="hidden" name="_modal" value="{{ $modalName }}">@endif

    <div class="space-y-6">
        <x-app-card data-modal-actions>
            <div class="border-b border-casa-border pb-5">
                <p class="casa-section-label">{{ __('Account') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Login details') }}</h2>
            </div>

            <div class="mt-5 grid gap-5">
                <div>
                    <x-input-label for="name" :value="__('Full name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-2" :value="old('name', $staffUser->name)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-2" :value="old('email', $staffUser->email)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Phone')" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-2" :value="old('phone', $staffUser->phone)" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="password" :value="$method === 'POST' ? __('Temporary password') : __('New password')" />
                    <x-text-input id="password" name="password" type="password" class="mt-2" :required="$method === 'POST'" autocomplete="new-password" />
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ $passwordHelp }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('password')" />
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staffUser->is_active)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                    <span>
                        <span class="block text-sm font-bold text-casa-text">{{ __('Active login account') }}</span>
                        <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Inactive staff cannot sign in or access protected staff workspaces.') }}</span>
                    </span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
            </div>
        </x-app-card>

        <x-app-card>
            <div class="border-b border-casa-border pb-5">
                <p class="casa-section-label">{{ __('Profile') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Operational details') }}</h2>
            </div>

            <div class="mt-5 grid gap-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="position" :value="__('Position')" />
                        <x-text-input id="position" name="position" type="text" class="mt-2" :value="old('position', $staffProfile->position)" />
                        <x-input-error class="mt-2" :messages="$errors->get('position')" />
                    </div>

                    <div>
                        <x-input-label for="hire_date" :value="__('Hire date')" />
                        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-2" :value="old('hire_date', $staffProfile->hire_date?->format('Y-m-d'))" />
                        <x-input-error class="mt-2" :messages="$errors->get('hire_date')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="specialization" :value="__('Specialization')" />
                    <x-text-input id="specialization" name="specialization" type="text" class="mt-2" :value="old('specialization', $staffProfile->specialization)" />
                    <x-input-error class="mt-2" :messages="$errors->get('specialization')" />
                </div>

                <div>
                    <x-input-label for="bio" :value="__('Bio')" />
                    <textarea id="bio" name="bio" rows="5" class="casa-input mt-2">{{ old('bio', $staffProfile->bio) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('bio')" />
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                    <input type="hidden" name="is_bookable" value="0">
                    <input type="checkbox" name="is_bookable" value="1" @checked(old('is_bookable', $staffProfile->is_bookable)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                    <span>
                        <span class="block text-sm font-bold text-casa-text">{{ __('Bookable for appointments') }}</span>
                        <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Bookable staff can later be assigned schedules and confirmed appointments.') }}</span>
                    </span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('is_bookable')" />
            </div>
        </x-app-card>
    </div>

    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('Service eligibility') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Assigned treatments') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Only active services are assignable in this phase. These assignments will drive pending appointment visibility for staff.') }}
            </p>

            <div class="mt-5 space-y-3">
                @php
                    $selectedServiceIds = collect(old('service_ids', $assignedServiceIds))->map(fn ($id) => (int) $id)->all();
                @endphp
                @forelse ($services as $service)
                    <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServiceIds, true)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                        <span>
                            <span class="block text-sm font-bold text-casa-text">{{ $service->name }}</span>
                            <span class="mt-1 block text-sm leading-6 text-casa-muted">
                                {{ $service->duration_minutes }} {{ __('min') }} - PHP {{ number_format((float) $service->price, 2) }}
                            </span>
                        </span>
                    </label>
                @empty
                    <x-empty-state
                        title="{{ __('No active services') }}"
                        description="{{ __('Add or activate services before assigning staff eligibility.') }}"
                    />
                @endforelse
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('service_ids')" />
            <x-input-error class="mt-2" :messages="$errors->get('service_ids.*')" />
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                @if ($modalName)<button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>@else<a href="{{ route('admin.staff.index') }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>@endif
            </div>
        </x-app-card>
    </aside>
</form>
