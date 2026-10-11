'use strict';

(function () {
    /**
     * Per-tab config: endpoint, where the body lives, where the count badge lives, and
     * how to render an item. Lets the load/render machinery stay tab-agnostic.
     */
    const TABS = {
        categories: {
            endpointKey: 'categories',
            bodyId: 'zonePreviewCategoriesBody',
            countId: 'zonePreviewCategoriesCount',
            extraParams: () => state.categoryId ? { parent_id: state.categoryId } : {},
            renderItem: renderCategoryCard,
            postRender: wireDrillDown,
        },
        brands: {
            endpointKey: 'brands',
            bodyId: 'zonePreviewBrandsBody',
            countId: 'zonePreviewBrandsCount',
            extraParams: () => state.categoryId ? { category_id: state.categoryId } : {},
            renderItem: renderBrandCard,
            postRender: () => {},
        },
        banners: {
            endpointKey: 'banners',
            bodyId: 'zonePreviewBannersBody',
            countId: 'zonePreviewBannersCount',
            extraParams: () => state.categoryId ? { category_id: state.categoryId } : {},
            renderItem: renderBannerCard,
            postRender: () => {},
        },
        'featured-sections': {
            endpointKey: 'featuredSections',
            bodyId: 'zonePreviewFeaturedSectionsBody',
            countId: 'zonePreviewFeaturedSectionsCount',
            extraParams: () => state.categoryId ? { category_id: state.categoryId } : {},
            renderItem: renderFeaturedSectionCard,
            postRender: () => {},
        },
        products: {
            endpointKey: 'products',
            bodyId: 'zonePreviewProductsBody',
            countId: 'zonePreviewProductsCount',
            extraParams: () => state.categoryId ? { category_id: state.categoryId } : {},
            renderItem: renderProductCard,
            postRender: () => {},
        },
    };

    const TAB_KEYS = Object.keys(TABS);

    const state = {
        zoneId: null,
        categoryId: null,        // null = "All" (root for categories, global for the rest)
        search: '',              // shared across tabs — applies to whichever tab is active
        page: Object.fromEntries(TAB_KEYS.map(k => [k, 1])),
        loaded: Object.fromEntries(TAB_KEYS.map(k => [k, false])),
    };

    let cfg;

    document.addEventListener('DOMContentLoaded', () => {
        const endpoints = document.getElementById('zonePreviewEndpoints');
        if (!endpoints) return;

        cfg = endpoints.dataset;
        initZoneSelect();
        initContextSelect();
        initSearchInput();
        initTabs();
    });

    function initZoneSelect() {
        const el = document.getElementById('zonePreviewZone');
        if (!el || !window.TomSelect) return;

        const ts = new TomSelect(el, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            placeholder: 'Select zone',
        });

        ts.on('change', value => {
            state.zoneId = value ? parseInt(value, 10) : null;
            state.categoryId = null;
            resetPaging();
            refreshContextOptions();
            updateSearchInputState();
            loadActiveTab();
        });
    }

    /**
     * Single search box above the tabs. Debounced; applies to whichever tab
     * is active and persists when the user switches tabs.
     */
    function initSearchInput() {
        const el = document.getElementById('zonePreviewSearch');
        if (!el) return;

        let timer = null;
        el.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const next = el.value.trim();
                if (next === state.search) return;
                state.search = next;
                resetPaging();
                loadActiveTab();
            }, 300);
        });
    }

    function updateSearchInputState() {
        const el = document.getElementById('zonePreviewSearch');
        if (!el) return;
        el.disabled = ! state.zoneId;
        if (! state.zoneId) {
            el.value = '';
            state.search = '';
        }
    }

    function initContextSelect() {
        const el = document.getElementById('zonePreviewContext');
        if (!el) return;

        el.addEventListener('change', () => {
            const value = el.value;
            state.categoryId = value ? parseInt(value, 10) : null;
            resetPaging();
            loadActiveTab();
        });
    }

    function initTabs() {
        document.querySelectorAll('#zonePreviewTabs button[data-tab]').forEach(btn => {
            btn.addEventListener('shown.bs.tab', () => loadTab(btn.dataset.tab));
        });
    }

    /** When zone or context changes, every tab needs to re-fetch. */
    function resetPaging() {
        state.page = Object.fromEntries(TAB_KEYS.map(k => [k, 1]));
        state.loaded = Object.fromEntries(TAB_KEYS.map(k => [k, false]));
    }

    function activeTab() {
        const btn = document.querySelector('#zonePreviewTabs button.nav-link.active');
        return btn?.dataset.tab || 'categories';
    }

    function loadActiveTab() {
        loadTab(activeTab());
    }

    /** Reset Viewing dropdown to "All" + the home categories for the chosen zone. */
    async function refreshContextOptions() {
        const el = document.getElementById('zonePreviewContext');
        if (!el) return;

        el.innerHTML = `<option value="">${cfg.i18nAll}</option>`;
        el.value = '';
        el.disabled = !state.zoneId;
        if (!state.zoneId) return;

        try {
            const res = await axios.get(cfg.homeCategories, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const items = (res.data && res.data.data) || [];
            items.forEach(c => addContextOption(el, c.id, c.title));
        } catch (e) {
            console.error('Failed to load home categories', e);
        }
    }

    /**
     * Add a category to the Viewing dropdown if it isn't already there.
     * Drilling into a non-home subcategory still needs the dropdown to display it.
     */
    function addContextOption(selectEl, id, title) {
        const idStr = String(id);
        if (Array.from(selectEl.options).some(o => o.value === idStr)) return;
        const opt = document.createElement('option');
        opt.value = idStr;
        opt.textContent = title;
        selectEl.appendChild(opt);
    }

    async function loadTab(tabKey, { force = false } = {}) {
        const tab = TABS[tabKey];
        if (!tab) return;
        const body = document.getElementById(tab.bodyId);
        const countEl = document.getElementById(tab.countId);
        if (!body) return;

        if (!state.zoneId) {
            body.innerHTML = `<p class="text-muted m-0">${cfg.i18nNoResults}</p>`;
            if (countEl) countEl.textContent = '';
            return;
        }
        if (state.loaded[tabKey] && !force) return;

        body.innerHTML = `<div class="text-muted">${cfg.i18nLoading}</div>`;

        try {
            const params = { zone_id: state.zoneId, page: state.page[tabKey], ...tab.extraParams() };
            if (state.search) params.search = state.search;
            const res = await axios.get(cfg[tab.endpointKey], {
                params,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = res.data && res.data.data;
            const items = (payload && payload.data) || [];
            const total = payload && typeof payload.total === 'number' ? payload.total : items.length;
            const currentPage = (payload && payload.current_page) || 1;
            const lastPage = (payload && payload.last_page) || 1;

            if (countEl) countEl.textContent = String(total);
            renderTab(tabKey, body, items, currentPage, lastPage);
            state.loaded[tabKey] = true;
        } catch (e) {
            console.error(`Zone preview tab "${tabKey}" load failed`, e);
            body.innerHTML = `<div class="alert alert-danger m-0">Failed to load.</div>`;
            if (countEl) countEl.textContent = '';
        }
    }

    function renderTab(tabKey, body, items, currentPage, lastPage) {
        if (!items.length) {
            body.innerHTML = `<p class="text-muted m-0">${cfg.i18nNoResults}</p>`;
            return;
        }
        const tab = TABS[tabKey];
        const cards = items.map(tab.renderItem).join('');
        body.innerHTML = `<div class="row g-3">${cards}</div>${renderPagination(tabKey, currentPage, lastPage)}`;
        tab.postRender(body);
        wirePagination(body, tabKey);
    }

    function renderCategoryCard(c) {
        return `
            <div class="col-md-4 col-lg-3">
                <a href="#" class="card card-link h-100 zone-preview-category"
                   data-id="${c.id}" data-title="${escapeAttr(c.title)}">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="min-w-0">
                                <div class="fw-medium text-truncate">${escapeHtml(c.title)}</div>
                                <div class="text-muted small text-truncate">${escapeHtml(c.slug || '')}</div>
                            </div>
                            <span class="text-muted ms-2">›</span>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-indigo-lt">${c.products_count} ${cfg.i18nProductsCount}</span>
                        </div>
                    </div>
                </a>
            </div>`;
    }

    function renderBrandCard(b) {
        const placeholder = `<span class="rounded bg-light d-inline-flex align-items-center justify-content-center text-muted" style="width:48px;height:48px;font-size:18px;">${escapeHtml((b.title || '?').charAt(0).toUpperCase())}</span>`;
        const logo = b.logo
            ? `<img src="${escapeAttr(b.logo)}" alt="" class="rounded" style="width:48px;height:48px;object-fit:contain;background:#f6f8fa;">`
            : placeholder;
        return `
            <div class="col-md-4 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            ${logo}
                            <div class="ms-2 min-w-0 flex-grow-1">
                                <div class="fw-medium text-truncate">${escapeHtml(b.title)}</div>
                                <div class="text-muted small text-truncate">${escapeHtml(b.slug || '')}</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-indigo-lt">${b.products_count} ${cfg.i18nProductsCount}</span>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    function renderBannerCard(b) {
        const image = b.image
            ? `<img src="${escapeAttr(b.image)}" alt="" class="card-img-top" style="object-fit:cover;height:120px;background:#f6f8fa;">`
            : `<div class="bg-light text-muted d-flex align-items-center justify-content-center" style="height:120px;font-size:12px;">No image</div>`;
        return `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    ${image}
                    <div class="card-body">
                        <div class="fw-medium text-truncate">${escapeHtml(b.title)}</div>
                        <div class="text-muted small text-truncate">${escapeHtml(b.type || '')} · ${escapeHtml(b.position || '')}</div>
                        <div class="mt-2">${renderZoneBadges(b.zones)}</div>
                    </div>
                </div>
            </div>`;
    }

    function renderFeaturedSectionCard(s) {
        return `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="fw-medium text-truncate">${escapeHtml(s.title)}</div>
                        <div class="text-muted small text-truncate">${escapeHtml(s.section_type || '')}</div>
                        <div class="text-muted small text-truncate">${escapeHtml(s.slug || '')}</div>
                        <div class="mt-2">${renderZoneBadges(s.zones)}</div>
                    </div>
                </div>
            </div>`;
    }

    function renderProductCard(p) {
        const image = p.image
            ? `<img src="${escapeAttr(p.image)}" alt="" class="card-img-top" style="object-fit:cover;height:140px;background:#f6f8fa;">`
            : `<div class="bg-light text-muted d-flex align-items-center justify-content-center" style="height:140px;font-size:12px;">No image</div>`;
        return `
            <div class="col-md-4 col-lg-3">
                <div class="card h-100">
                    ${image}
                    <div class="card-body">
                        <div class="fw-medium text-truncate">${escapeHtml(p.title)}</div>
                        <div class="text-muted small text-truncate">${escapeHtml(p.slug || '')}</div>
                    </div>
                </div>
            </div>`;
    }

    function renderZoneBadges(zones) {
        if (!zones || zones.length === 0) {
            return `<span class="badge bg-blue-lt">${cfg.i18nAllZones || 'All zones'}</span>`;
        }
        return zones.map(z => `<span class="badge bg-indigo-lt me-1">${escapeHtml(z)}</span>`).join('');
    }

    function wireDrillDown(body) {
        body.querySelectorAll('.zone-preview-category').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const id = parseInt(a.dataset.id, 10);
                const title = a.dataset.title;
                const sel = document.getElementById('zonePreviewContext');
                if (!sel) return;
                addContextOption(sel, id, title);
                sel.value = String(id);
                sel.dispatchEvent(new Event('change'));
            });
        });
    }

    function renderPagination(tabKey, currentPage, lastPage) {
        if (!lastPage || lastPage <= 1) return '';
        const prevDisabled = currentPage <= 1 ? 'disabled' : '';
        const nextDisabled = currentPage >= lastPage ? 'disabled' : '';
        return `
            <nav class="mt-3" aria-label="Page navigation">
                <ul class="pagination pagination-sm m-0 justify-content-end">
                    <li class="page-item ${prevDisabled}">
                        <a href="#" class="page-link zone-preview-page" data-tab="${tabKey}" data-page="${currentPage - 1}">‹ ${cfg.i18nPrev}</a>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link">${currentPage} / ${lastPage}</span>
                    </li>
                    <li class="page-item ${nextDisabled}">
                        <a href="#" class="page-link zone-preview-page" data-tab="${tabKey}" data-page="${currentPage + 1}">${cfg.i18nNext} ›</a>
                    </li>
                </ul>
            </nav>`;
    }

    function wirePagination(body, tabKey) {
        body.querySelectorAll(`.zone-preview-page[data-tab="${tabKey}"]`).forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                if (a.closest('.page-item')?.classList.contains('disabled')) return;
                const target = parseInt(a.dataset.page, 10);
                if (!Number.isFinite(target) || target < 1) return;
                state.page[tabKey] = target;
                loadTab(tabKey, { force: true });
            });
        });
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttr(s) {
        return escapeHtml(s);
    }
})();
