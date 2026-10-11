<?php

namespace App\Services\BulkUpload;

/**
 * Per-module configuration for the bulk upload pipeline.
 * One instance per module registered with BulkUploadModuleRegistry — the
 * single source of truth for which model + importer + UI labels to use.
 */
class BulkUploadModuleConfig
{
    public function __construct(
        /** Short slug used in URLs and route names (e.g. 'brands'). */
        public readonly string $key,
        /** Fully-qualified model class. */
        public readonly string $modelClass,
        /** Fully-qualified BulkImportable implementation. */
        public readonly string $importerClass,
        /** Translation key for the display name (e.g. 'labels.brands'). */
        public readonly string $displayNameKey,
        /** Existing route name that points back to the module's main listing. */
        public readonly string $indexRouteName,
        /** Menu key used by admin layout for sidebar highlighting. */
        public readonly string $menuKey,
    ) {}
}
