<div class="modal modal-blur fade" id="confirm-update-modal" tabindex="-1" aria-labelledby="confirm-update-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirm-update-title">{{ __('Ready to update') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p>{{ __('Version') }} <strong data-update-version></strong> {{ __('has been checked and is ready for your review.') }}</p>
                <div class="alert alert-warning" role="alert">{{ __('Your website will briefly enter maintenance mode while the update is applied. If recovery is needed, it will stay in maintenance until the issue is resolved.') }}</div>
                <p data-vendor-warning hidden>{{ __('This update includes prepared system packages. They will be installed automatically. You do not need to run Composer.') }}</p>
                <div class="alert alert-warning" data-platform-warning hidden role="alert">
                    <strong>{{ __('Some server requirements are unavailable.') }}</strong>
                    <ul class="mb-2 mt-2" data-platform-warning-list></ul>
                    <div>{{ __('The updater will install the prepared packages directly. Features that need the missing extension may remain unavailable until your hosting provider enables it.') }}</div>
                </div>
                <p data-database-warning hidden>{{ __('The updater will back up the database before making changes. If database changes fail, recovery may require your hosting support.') }}</p>
                <p class="text-secondary mb-0">{{ __('Keep this page open to follow progress. Save the private update log link shown after confirmation; it works even during maintenance.') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" data-confirm-update>{{ __('Confirm update') }}</button>
            </div>
        </div>
    </div>
</div>
