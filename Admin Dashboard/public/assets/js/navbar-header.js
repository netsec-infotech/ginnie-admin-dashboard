/**
 * Navbar & Header Storefront Navigation & Appearance Studio
 */
(function () {
    // Safe notification toast helper
    function showToast(icon, title) {
        if (typeof Toast !== "undefined" && typeof Toast.fire === "function") {
            Toast.fire({ icon: icon, title: title });
        } else if (typeof Swal !== "undefined" && typeof Swal.fire === "function") {
            Swal.fire({
                icon: icon,
                title: title,
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            console.log(`[${icon}] ${title}`);
        }
    }

    // FilePond file sync helper
    function setPondFile(inputEl, fileUrl) {
        if (!inputEl || typeof FilePond === "undefined") return;
        let pond = FilePond.find(inputEl);
        if (!pond) {
            pond = FilePond.create(inputEl, {
                allowImagePreview: true,
                credits: false,
                storeAsFile: true,
                acceptedFileTypes: ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/svg+xml', '.svg']
            });
        }
        if (pond) {
            pond.removeFiles();
            if (fileUrl) {
                pond.addFile(fileUrl);
            }
        }
    }

    function syncPreviews() {
        if (typeof window.refreshHomeBuilderPreviews === 'function') {
            window.refreshHomeBuilderPreviews();
        }
    }

    function initNavbarHeader() {
        const searchSelectEl = document.getElementById("add-category-search-select");
        const sortableContainer = document.getElementById("categories-sortable-list");
        const catCountEl = document.getElementById("navbar-cat-count");
        const saveSortingBtn = document.getElementById("save-category-order");

        const globalHomeBtn = document.getElementById("hb-global-home-btn");
        const globalTitleDisplay = document.getElementById("hb-global-title-display");
        const inspectorHeading = document.getElementById("hb-inspector-heading");
        const selectedTitleEl = document.getElementById("hb-cat-selected-title");
        const topSaveBtn = document.getElementById("hb-save-cat-appearance-top-btn");

        // Forms
        const globalForm = document.getElementById("hb-global-settings-form");
        const catAppearanceForm = document.getElementById("hb-cat-appearance-form");

        // Global Form Fields
        const globalTitleInput = document.getElementById("hb-global-title-input");
        const globalIconInput = document.getElementById("hb-global-icon-upload");
        const globalActiveIconInput = document.getElementById("hb-global-active-icon-upload");
        const globalBgTypeSelect = document.getElementById("hb-global-bg-type-select");
        const globalBgColorField = document.getElementById("hb-global-bg-color-field");
        const globalBgImageField = document.getElementById("hb-global-bg-image-field");
        const globalBgAnimField = document.getElementById("hb-global-bg-animation-field");
        const globalImgWebInput = document.getElementById("hb-global-bg-img-web-upload");
        const globalImgAppInput = document.getElementById("hb-global-bg-img-app-upload");
        const globalAnimWebInput = document.getElementById("hb-global-bg-anim-web-upload");
        const globalAnimAppInput = document.getElementById("hb-global-bg-anim-app-upload");
        const globalWebLottieWrap = document.getElementById("hb-global-web-lottie-wrap");
        const globalAppLottieWrap = document.getElementById("hb-global-app-lottie-wrap");
        const globalWebLottiePlayer = document.getElementById("hb-global-web-lottie-player");
        const globalAppLottiePlayer = document.getElementById("hb-global-app-lottie-player");

        // Category Form Fields
        const catIconInput = document.getElementById("hb-cat-icon-upload");
        const catActiveIconInput = document.getElementById("hb-cat-active-icon-upload");
        const catBgTypeSelect = document.getElementById("hb-bg-type-select");
        const catBgColorField = document.getElementById("hb-bg-color-field");
        const catBgImageField = document.getElementById("hb-bg-image-field");
        const catBgAnimField = document.getElementById("hb-bg-animation-field");

        const colorWebInput = document.getElementById("hb-bg-color-web-input");
        const colorAppInput = document.getElementById("hb-bg-color-app-input");
        const fontColorInput = document.getElementById("hb-font-color-input");
        const catIdInput = document.getElementById("hb-cat-id-input");

        const imgWebInput = document.getElementById("hb-bg-image-web-upload");
        const imgAppInput = document.getElementById("hb-bg-image-app-upload");

        const animWebInput = document.getElementById("hb-bg-anim-web-upload");
        const animAppInput = document.getElementById("hb-bg-anim-app-upload");
        const animWebWrap = document.getElementById("hb-web-lottie-wrap");
        const animAppWrap = document.getElementById("hb-app-lottie-wrap");
        const animWebPlayer = document.getElementById("hb-web-lottie-player");
        const animAppPlayer = document.getElementById("hb-app-lottie-player");

        let activeMode = "global"; // 'global' | 'category'
        let activeRow = null;
        let searchTomSelect = null;

        // Render Style Indicator Pill HTML
        function renderPillHtml(bgType, colorWeb) {
            if (bgType === 'color') {
                return `
                    <span class="rounded-circle border d-inline-block hb-cat-color-dot" style="width: 8px; height: 8px; background: ${colorWeb || '#ffffff'};"></span>
                    <span class="hb-cat-bgtype-text">Color</span>
                `;
            } else if (bgType === 'image') {
                return `
                    <i class="ti ti-photo" style="font-size: 11px;"></i>
                    <span class="hb-cat-bgtype-text">Image</span>
                `;
            } else if (bgType === 'animation') {
                return `
                    <i class="ti ti-player-play" style="font-size: 11px;"></i>
                    <span class="hb-cat-bgtype-text">Animation</span>
                `;
            }
            return `<span class="hb-cat-bgtype-text">None</span>`;
        }

        function updateRowPill(row, bgType, colorWeb) {
            if (!row) return;
            const pill = row.querySelector('.hb-cat-bgtype-pill');
            if (pill) {
                pill.innerHTML = renderPillHtml(bgType, colorWeb);
            }
        }

        // Re-index number badges in list
        function updateIndexBadges() {
            if (!sortableContainer) return;
            const rows = sortableContainer.querySelectorAll(".hb-category-item-row");
            rows.forEach((row, i) => {
                const badge = row.querySelector(".hb-cat-index");
                if (badge) badge.textContent = i + 1;
            });
            if (catCountEl) catCountEl.textContent = rows.length;

            const placeholder = sortableContainer.querySelector(".hb-no-cat-placeholder");
            if (rows.length === 0) {
                if (!placeholder) {
                    sortableContainer.innerHTML = `
                        <div class="text-center py-4 text-muted hb-no-cat-placeholder">
                            <i class="ti ti-category fs-1 mb-2 d-block text-secondary"></i>
                            <span class="small">No categories on home navbar yet. Search above to add.</span>
                        </div>
                    `;
                }
            } else if (placeholder) {
                placeholder.remove();
            }
        }

        // 1. Initialize Autocomplete "Add a category..." (TomSelect)
        if (searchSelectEl && window.TomSelect && !searchSelectEl.tomselect) {
            searchTomSelect = new TomSelect(searchSelectEl, {
                create: false,
                maxItems: 1,
                placeholder: "Add a category...",
                onChange: function (selectedId) {
                    if (!selectedId) return;
                    addCategoryToNavbar(selectedId);
                    this.clear(true);
                }
            });
        }

        // 2. Global Search Labels TomSelect
        const globalSearchLabelsSelect = document.getElementById("hb-global-search-labels");
        if (globalSearchLabelsSelect && window.TomSelect && !globalSearchLabelsSelect.tomselect) {
            new TomSelect(globalSearchLabelsSelect, {
                plugins: ['remove_button'],
                persist: false,
                create: true,
                maxItems: null,
                placeholder: "Search keywords (e.g. fresh milk, bakery)...",
            });
        }

        // Add Category to Navbar list
        function addCategoryToNavbar(catId) {
            if (!sortableContainer) return;

            // Check if already in list
            const existingRow = sortableContainer.querySelector(`.hb-category-item-row[data-id="${catId}"]`);
            if (existingRow) {
                selectCategory(existingRow);
                return;
            }

            // Find option in select
            let opt = null;
            if (searchSelectEl) {
                opt = searchSelectEl.querySelector(`option[value="${catId}"]`);
            }
            if (!opt) return;

            const catTitle = opt.dataset.title || opt.textContent.trim();
            const catIcon = opt.dataset.icon || "";
            const catActiveIcon = opt.dataset.activeIcon || catIcon;
            const catStatus = opt.dataset.status || "active";
            const bgType = opt.dataset.bgType || "none";
            const bgColorWeb = opt.dataset.bgColorWeb || "#ffffff";
            const bgColorApp = opt.dataset.bgColorApp || "#ffffff";
            const bgImgWeb = opt.dataset.bgImageWeb || "";
            const bgImgApp = opt.dataset.bgImageApp || "";
            const bgAnimWeb = opt.dataset.bgAnimWeb || "";
            const bgAnimApp = opt.dataset.bgAnimApp || "";
            const fontColor = opt.dataset.fontColor || "#000000";

            const count = sortableContainer.querySelectorAll(".hb-category-item-row").length + 1;

            const row = document.createElement("div");
            row.className = "card border rounded-3 p-2 bg-body shadow-xs sortable-item hb-category-item-row";
            row.dataset.id = catId;
            row.dataset.title = catTitle;
            row.dataset.icon = catIcon;
            row.dataset.activeIcon = catActiveIcon;
            row.dataset.status = catStatus;
            row.dataset.bgType = bgType;
            row.dataset.bgColorWeb = bgColorWeb;
            row.dataset.bgColorApp = bgColorApp;
            row.dataset.bgImageWeb = bgImgWeb;
            row.dataset.bgImageApp = bgImgApp;
            row.dataset.bgAnimWeb = bgAnimWeb;
            row.dataset.bgAnimApp = bgAnimApp;
            row.dataset.fontColor = fontColor;
            row.style.cursor = "pointer";
            row.style.transition = "all 0.15s ease";

            row.innerHTML = `
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                        <div class="hb-cat-drag-handle text-muted" style="cursor: grab;" title="Drag to reorder">
                            <i class="ti ti-grip-vertical fs-2"></i>
                        </div>
                        <span class="badge bg-primary-lt text-primary fw-bold px-2 py-1 hb-cat-index" style="font-size: 11px;">
                            ${count}
                        </span>
                        <span class="fw-semibold text-body text-truncate hb-cat-title-display">
                            ${catTitle}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="badge bg-secondary-lt text-secondary hb-cat-bgtype-pill d-inline-flex align-items-center gap-1" style="font-size: 10px; font-weight: 500;">
                            ${renderPillHtml(bgType, bgColorWeb)}
                        </span>
                        <button type="button" class="btn btn-icon btn-sm btn-ghost-danger hb-remove-cat-btn"
                            data-id="${catId}" title="Remove from top navbar" style="font-size: 16px; line-height: 1;">
                            &times;
                        </button>
                    </div>
                </div>
            `;

            sortableContainer.appendChild(row);
            updateIndexBadges();
            selectCategory(row);
            persistNavbarCategories();
        }

        // Persist Home Navbar Categories to backend
        function persistNavbarCategories(onSuccess) {
            if (!sortableContainer) return;
            const ids = Array.from(sortableContainer.querySelectorAll(".hb-category-item-row")).map(el => el.dataset.id);

            axios.post('/admin/categories/home-categories', { home_category_ids: ids }, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(res => {
                showToast("success", res.data?.message || "Navbar categories updated");
                syncPreviews();
                if (onSuccess) onSuccess();
            }).catch(err => {
                const msg = err.response?.data?.message || "Failed to update navbar categories";
                showToast("error", msg);
            });
        }

        // 3. Remove Category from Navbar list
        if (sortableContainer) {
            sortableContainer.addEventListener("click", function (e) {
                const removeBtn = e.target.closest(".hb-remove-cat-btn");
                if (removeBtn) {
                    e.stopPropagation();
                    const row = removeBtn.closest(".hb-category-item-row");
                    if (!row) return;

                    row.remove();
                    updateIndexBadges();

                    if (activeRow === row) {
                        selectGlobalHome();
                    }

                    persistNavbarCategories();
                    return;
                }

                // Row click -> select category
                const row = e.target.closest(".hb-category-item-row");
                if (row) {
                    selectCategory(row);
                }
            });
        }

        // 4. Initialize SortableJS
        if (sortableContainer && window.Sortable) {
            new Sortable(sortableContainer, {
                animation: 200,
                handle: ".hb-cat-drag-handle",
                ghostClass: "bg-primary-lt",
                onEnd: function () {
                    updateIndexBadges();
                    persistNavbarCategories();
                }
            });
        }

        // Save Sorting Order Button
        if (saveSortingBtn) {
            saveSortingBtn.addEventListener("click", function () {
                if (!sortableContainer) return;
                const ids = Array.from(sortableContainer.querySelectorAll(".hb-category-item-row")).map(el => el.dataset.id);
                if (!ids.length) {
                    showToast("warning", "No categories to sort");
                    return;
                }

                saveSortingBtn.disabled = true;
                saveSortingBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

                axios.post('/admin/categories/sort', { categories: ids }, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(res => {
                    showToast("success", res.data?.message || "Sort order updated successfully");
                    updateIndexBadges();
                    syncPreviews();
                }).catch(err => {
                    const msg = err.response?.data?.message || "Failed to update sort order";
                    showToast("error", msg);
                }).finally(() => {
                    saveSortingBtn.disabled = false;
                    saveSortingBtn.innerHTML = '<i class="ti ti-check me-1"></i>Save sorting';
                });
            });
        }

        // 5. Select Global Home Settings (Mode: 'global')
        function selectGlobalHome() {
            activeMode = "global";
            activeRow = null;

            // Highlight Global Home card
            if (globalHomeBtn) {
                globalHomeBtn.classList.add("border-primary", "bg-primary-lt");
            }

            // Deselect all category rows
            if (sortableContainer) {
                sortableContainer.querySelectorAll(".hb-category-item-row").forEach(r => {
                    r.classList.remove("border-primary", "bg-primary-lt");
                });
            }

            // Update Right Inspector
            if (inspectorHeading) inspectorHeading.textContent = "Global Header Appearance";
            const titleVal = globalTitleInput ? globalTitleInput.value : "Home / Global";
            if (selectedTitleEl) selectedTitleEl.textContent = titleVal || "Home / Global";

            if (globalForm) globalForm.classList.remove("d-none");
            if (catAppearanceForm) catAppearanceForm.classList.add("d-none");
        }

        if (globalHomeBtn) {
            globalHomeBtn.addEventListener("click", selectGlobalHome);
        }

        // 6. Select Category (Mode: 'category')
        function selectCategory(row) {
            if (!row) return;
            activeMode = "category";
            activeRow = row;

            // Deselect Global Home card
            if (globalHomeBtn) {
                globalHomeBtn.classList.remove("border-primary", "bg-primary-lt");
            }

            // Highlight active row
            if (sortableContainer) {
                sortableContainer.querySelectorAll(".hb-category-item-row").forEach(r => {
                    r.classList.remove("border-primary", "bg-primary-lt");
                });
            }
            row.classList.add("border-primary", "bg-primary-lt");

            // Extract category data
            const catId = row.dataset.id;
            const catTitle = row.dataset.title || "Category #" + catId;
            const catIcon = row.dataset.icon || "";
            const catActiveIcon = row.dataset.activeIcon || catIcon;
            const bgType = row.dataset.bgType || "";
            const bgColorWeb = row.dataset.bgColorWeb || "#ffffff";
            const bgColorApp = row.dataset.bgColorApp || "#ffffff";
            const bgImgWeb = row.dataset.bgImageWeb || "";
            const bgImgApp = row.dataset.bgImageApp || "";
            const bgAnimWeb = row.dataset.bgAnimWeb || "";
            const bgAnimApp = row.dataset.bgAnimApp || "";
            const fontColor = row.dataset.fontColor || "#000000";

            // Update Right Inspector Header
            if (inspectorHeading) inspectorHeading.textContent = "Category Header Appearance";
            if (selectedTitleEl) selectedTitleEl.textContent = catTitle;

            // Switch visible form
            if (globalForm) globalForm.classList.add("d-none");
            if (catAppearanceForm) catAppearanceForm.classList.remove("d-none");

            // Populate Category Form
            if (catIdInput) catIdInput.value = catId;
            if (catBgTypeSelect) catBgTypeSelect.value = bgType;
            updateCatBgTypeFields(bgType);

            if (colorWebInput) colorWebInput.value = bgColorWeb || "#ffffff";
            if (colorAppInput) colorAppInput.value = bgColorApp || "#ffffff";
            if (fontColorInput) fontColorInput.value = fontColor || "#000000";

            // Populate Category FilePond in-input image and icon previews
            setPondFile(catIconInput, catIcon);
            setPondFile(catActiveIconInput, catActiveIcon);
            setPondFile(imgWebInput, bgImgWeb);
            setPondFile(imgAppInput, bgImgApp);

            // Populate Category Lottie Previews
            if (animWebWrap && animWebPlayer) {
                if (bgAnimWeb) {
                    animWebPlayer.setAttribute("src", bgAnimWeb);
                    animWebWrap.classList.remove("d-none");
                } else {
                    animWebWrap.classList.add("d-none");
                }
            }
            if (animAppWrap && animAppPlayer) {
                if (bgAnimApp) {
                    animAppPlayer.setAttribute("src", bgAnimApp);
                    animAppWrap.classList.remove("d-none");
                } else {
                    animAppWrap.classList.add("d-none");
                }
            }
        }

        // 7. Background Type Field Toggles
        function updateGlobalBgTypeFields(bgType) {
            if (globalBgColorField) globalBgColorField.classList.toggle("d-none", bgType !== "color");
            if (globalBgImageField) globalBgImageField.classList.toggle("d-none", bgType !== "image");
            if (globalBgAnimField) globalBgAnimField.classList.toggle("d-none", bgType !== "animation");
        }

        if (globalBgTypeSelect) {
            globalBgTypeSelect.addEventListener("change", function () {
                updateGlobalBgTypeFields(this.value);
            });
        }

        function updateCatBgTypeFields(bgType) {
            if (catBgColorField) catBgColorField.classList.toggle("d-none", bgType !== "color");
            if (catBgImageField) catBgImageField.classList.toggle("d-none", bgType !== "image");
            if (catBgAnimField) catBgAnimField.classList.toggle("d-none", bgType !== "animation");
        }

        if (catBgTypeSelect) {
            catBgTypeSelect.addEventListener("change", function () {
                updateCatBgTypeFields(this.value);
            });
        }

        // 8. Lottie Animation Previews (JSON file reader)
        function bindLottiePreview(inputEl, playerEl, wrapEl) {
            if (!inputEl || !playerEl || !wrapEl) return;
            inputEl.addEventListener("change", function () {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        try {
                            playerEl.load(e.target.result);
                            wrapEl.classList.remove("d-none");
                        } catch (err) {
                            console.error("Lottie load error:", err);
                        }
                    };
                    reader.readAsText(this.files[0]);
                }
            });
        }
        bindLottiePreview(globalAnimWebInput, globalWebLottiePlayer, globalWebLottieWrap);
        bindLottiePreview(globalAnimAppInput, globalAppLottiePlayer, globalAppLottieWrap);
        bindLottiePreview(animWebInput, animWebPlayer, animWebWrap);
        bindLottiePreview(animAppInput, animAppPlayer, animAppWrap);

        // 9. Save Handlers (Global vs Category)
        function saveGlobalSettings() {
            if (!globalForm) return;

            if (topSaveBtn) {
                topSaveBtn.disabled = true;
                topSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            }

            const formData = new FormData(globalForm);

            axios.post(globalForm.action, formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'multipart/form-data'
                }
            }).then(res => {
                showToast("success", res.data?.message || "Global header settings saved!");
                const titleVal = globalTitleInput ? globalTitleInput.value : "Home / Global";
                if (globalTitleDisplay) globalTitleDisplay.textContent = titleVal;
                if (selectedTitleEl) selectedTitleEl.textContent = titleVal;
                syncPreviews();
            }).catch(err => {
                const msg = err.response?.data?.message || "Failed to save global settings";
                showToast("error", msg);
            }).finally(() => {
                if (topSaveBtn) {
                    topSaveBtn.disabled = false;
                    topSaveBtn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>Save appearance';
                }
            });
        }

        function saveCategoryAppearance() {
            if (!catAppearanceForm) return;
            const catId = catIdInput ? catIdInput.value : null;
            if (!catId) return;

            if (topSaveBtn) {
                topSaveBtn.disabled = true;
                topSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
            }

            const formData = new FormData(catAppearanceForm);

            axios.post(`/admin/categories/${catId}/appearance`, formData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'multipart/form-data'
                }
            }).then(res => {
                const data = res.data?.data || {};
                showToast("success", res.data?.message || "Category appearance saved!");

                // Update row attributes & badge
                if (activeRow) {
                    if (data.icon) activeRow.dataset.icon = data.icon;
                    if (data.active_icon) activeRow.dataset.activeIcon = data.active_icon;
                    activeRow.dataset.bgType = data.background_type || "";
                    activeRow.dataset.bgColorWeb = data.background_color_web || "";
                    activeRow.dataset.bgColorApp = data.background_color_app || "";
                    activeRow.dataset.bgImageWeb = data.background_image_web || "";
                    activeRow.dataset.bgImageApp = data.background_image_app || "";
                    activeRow.dataset.bgAnimWeb = data.background_animation_web || "";
                    activeRow.dataset.bgAnimApp = data.background_animation_app || "";
                    activeRow.dataset.fontColor = data.font_color || "";

                    updateRowPill(activeRow, data.background_type, data.background_color_web);
                }
                syncPreviews();
            }).catch(err => {
                const msg = err.response?.data?.message || "Failed to save category appearance";
                showToast("error", msg);
            }).finally(() => {
                if (topSaveBtn) {
                    topSaveBtn.disabled = false;
                    topSaveBtn.innerHTML = '<i class="ti ti-device-floppy me-1"></i>Save appearance';
                }
            });
        }

        // Top Save Button triggers appropriate save based on activeMode
        if (topSaveBtn) {
            topSaveBtn.addEventListener("click", function (e) {
                e.preventDefault();
                if (activeMode === "global") {
                    saveGlobalSettings();
                } else {
                    saveCategoryAppearance();
                }
            });
        }

        // Default to Global Home selection
        selectGlobalHome();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initNavbarHeader);
    } else {
        initNavbarHeader();
    }
})();
