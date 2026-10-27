<div class="d-flex justify-content-start align-items-center gap-1">
    <a
        href="{{ route('admin.languages.export', $language) }}"
        class="btn btn-icon btn-outline-secondary fs-2"
        data-bs-toggle="tooltip"
        title="{{ __('labels.export_labels_json') }}"
        aria-label="{{ __('labels.export_labels_json') }}"
    >
        <i class="ti ti-download"></i>
    </a>
    @if($editPermission)
        <a
            href="{{ route('admin.languages.edit', $language) }}"
            class="btn btn-icon btn-outline-primary fs-2"
            data-bs-toggle="tooltip"
            title="{{ __('labels.edit') }}"
            aria-label="{{ __('labels.edit') }}"
        >
            <i class="ti ti-edit"></i>
        </a>
        <button
            type="button"
            class="btn btn-icon {{ $language->is_active ? 'btn-outline-success' : 'btn-outline-danger' }} toggle-language fs-2"
            data-url="{{ route('admin.languages.toggle', $language) }}"
            data-active="{{ $language->is_active ? '1' : '0' }}"
            data-name="{{ $language->native_name }}"
            data-bs-toggle="tooltip"
            title="{{ $language->code === 'en' && $language->is_active ? __('labels.language_english_required') : ($language->is_default && $language->is_active ? __('labels.language_default_deactivate_forbidden') : ($language->is_active ? __('labels.deactivate') : __('labels.activate'))) }}"
            aria-label="{{ $language->is_active ? __('labels.deactivate') : __('labels.activate') }}"
            @disabled($language->is_default && $language->is_active)
        >
            <i class="ti {{ $language->is_active ? 'ti-toggle-right-filled' : 'ti-toggle-left' }}"></i>
        </button>
    @endif
    @if($deletePermission)
        <button
            type="button"
            class="btn btn-icon btn-outline-danger delete-language"
            data-id="{{ $language->id }}"
            data-url="{{ route('admin.languages.delete', $language) }}"
            data-bs-toggle="tooltip"
            title="{{ $language->code === 'en' ? __('labels.language_english_delete_forbidden') : ($language->is_default ? __('labels.language_default_delete_forbidden') : __('labels.delete')) }}"
            aria-label="{{ __('labels.delete') }}"
            @disabled($language->is_default || $language->code === 'en')
        >
            <i class="ti ti-trash"></i>
        </button>
    @endif
</div>

