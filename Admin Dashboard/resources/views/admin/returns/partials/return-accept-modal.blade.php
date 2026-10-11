<!-- ADMIN ACCEPT MODAL -->
<div class="modal modal-blur fade" id="adminAcceptModel" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="modal-status bg-success"></div>
            <div class="modal-body text-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="icon mb-2 text-green icon-lg">
                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/>
                    <path d="M9 12l2 2l4 -4"/>
                </svg>
                <h3>{{ __('labels.approve_return') }}</h3>
                <div class="text-secondary">
                    {{ __('labels.admin_approve_return_confirm') }}
                </div>
                <div class="form-group mt-3 text-start">
                    <label for="admin_comment_accept" class="form-label">
                        {{ __('labels.admin_comment') }}
                        <small class="text-secondary">({{ __('labels.optional') }})</small>
                    </label>
                    <textarea class="form-control" id="admin_comment_accept" name="admin_comment" rows="3"
                              placeholder="{{ __('labels.add_your_comment_here') }}"></textarea>
                    <div class="form-text">{{ __('labels.comment_visible_to_seller') }}</div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <a href="#" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">{{ __('labels.cancel') }}</a>
                        </div>
                        <div class="col">
                            <a href="#" class="btn btn-success w-100" id="adminConfirmAccept" data-bs-dismiss="modal">{{ __('labels.approve') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
