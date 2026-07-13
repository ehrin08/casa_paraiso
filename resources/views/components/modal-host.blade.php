<div data-panel-host data-turbo="false" class="casa-panel-host" aria-hidden="true">
    <div data-panel-backdrop class="casa-panel-backdrop"></div>

    <section
        class="casa-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="casa-panel-title"
        tabindex="-1"
    >
        <div class="casa-panel__bar">
            <div class="min-w-0">
                <p class="casa-section-label">{{ __('Workspace panel') }}</p>
                <h2 id="casa-panel-title" data-panel-title class="truncate font-display text-lg font-black text-casa-ink">
                    {{ __('Loading') }}
                </h2>
            </div>

            <button type="button" data-panel-close class="casa-icon-button" aria-label="{{ __('Close panel') }}">
                <x-nav-icon name="close" />
            </button>
        </div>

        <div data-panel-status class="casa-panel__status">
            {{ __('Loading workspace') }}
        </div>

        <div data-panel-content class="casa-panel__content"></div>
    </section>
</div>
