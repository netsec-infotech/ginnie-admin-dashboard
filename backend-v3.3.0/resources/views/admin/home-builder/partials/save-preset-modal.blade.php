<div class="modal modal-blur fade" id="hbSavePresetModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="ti ti-bookmark text-primary fs-2"></i>
                    <span>Save Section as Custom Preset</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Preset Name</label>
                    <input type="text" id="hb-preset-name-input" class="form-control" placeholder="e.g. Summer Mega Sale Grid" autocomplete="off">
                </div>
                <div class="mb-2">
                    <label class="form-label">Description (Optional)</label>
                    <textarea id="hb-preset-desc-input" class="form-control" rows="2" placeholder="Brief note describing this custom layout configuration..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="hb-save-preset-confirm-btn" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>Save Preset
                </button>
            </div>
        </div>
    </div>
</div>
