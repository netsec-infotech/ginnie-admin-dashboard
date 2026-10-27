@props([
    'tableId',
    'deleteUrl' => null,
    'exportUrl' => null,
    'exportColumnsMode' => 'visible',
    'itemLabel' => __('labels.items'),
])

<div
    class="d-none mx-3 my-3 catalog-selection-toolbar bulk-delete-toolbar"
    data-table-id="{{ $tableId }}"
    @if($deleteUrl) data-bulk-delete-url="{{ $deleteUrl }}" @endif
    @if($exportUrl) data-export-url="{{ $exportUrl }}" @endif
    {{-- Products can opt into import-shaped exports by skipping visible DataTable columns. --}}
    data-export-columns-mode="{{ $exportColumnsMode }}"
    data-item-label="{{ $itemLabel }}"
>
    <div class="d-flex align-items-center gap-2 flex-wrap p-2 form-control rounded">
        <span class="text-muted small me-2">
            <span class="bulk-delete-selected-count">0</span> {{ $itemLabel }} {{ __('labels.selected') }}
        </span>
        @if($exportUrl)
            <button type="button" class="btn btn-sm btn-outline-primary catalog-export-selected">
                <i class="ti ti-download me-1"></i>{{ __('labels.export_selected') }}
            </button>
        @endif
        @if($deleteUrl)
        <button type="button" class="btn btn-sm btn-outline-danger bulk-delete-submit">
            <i class="ti ti-trash me-1"></i>{{ __('labels.delete_selected') }}
        </button>
        @endif
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto bulk-delete-deselect">
            {{ __('labels.deselect_all') }}
        </button>
    </div>
</div>
