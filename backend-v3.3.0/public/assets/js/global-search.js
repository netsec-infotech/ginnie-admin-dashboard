/**
 * Global Search Command Palette Controller
 * Handles keyboard shortcuts (Ctrl+K), debounced API requests, category filtering, and item navigation.
 */

// Helper to open/toggle modal across different Bootstrap/jQuery/Tabler environments
function toggleGlobalSearchModal() {
    const searchModalEl = document.getElementById('globalSearchModal');
    if (!searchModalEl) return;

    if (window.bootstrap && window.bootstrap.Modal) {
        const modalInstance = window.bootstrap.Modal.getOrCreateInstance(searchModalEl);
        modalInstance.toggle();
    } else if (window.jQuery && typeof jQuery(searchModalEl).modal === 'function') {
        jQuery(searchModalEl).modal('toggle');
    } else {
        const isShown = searchModalEl.classList.contains('show');
        if (isShown) {
            searchModalEl.classList.remove('show');
            searchModalEl.style.display = 'none';
            document.body.classList.remove('modal-open');
        } else {
            searchModalEl.classList.add('show');
            searchModalEl.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    }
}

// 1. Global Keyboard Shortcut Handler (Ctrl + K / Cmd + K) attached to Window immediately
window.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K' || e.code === 'KeyK')) {
        e.preventDefault();
        e.stopPropagation();
        toggleGlobalSearchModal();
    }
}, true);

document.addEventListener('DOMContentLoaded', function () {
    const searchModalEl = document.getElementById('globalSearchModal');
    if (!searchModalEl) {
        return;
    }

    const searchInput = document.getElementById('globalSearchInput');
    const searchClearBtn = document.getElementById('globalSearchClear');
    const resultsContainer = document.getElementById('globalSearchResults');
    const filterContainer = document.getElementById('globalSearchFilters');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const panel = document.getElementById('panel')?.getAttribute('data-panel') || 'admin';
    const baseUrl = document.getElementById('base_url')?.value || '';

    let debounceTimer = null;
    let activeIndex = -1;
    let currentRawData = null;
    let preloadedRawData = null;
    let preloadRequested = false;
    let currentFilter = 'All';

    // Attach click listeners to trigger buttons
    document.querySelectorAll('[data-bs-target="#globalSearchModal"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleGlobalSearchModal();
        });
    });

    // Auto-focus input when modal opens
    if (searchModalEl) {
        searchModalEl.addEventListener('shown.bs.modal', function () {
            searchInput.focus();
            currentFilter = 'All'; // Always reset to 'All' by default when modal opens
            if (searchInput.value.trim().length >= 2 && !currentRawData) {
                triggerSearch(searchInput.value.trim());
            } else if (searchInput.value.trim().length < 2) {
                renderPreloadedState();
            }
        });
    }

    // 2. Input Events with 300ms Debounce
    searchInput.addEventListener('input', function () {
        const query = searchInput.value.trim();
        searchClearBtn.style.display = query.length > 0 ? 'block' : 'none';

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        if (query.length < 2) {
            currentRawData = null;
            currentFilter = 'All';
            renderPreloadedState();
            return;
        }

        renderLoadingState();

        debounceTimer = setTimeout(function () {
            triggerSearch(query);
        }, 300);
    });

    // Clear input handler
    searchClearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchClearBtn.style.display = 'none';
        currentFilter = 'All';
        currentRawData = null;
        renderPreloadedState();
        searchInput.focus();
    });

    function renderPreloadedState() {
        if (preloadedRawData) {
            currentRawData = preloadedRawData;
            renderFilterChips(preloadedRawData.results);
            renderResults(preloadedRawData.results, '', 'All');
            return;
        }

        if (!preloadRequested) {
            triggerPreload();
            return;
        }

        renderDefaultState();
    }

    function triggerPreload() {
        preloadRequested = true;
        renderLoadingState('Loading quick links...');

        fetch(`${baseUrl}/${panel}/global-search`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
            .then((res) => res.json())
            .then((response) => {
                if (searchInput.value.trim().length >= 2) {
                    return;
                }

                if (response.status === 'success' && response.data.total > 0) {
                    preloadedRawData = response.data;
                    currentRawData = response.data;
                    renderFilterChips(response.data.results);
                    renderResults(response.data.results, '', 'All');
                } else {
                    renderDefaultState();
                }
            })
            .catch((err) => {
                console.error('Global search preload error:', err);
                renderDefaultState();
            });
    }

    // 3. Perform AJAX Search Request
    function triggerSearch(query) {
        currentFilter = 'All'; // Always reset filter to 'All' by default on every new search query
        const endpoint = `${baseUrl}/${panel}/global-search?q=${encodeURIComponent(query)}`;

        fetch(endpoint, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.status === 'success') {
                    currentRawData = response.data;
                    renderFilterChips(response.data.results);
                    renderResults(response.data.results, query, 'All');
                } else {
                    renderEmptyState(query);
                }
            })
            .catch((err) => {
                console.error('Global search error:', err);
                renderEmptyState(query);
            });
    }

    // 4. Category Filter Chips Generator
    function renderFilterChips(groupedResults) {
        const categories = Object.keys(groupedResults || {});

        if (categories.length === 0) {
            filterContainer.innerHTML = '';
            filterContainer.style.display = 'none';
            return;
        }

        filterContainer.style.display = 'flex';
        let chipsHtml = `<span class="search-filter-chip ${currentFilter === 'All' ? 'active' : ''}" data-filter="All">All</span>`;

        categories.forEach((cat) => {
            const count = groupedResults[cat].length;
            const isActive = currentFilter === cat ? 'active' : '';
            chipsHtml += `<span class="search-filter-chip ${isActive}" data-filter="${escapeHtml(cat)}">${escapeHtml(cat)} (${count})</span>`;
        });

        filterContainer.innerHTML = chipsHtml;

        // Attach click listeners to filter chips
        filterContainer.querySelectorAll('.search-filter-chip').forEach((chip) => {
            chip.addEventListener('click', function () {
                currentFilter = this.getAttribute('data-filter');
                filterContainer.querySelectorAll('.search-filter-chip').forEach((c) => c.classList.remove('active'));
                this.classList.add('active');

                if (currentRawData) {
                    renderResults(currentRawData.results, searchInput.value.trim(), currentFilter);
                }
            });
        });
    }

    // 5. Render Grouped Results UI
    function renderResults(groupedResults, query, filter) {
        activeIndex = -1;
        let html = '';
        let totalRendered = 0;

        const categories = Object.keys(groupedResults || {});

        if (categories.length === 0) {
            renderEmptyState(query);
            return;
        }

        categories.forEach((cat) => {
            if (filter !== 'All' && filter !== cat) {
                return;
            }

            const items = groupedResults[cat];
            if (items.length === 0) {
                return;
            }

            html += `<div class="search-category-header">${escapeHtml(cat)}</div>`;

            items.forEach((item) => {
                totalRendered++;
                const highlightedTitle = highlightMatches(item.title, query);

                html += `
                    <a href="${escapeHtml(item.url)}" class="search-result-item" data-index="${totalRendered - 1}">
                        <div class="search-result-icon">
                            <i class="${escapeHtml(item.icon)}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">
                                ${highlightedTitle}
                                <span class="badge ${escapeHtml(item.badge_class || 'bg-secondary-lt')} ms-2">${escapeHtml(item.badge)}</span>
                            </div>
                            <div class="search-result-subtitle">${escapeHtml(item.subtitle)}</div>
                        </div>
                    </a>
                `;
            });
        });

        if (totalRendered === 0) {
            renderEmptyState(query);
        } else {
            resultsContainer.innerHTML = html;
        }
    }

    // 6. Keyboard Traversal Handler (ArrowUp, ArrowDown, Enter)
    searchInput.addEventListener('keydown', function (e) {
        const items = resultsContainer.querySelectorAll('.search-result-item');
        if (items.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % items.length;
            updateActiveItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
            updateActiveItem(items);
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                window.location.href = items[activeIndex].getAttribute('href');
            }
        }
    });

    function updateActiveItem(items) {
        items.forEach((item, idx) => {
            if (idx === activeIndex) {
                item.classList.add('active-item');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active-item');
            }
        });
    }

    // State Render Helpers
    function renderLoadingState(message = 'Searching records...') {
        resultsContainer.innerHTML = `
            <div class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
    }

    function renderDefaultState() {
        filterContainer.style.display = 'none';
        resultsContainer.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="ti ti-search fs-1 text-secondary mb-2 d-block"></i>
                <div class="fw-medium">Type at least 2 characters to search</div>
                <div class="small">Search menu pages, categories, products, orders, returns, and stores</div>
            </div>
        `;
    }

    function renderEmptyState(query) {
        resultsContainer.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="ti ti-circle-x fs-1 text-danger mb-2 d-block"></i>
                <div class="fw-medium">No results found for "${escapeHtml(query)}"</div>
                <div class="small">Try checking for typos or search by Category, Product SKU, or Order ID</div>
            </div>
        `;
    }

    function highlightMatches(text, query) {
        if (!text || !query) {
            return escapeHtml(text || '');
        }
        const safeText = escapeHtml(text);
        const safeQuery = escapeHtml(query);
        const regex = new RegExp(`(${safeQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return safeText.replace(regex, '<mark class="search-highlight">$1</mark>');
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    renderPreloadedState();
});
