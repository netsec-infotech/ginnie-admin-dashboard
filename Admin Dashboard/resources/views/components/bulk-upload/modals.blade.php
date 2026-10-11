@props([
    'module',
    'showExport' => true,
])

@php
    $exportFilters = [];
    if ($showExport) {
        try {
            $registry = app(App\Services\BulkUpload\BulkUploadModuleRegistry::class);
            $cfg = $registry->tryGet($module);
            if ($cfg) {
                $exportFilters = app($cfg->importerClass)->exportFilters();
            } else {
                $showExport = false;
            }
        } catch (\Throwable $e) {
            $showExport = false;
        }
    }
@endphp

{{--
    Document-root modal slot. Drop this once per page, OUTSIDE any nested
    container (dropdowns, tables, modals). All bulk-upload-related modals
    for the module get rendered here so Bootstrap can find them by ID.
--}}
@if($showExport)
    <x-bulk-upload.export-modal :module="$module" :filters="$exportFilters"/>
@endif
