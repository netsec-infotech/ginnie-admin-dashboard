
<div class="modal modal-blur fade" id="adminAddNoteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="adminAddNoteForm">
            @csrf
            <div class="modal-content">
                <div class="modal-status bg-purple"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body py-4">
                    <h3 class="mb-2">
                        <i class="ti ti-note me-1"></i>{{ __('labels.add_note') }}
                    </h3>
                    <p class="text-secondary mb-3">
                        {{ __('labels.add_note_help_text') }}
                    </p>
                    <div class="mb-1">
                        <label class="form-label required" for="adminNoteBody">{{ __('labels.note') }}</label>
                        <textarea class="form-control"
                                  name="note"
                                  id="adminNoteBody"
                                  rows="4"
                                  maxlength="2000"
                                  required
                                  placeholder="{{ __('labels.add_note_placeholder') }}"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        {{ __('labels.close') }}
                    </button>
                    <button type="submit" class="btn btn-primary" id="adminAddNoteSubmit">
                        <i class="ti ti-plus me-1"></i>{{ __('labels.add_note') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
