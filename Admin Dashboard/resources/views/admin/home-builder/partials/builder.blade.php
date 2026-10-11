<div class="hb-root" id="home-builder-{{ $platform }}" data-home-builder data-platform="{{ $platform }}"
    data-show-url="{{ route('admin.home-builder.show') }}" data-save-url="{{ route('admin.home-builder.save') }}"
    data-section-media-url="{{ url('admin/home-builder/sections') }}"
    data-item-image-url="{{ url('admin/home-builder/items') }}" data-product-search="{{ url('admin/products/search') }}"
    data-category-search="{{ url('admin/categories/search') }}" data-brand-search="{{ url('admin/brands/search') }}"
    data-zone-search="{{ url('admin/delivery-zones/search') }}"
    data-global-appearance-url="{{ route('admin.home-builder.global-appearance.show') }}"
    data-market-search="{{ url('admin/delivery-zones/search') }}" data-can-edit="{{ $canEdit ? '1' : '0' }}"
    data-labels='@json($labels)' data-options='@json($options)'
    data-templates='@json($templates ?? [])' data-themes='@json($themes ?? [])'
    data-section-types='@json($options['section_types'])' data-categories='@json($categories)'>

    <div class="row g-2 align-items-center mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted">{{ __('labels.hb_scope') }}</span>
                <div class="form-selectgroup form-selectgroup-pills" data-role="scope">
                    <label class="form-selectgroup-item">
                        <input type="radio" name="hb-scope-type-{{ $platform }}" value="global"
                            class="form-selectgroup-input" checked>
                        <span class="form-selectgroup-label">{{ __('labels.hb_global') }}</span>
                    </label>
                    <label class="form-selectgroup-item">
                        <input type="radio" name="hb-scope-type-{{ $platform }}" value="category"
                            class="form-selectgroup-input">
                        <span class="form-selectgroup-label">{{ __('labels.hb_by_category') }}</span>
                    </label>
                    <label class="form-selectgroup-item">
                        <input type="radio" name="hb-scope-type-{{ $platform }}" value="zone"
                            class="form-selectgroup-input">
                        <span
                            class="form-selectgroup-label">{{ __('labels.hb_by_zone') !== 'labels.hb_by_zone' ? __('labels.hb_by_zone') : 'By Zone' }}</span>
                    </label>
                </div>
                <select data-role="scope-category" class="form-select d-none" style="width: 220px;"></select>
                <select data-role="scope-zone" class="form-select d-none" style="width: 220px;"></select>
                <span class="badge bg-secondary-lt ms-2" data-role="draft-badge" title="Saved state">Saved</span>
            </div>
        </div>
        <div class="col-auto d-flex align-items-center gap-2">
            <div class="btn-group" role="group">
                <button type="button" class="btn  btn-icon px-3" data-role="undo" disabled title="Undo (Ctrl+Z)">
                    <i class="ti ti-rotate-2 fs-2"></i>
                </button>
                <button type="button" class="btn  btn-icon px-3" data-role="redo" disabled title="Redo (Ctrl+Y)">
                    <i class="ti ti-rotate-clockwise-2 fs-2"></i>
                </button>
            </div>
            <button type="button" class="btn " data-role="open-revisions" title="Revision history snapshots">
                <i class="ti ti-history me-1"></i>Revisions
            </button>
            <button type="button" class="btn btn-outline-primary" data-role="open-templates">
                <i class="ti ti-palette me-1"></i>Browse Templates
            </button>
            @if (empty($systemSettings['demoMode']))
                <button type="button" class="btn btn-primary" data-role="save" @disabled(!$canEdit)>
                    <i class="ti ti-device-floppy me-1"></i>{{ __('labels.hb_save') }}
                </button>
            @endif
        </div>
    </div>

    <div class="hb-builder-workspace" data-role="builder-workspace">
        <div class="hb-builder-canvas" data-role="builder-canvas">
            <div class="hb-canvas-preview-wrapper" data-role="canvas-preview-wrapper">
                @include('admin.home-builder.partials.preview-drawer')
            </div>

            <div class="card hb-sections-card hb-structure-panel" data-role="structure-panel">
                <div class="card-header hb-structure-header" data-role="structure-drag-handle">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <i class="ti ti-grip-horizontal hb-structure-grip"></i>
                        <i class="ti ti-stack-2"></i>
                        <h3 class="card-title text-truncate m-0">{{ __('labels.hb_sections') }}</h3>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-xs btn-ghost-primary" data-role="open-templates"
                            title="Browse templates library">
                            <i class="ti ti-palette"></i>
                        </button>
                        <button type="button" class="btn btn-icon btn-sm btn-ghost-secondary"
                            data-role="structure-toggle" title="Toggle sections">
                            <i class="ti ti-chevron-up"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body hb-structure-body p-0 d-flex flex-column hb-sections-card-body"
                    data-role="structure-body">
                    <div data-role="sections" class="hb-sections p-2"></div>
                    <div class="hb-sections-footer p-2 border-top mt-auto bg-surface">
                        <div class="d-flex gap-2">
                            <div class="dropup flex-fill position-relative">
                                <button type="button" class="btn btn-outline-primary btn-sm w-100 dropdown-toggle"
                                    data-bs-toggle="dropdown" data-role="add-section" aria-expanded="false"
                                    @disabled(!$canEdit)>
                                    <i class="ti ti-plus me-1"></i>{{ __('labels.hb_add_section') }}
                                </button>
                                <div data-role="type-picker" class="dropdown-menu w-100 p-2 mb-2"></div>
                            </div>
                            <button type="button" class="btn  btn-sm" data-role="open-templates"
                                title="Browse section templates & themes" @disabled(!$canEdit)>
                                <i class="ti ti-palette"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="hb-builder-inspector" data-role="builder-inspector">
            <div class="card hb-editor-card">
                <div class="card-header d-flex align-items-center gap-2 py-3 px-3">
                    <i class="ti ti-adjustments-horizontal text-primary fs-2"></i>
                    <h3 class="card-title m-0 fw-bold">Design & Properties</h3>
                </div>
                <div class="card-body p-3" data-role="editor">
                    <div class="text-muted">{{ __('labels.hb_select_section') }}</div>
                </div>
            </div>
        </aside>
    </div>
</div>
