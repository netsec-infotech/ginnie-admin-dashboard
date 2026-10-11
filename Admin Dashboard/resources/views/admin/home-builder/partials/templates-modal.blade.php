<div class="modal modal-blur fade hb-tmpl-modal" id="hbTemplatesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="ti ti-palette text-primary fs-2"></i>
                        <span>Template & Preset Library</span>
                    </h5>
                    <div class="hb-tmpl-header-subtitle">
                        Select pre-designed layout sections or apply full theme presets to instant start building
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="hb-tmpl-toolbar">
                <div class="hb-tmpl-search-wrap">
                    <i class="ti ti-search hb-tmpl-search-icon"></i>
                    <input type="text" id="hb-template-search" class="form-control" placeholder="Search templates, preset cards, or themes..." autocomplete="off">
                    <button type="button" id="hb-template-search-clear" class="btn text-muted d-none" aria-label="Clear search">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <ul class="nav nav-pills hb-template-pills" id="hb-template-tabs">
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-cat="all">
                            <i class="ti ti-apps me-1"></i>All Presets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="hero">
                            <i class="ti ti-photo me-1"></i>Hero
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="products">
                            <i class="ti ti-shopping-bag me-1"></i>Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="categories">
                            <i class="ti ti-category me-1"></i>Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="brands">
                            <i class="ti ti-building-store me-1"></i>Brands
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="banners">
                            <i class="ti ti-layout-board me-1"></i>Banners
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="themes">
                            <i class="ti ti-sparkles me-1"></i>Full Themes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-cat="saved">
                            <i class="ti ti-bookmark me-1"></i>Saved Presets
                        </a>
                    </li>
                </ul>
            </div>

            <div class="modal-body p-3">
                <div class="row g-3" id="hb-template-cards-container">
                    {{-- Dynamically populated via JS --}}
                </div>
            </div>
        </div>
    </div>
</div>
