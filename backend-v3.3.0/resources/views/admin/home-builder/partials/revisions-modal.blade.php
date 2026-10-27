<div class="modal modal-blur fade" id="hbRevisionsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="ti ti-history text-primary fs-2"></i>
                    <span>Layout Revision History</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3 text-muted fs-4">
                    Select a previous auto-save snapshot or saved layout revision to restore.
                </div>
                <div id="hb-revisions-list" class="d-flex flex-column gap-2">
                    {{-- Dynamically populated via JS --}}
                </div>
            </div>
        </div>
    </div>
</div>
