@php $modalName = $modalName ?? null; $idPrefix = $modalName ? $modalName.'-' : ''; @endphp
<form method="POST" action="{{ route('customer.feedback.store') }}" x-on:feedback-appointment-selected.window="$el.querySelector('[name=appointment_id]').value = $event.detail.id" @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])>
    @csrf
    @if ($modalName)<input type="hidden" name="_modal" value="{{ $modalName }}">@endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Review') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-ink">{{ __('Service feedback') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label :for="$idPrefix.'appointment_id'" :value="__('Completed appointment')" />
                <select :id="$idPrefix.'appointment_id'" name="appointment_id" class="casa-input mt-2" required>
                    <option value="">{{ __('Select appointment') }}</option>
                    @foreach ($appointments as $appointment)
                        <option value="{{ $appointment->id }}" @selected((int) old('appointment_id', $selectedAppointmentId) === $appointment->id)>
                            {{ $appointment->appointment_number }} - {{ $appointment->service?->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('appointment_id')" />
            </div>

            <div>
                <x-input-label :for="$idPrefix.'rating'" :value="__('Rating')" />
                <select :id="$idPrefix.'rating'" name="rating" class="casa-input mt-2" required>
                    @for ($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" @selected((int) old('rating', 5) === $rating)>{{ $rating }} / 5</option>
                    @endfor
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('rating')" />
            </div>

            <div>
                <x-input-label :for="$idPrefix.'comment'" :value="__('Comment')" />
                <textarea :id="$idPrefix.'comment'" name="comment" rows="6" class="casa-input mt-2">{{ old('comment') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('comment')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Sentiment') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('The system assigns a simple positive, neutral, or negative label from the rating and comment keywords.') }}</p>
        </x-app-card>
        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full" @disabled($appointments->isEmpty())>{{ __('Submit feedback') }}</button>
                @if ($appointments->isEmpty())
                    <p class="text-sm leading-6 text-casa-muted">{{ __('Complete an appointment before sharing feedback.') }}</p>
                @endif
                @if ($modalName)<button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>@else<a href="{{ route('customer.feedback.index') }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>@endif
            </div>
        </x-app-card>
    </aside>
</form>
