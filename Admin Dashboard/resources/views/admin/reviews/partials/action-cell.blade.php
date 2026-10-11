<div class="d-flex justify-content-center">
    @if($canDelete && empty($systemSettings['demoMode']))
        <button
            type="button"
            class="btn btn-outline-danger btn-icon delete-admin-review"
            data-delete-url="{{ $deleteUrl }}"
            data-delete-title="{{ __('labels.delete_review_confirmation') }}"
            title="{{ __('labels.delete') }}"
        >
            <i class="ti ti-trash"></i>
        </button>
    @else
        <span class="text-secondary">-</span>
    @endif
</div>
