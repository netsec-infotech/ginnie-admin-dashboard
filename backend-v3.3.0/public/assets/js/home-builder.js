(function () {
    const roots = document.querySelectorAll("[data-home-builder]");
    if (!roots.length) return;

    const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
    if (window.axios) {
        window.axios.defaults.headers.common["X-Requested-With"] =
            "XMLHttpRequest";
        window.axios.defaults.headers.common["X-CSRF-TOKEN"] = csrf;
    }
    if (window.FilePond) {
        try {
            window.FilePond.registerPlugin(
                window.FilePondPluginImagePreview,
                window.FilePondPluginFileValidateType,
                window.FilePondPluginFileValidateSize,
            );
        } catch (e) {
            /* already registered */
        }
    }

    /* ---------- shared constants ---------- */
    const ICON = {
        hero: "ti-photo",
        products: "ti-shopping-bag",
        categories: "ti-category",
        brands: "ti-tag",
        play_image: "ti-layout-grid-add",
        banners: "ti-layout-board",
        stories: "ti-history",
        reels: "ti-video",
    };
    const TYPE = [
        ["play_image", "Play Image"],
        ["hero", "Hero"],
        ["products", "Products"],
        ["categories", "Categories"],
        ["brands", "Brands"],
        ["stories", "Stories"],
        ["reels", "Reels"],
    ];
    const HERO_MEDIA = [
        ["image", "Image"],
        ["lottie", "Animation"],
    ];
    const HERO_LAYOUT = [
        ["none", "Banner only"],
        ["items", "With items"],
    ];
    const ITEM_SOURCE = [
        ["products", "Products"],
        ["brands", "Brands"],
        ["categories", "Categories"],
    ];
    const PRODUCT_SOURCE = [
        ["newly_added", "Newly added"],
        ["top_rated", "Top rated"],
        ["best_seller", "Best seller"],
        ["featured", "Featured"],
        ["recommended", "Recommended"],
        ["custom", "Custom selection"],
    ];
    const ORIENTATION = [
        ["vertical", "Vertical"],
        ["horizontal", "Horizontal"],
    ];
    const BACKGROUND = [
        ["none", "None"],
        ["color", "Color"],
        ["image", "Image"],
    ];
    const BACKGROUND_IMAGE_STYLE = [
        ["full", "Full"],
        ["half", "Half"],
    ];
    const SOURCE_MODE = [
        ["all", "All"],
        ["custom", "Custom"],
    ];
    const CATEGORY_ROWS = [
        ["row", "Horizontal"],
        ["grid", "Vertical"],
    ];
    const BRAND_ROWS = [
        ["row", "Horizontal"],
        ["grid", "Vertical"],
    ];
    const GRID_COLUMNS = [
        ["1", "1 per row"],
        ["2", "2 per row"],
        ["3", "3 per row"],
        ["4", "4 per row"],
    ];
    const REEL_SOURCES = [
        ["trending", "Trending"],
        ["latest", "Latest"],
        ["most_liked", "Most Liked"],
    ];
    const STORY_RING_COLORS = [
        "#E1306C",
        "#833AB4",
        "#FD1D1D",
        "#F56040",
        "#25D366",
        "#007AFF",
        "#FF9500",
        "#6366F1",
    ];
    const STYLES = {
        hero: [
            ["full", "Full image"],
            ["image_title", "Image + title"],
        ],
        categories: [
            ["overlay", "Overlay title"],
            ["default", "Default"],
            ["custom_image", "Custom Image"],
        ],
        brands: [
            ["full", "Full image"],
            ["image_title", "Image + title"],
            ["custom_image", "Custom Image"],
        ],
        banners: [
            ["full", "Full width"],
            ["peek", "Peek sides"],
        ],
        play_image: [
            ["grid", "Grid Layout"],
            ["scroll", "Scroll/Slider Layout"],
        ],
        stories: [
            ["avatar_circle", "Avatar Circles"],
        ],
        reels: [
            ["card_slider", "Card Slider (9:16)"],
            ["grid", "Grid Layout"],
        ],
    };
    const LINK_TYPES = [
        ["product", "Product"],
        ["category", "Category"],
        ["brand", "Brand"],
        ["custom", "Custom URL"],
    ];
    const GRID_LINK_TYPES = [["none", "No Link"]].concat(LINK_TYPES);
    const PLAY_IMAGE_VISIBLE_OPTIONS = [
        ["auto", "Auto"],
        ["1", "1 item"],
        ["1.2", "1.2 items"],
        ["1.5", "1.5 items"],
        ["2", "2 items"],
        ["3", "3 items"],
        ["4", "4 items"],
        ["6", "6 items"],
    ];
    const PLAY_IMAGE_VISIBLE_MOBILE_OPTIONS = [
        ["auto", "Auto"],
        ["1", "1 item"],
        ["1.2", "1.2 items"],
        ["1.5", "1.5 items"],
        ["2", "2 items"],
        ["3", "3 items"],
    ];
    const PLAY_IMAGE_SPAN_OPTIONS = [
        ["auto", "Auto"],
        ["1", "1 column"],
        ["2", "2 columns"],
        ["3", "3 columns"],
        ["4", "4 columns"],
        ["5", "5 columns"],
        ["6", "6 columns"],
        ["7", "7 columns"],
        ["8", "8 columns"],
        ["9", "9 columns"],
        ["10", "10 columns"],
        ["11", "11 columns"],
        ["12", "12 columns"],
    ];
    const SRC_TO_KEY = {
        products: "product",
        brands: "brand",
        categories: "category",
    };
    const MEDIA_FIELD = {
        background: "background_image",
        hero_media: "hero_media",
        hero_media_web_mobile: "hero_media_web_mobile",
        card_background: "card_background_image",
    };

    let segCounter = 0; // shared so radio-group names stay unique across instances

    /* ---------- shared helpers ---------- */
    function safeJson(s, d) {
        try {
            return JSON.parse(s);
        } catch (e) {
            return d;
        }
    }

    function esc(s) {
        const d = document.createElement("div");
        d.textContent = s == null ? "" : String(s);
        return d.innerHTML;
    }

    function isConfigTrue(v, defaultVal = true) {
        if (v === undefined || v === null) return defaultVal;
        if (v === true || v === 1 || v === "1" || v === "true") return true;
        if (v === false || v === 0 || v === "0" || v === "false") return false;
        return Boolean(v);
    }

    function nameOf(v, list) {
        const f = list.find((o) => o[0] === v);
        return f ? f[1] : v;
    }

    function api(method, url, data) {
        return window.axios({
            method,
            url,
            data,
            headers: { Accept: "application/json" },
        });
    }

    function showModal(modalElOrId) {
        const el =
            typeof modalElOrId === "string"
                ? document.getElementById(modalElOrId.replace(/^#/, ""))
                : modalElOrId;
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        } else if (
            window.jQuery &&
            typeof window.jQuery(el).modal === "function"
        ) {
            window.jQuery(el).modal("show");
        }
    }

    function hideModal(modalElOrId) {
        const el =
            typeof modalElOrId === "string"
                ? document.getElementById(modalElOrId.replace(/^#/, ""))
                : modalElOrId;
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            const inst = window.bootstrap.Modal.getInstance(el);
            if (inst) {
                inst.hide();
            } else {
                window.bootstrap.Modal.getOrCreateInstance(el).hide();
            }
        } else if (
            window.jQuery &&
            typeof window.jQuery(el).modal === "function"
        ) {
            window.jQuery(el).modal("hide");
        }
    }

    /* ---------- shared confirm modal ---------- */
    const ConfirmModal = (function () {
        let cb = null;
        const ok = document.getElementById("hbConfirmOk");
        if (ok)
            ok.addEventListener("click", () => {
                hideModal("hbConfirmModal");
                const f = cb;
                cb = null;
                if (f) f();
            });
        return {
            open(msg, callback) {
                cb = callback;
                const m = document.getElementById("hbConfirmMessage");
                if (m) m.textContent = msg;
                showModal("hbConfirmModal");
            },
        };
    })();

    /* ---------- shared image modal (FilePond storeAsFile + optional crop) ---------- */
    const ImageModal = (function () {
        const modalEl = document.getElementById("hbImageModal");
        const dialog = modalEl
            ? modalEl.querySelector(".hb-image-modal-dialog")
            : null;
        const doneBtn = document.getElementById("hbImageDone");
        const modalFooter = document.getElementById("hbImageModalFooter");
        const uploadPane = document.getElementById("hbImageUploadPane");
        const cropPane = document.getElementById("hbImageCropEditor");
        const cropSource = document.getElementById("hbImageCropSource");
        const cropCancel = document.getElementById("hbImageCropCancel");
        const cropApply = document.getElementById("hbImageCropApply");
        const cropOutputWidth = document.getElementById(
            "hbImageCropOutputWidth",
        );
        const ratioButtons = cropPane
            ? Array.from(cropPane.querySelectorAll("[data-crop-ratio]"))
            : [];
        let pond = null;
        let onDone = null;
        let cropper = null;
        let cropEnabled = false;
        let pendingCropFileItem = null;
        let selectedFile = null;
        let suppressNextCrop = false;

        function destroyPond() {
            if (pond) {
                try {
                    pond.destroy();
                } catch (e) {
                    /* noop */
                }
                pond = null;
            }
        }

        function destroyCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            pendingCropFileItem = null;
            if (cropSource) {
                cropSource.src = "";
            }
        }

        function showUploadPane() {
            if (uploadPane) uploadPane.classList.remove("d-none");
            if (cropPane) cropPane.classList.add("d-none");
            if (modalFooter) modalFooter.classList.remove("d-none");
            if (dialog) dialog.classList.remove("is-cropping");
        }

        function showCropPane() {
            if (uploadPane) uploadPane.classList.add("d-none");
            if (cropPane) cropPane.classList.remove("d-none");
            if (modalFooter) modalFooter.classList.add("d-none");
            if (dialog) dialog.classList.add("is-cropping");
        }

        function setActiveRatio(value) {
            ratioButtons.forEach((button) => {
                const active = button.dataset.cropRatio === value;
                button.classList.toggle("btn-primary", active);
                button.classList.toggle("btn-outline-secondary", !active);
            });
        }

        function openCropper(fileItem) {
            if (!cropEnabled || !fileItem || !cropSource || !window.Cropper) {
                return;
            }

            destroyCropper();
            pendingCropFileItem = fileItem;
            cropSource.src = URL.createObjectURL(fileItem.file);
            showCropPane();

            cropSource.onload = () => {
                setActiveRatio("free");
                cropper = new window.Cropper(cropSource, {
                    viewMode: 1,
                    autoCropArea: 1,
                    background: false,
                    responsive: true,
                    movable: true,
                    zoomable: true,
                    rotatable: true,
                    scalable: true,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                });
            };
        }

        if (cropCancel)
            cropCancel.addEventListener("click", () => {
                destroyCropper();
                showUploadPane();
            });

        if (cropApply)
            cropApply.addEventListener("click", async () => {
                if (!cropper || !pendingCropFileItem) return;
                const maxWidth =
                    Number(cropOutputWidth && cropOutputWidth.value) || 0;
                const canvas = cropper.getCroppedCanvas(
                    maxWidth ? { maxWidth } : undefined,
                );

                cropApply.disabled = true;
                canvas.toBlob(
                    async (blob) => {
                        cropApply.disabled = false;
                        if (!blob) return;
                        selectedFile = new File(
                            [blob],
                            pendingCropFileItem.file.name || "image.jpg",
                            { type: blob.type || "image/jpeg" },
                        );
                        if (pond) {
                            try {
                                pond.removeFile(pendingCropFileItem.id);
                                suppressNextCrop = true;
                                await pond.addFile(selectedFile);
                            } catch (e) {
                                /* keep selectedFile as the save source */
                            }
                        }
                        destroyCropper();
                        showUploadPane();
                    },
                    pendingCropFileItem.file.type || "image/jpeg",
                    0.92,
                );
            });

        ratioButtons.forEach((button) =>
            button.addEventListener("click", () => {
                if (!cropper) return;
                const ratio = button.dataset.cropRatio;
                cropper.setAspectRatio(ratio === "free" ? NaN : Number(ratio));
                setActiveRatio(ratio);
            }),
        );

        [
            ["hbImageCropZoomIn", () => cropper && cropper.zoom(0.1)],
            ["hbImageCropZoomOut", () => cropper && cropper.zoom(-0.1)],
            ["hbImageCropRotateLeft", () => cropper && cropper.rotate(-90)],
            ["hbImageCropRotateRight", () => cropper && cropper.rotate(90)],
            ["hbImageCropReset", () => cropper && cropper.reset()],
        ].forEach(([id, handler]) => {
            const button = document.getElementById(id);
            if (button) button.addEventListener("click", handler);
        });

        if (doneBtn)
            doneBtn.addEventListener("click", () => {
                const files = pond ? pond.getFiles() : [];
                const native =
                    selectedFile || (files.length ? files[0].file : null);
                const cb = onDone;
                hideModal(modalEl);
                if (cb) cb(native);
            });
        if (modalEl)
            modalEl.addEventListener("hidden.bs.modal", () => {
                destroyCropper();
                destroyPond();
                selectedFile = null;
                showUploadPane();
            });

        return {
            open(opts, callback) {
                if (!window.FilePond) return;
                onDone = callback;
                const isLottie = !!opts.isLottie;
                const existing = opts.existing || null;
                cropEnabled = !!opts.allowCrop && !isLottie && !!window.Cropper;
                selectedFile = null;
                const input = document.getElementById("hbImagePond");
                destroyPond();
                destroyCropper();
                showUploadPane();
                pond = window.FilePond.create(input, {
                    allowMultiple: false,
                    credits: false,
                    storeAsFile: true,
                    allowImagePreview: !isLottie,
                    acceptedFileTypes: isLottie
                        ? ["application/json"]
                        : ["image/*"],
                    maxFileSize: "10MB",
                    fileValidateTypeDetectType: (source, type) =>
                        new Promise((resolve) => {
                            if (
                                isLottie &&
                                !type &&
                                /\.json$/i.test(source.name || "")
                            ) {
                                resolve("application/json");
                                return;
                            }
                            resolve(type);
                        }),
                    files:
                        existing &&
                        typeof existing === "string" &&
                        existing.startsWith("http")
                            ? [
                                  {
                                      source: existing,
                                      options: { type: "remote" },
                                  },
                              ]
                            : existing
                              ? [
                                    {
                                        source: existing,
                                        options: { type: "local" },
                                    },
                                ]
                              : [],
                    server: {
                        load: (src, load, error, progress, abort) => {
                            fetch(src, { mode: "cors" })
                                .then((r) => {
                                    if (!r.ok)
                                        throw new Error(
                                            "Failed to fetch image",
                                        );
                                    return r.blob();
                                })
                                .then(load)
                                .catch((err) => {
                                    error(
                                        err.message || "Could not load image",
                                    );
                                });
                        },
                    },
                });
                if (cropEnabled) {
                    pond.on("addfile", (error, fileItem) => {
                        if (suppressNextCrop) {
                            suppressNextCrop = false;
                            return;
                        }
                        if (!error && fileItem && fileItem.file) {
                            selectedFile = fileItem.file;
                            openCropper(fileItem);
                        }
                    });
                }
                showModal(modalEl);
            },
        };
    })();

    /* ====================================================================
       One builder instance, scoped to its root element.
       ==================================================================== */
    function initHomeBuilder(root) {
        const cfg = {
            showUrl: root.dataset.showUrl,
            saveUrl: root.dataset.saveUrl,
            productSearch: root.dataset.productSearch,
            categorySearch: root.dataset.categorySearch,
            brandSearch: root.dataset.brandSearch,
            zoneSearch: root.dataset.zoneSearch || root.dataset.marketSearch,
            canEdit: root.dataset.canEdit === "1",
        };
        const PLATFORM = root.dataset.platform || "app";
        const ALLOWED_TYPES = safeJson(root.dataset.sectionTypes, null);
        const TYPES = TYPE.filter(
            (t) => !ALLOWED_TYPES || ALLOWED_TYPES.indexOf(t[0]) !== -1,
        );
        const LBL = safeJson(root.dataset.labels, {});
        const L = (k, fallback) => (LBL[k] != null ? LBL[k] : fallback);

        const sectionsEl = root.querySelector('[data-role="sections"]');
        const editorEl = root.querySelector('[data-role="editor"]');
        const typePickerEl = root.querySelector('[data-role="type-picker"]');

        const state = {
            platform: PLATFORM,
            scope_type: "global",
            scope_id: null,
            zone_id: null,
            is_active: true,
            sections: [],
            preview_stories: [],
            preview_reels: [],
        };
        let sel = -1;
        let isDirty = false;
        let simZoneId = null;
        let simTheme = localStorage.getItem("hb_sim_theme") || "light";
        let scopeCatTs = null;
        let scopeZoneTs = null;

        function syncCatPillIcons() {
            root.querySelectorAll('[data-role="cat-pill"]').forEach((p) => {
                const isActive = p.classList.contains("active");
                const iconUrl = isActive
                    ? p.dataset.catActiveIcon || p.dataset.catIcon
                    : p.dataset.catIcon || p.dataset.catActiveIcon;
                const imgEl = p.querySelector(
                    ".hb-phone-cat-icon img, .hb-web-cat-pill-icon img",
                );
                if (imgEl && iconUrl) {
                    imgEl.src = iconUrl;
                }
            });
        }

        let historyStack = [];
        let historyPointer = -1;
        let isPushingHistory = false;
        let focusedPlayRow = null;
        const disclosureState = new Map();

        const undoBtn = root.querySelector('[data-role="undo"]');
        const redoBtn = root.querySelector('[data-role="redo"]');
        const draftBadgeEl = root.querySelector('[data-role="draft-badge"]');
        const openRevisionsBtn = root.querySelector(
            '[data-role="open-revisions"]',
        );

        function updateHistoryButtons() {
            if (undoBtn) undoBtn.disabled = historyPointer <= 0;
            if (redoBtn)
                redoBtn.disabled = historyPointer >= historyStack.length - 1;
        }

        function updateDraftState(dirty) {
            isDirty = !!dirty;
            if (draftBadgeEl) {
                draftBadgeEl.textContent = isDirty
                    ? "Draft (Unsaved)"
                    : "Saved";
                draftBadgeEl.className =
                    "badge ms-2 " +
                    (isDirty ? "bg-warning-lt is-draft" : "bg-secondary-lt");
            }
        }

        function pushHistory() {
            if (isPushingHistory) return;
            const snapshot = JSON.stringify(state.sections);
            if (
                historyPointer >= 0 &&
                historyStack[historyPointer] === snapshot
            )
                return;

            historyStack = historyStack.slice(0, historyPointer + 1);
            historyStack.push(snapshot);
            historyPointer = historyStack.length - 1;
            updateHistoryButtons();
        }

        function undo() {
            if (historyPointer <= 0) return;
            historyPointer--;
            isPushingHistory = true;
            state.sections = JSON.parse(historyStack[historyPointer]);
            isPushingHistory = false;
            updateHistoryButtons();
            updateDraftState(true);
            renderSections();
            renderEditor();
            renderLivePreview();
            Toast.fire({ icon: "info", title: "Undo action applied" });
        }

        function redo() {
            if (historyPointer >= historyStack.length - 1) return;
            historyPointer++;
            isPushingHistory = true;
            state.sections = JSON.parse(historyStack[historyPointer]);
            isPushingHistory = false;
            updateHistoryButtons();
            updateDraftState(true);
            renderSections();
            renderEditor();
            renderLivePreview();
            Toast.fire({ icon: "info", title: "Redo action applied" });
        }

        function markDirty() {
            updateDraftState(true);
            pushHistory();
            renderLivePreview();
        }

        if (undoBtn) undoBtn.addEventListener("click", undo);
        if (redoBtn) redoBtn.addEventListener("click", redo);

        // Global Keyboard Shortcuts (Ctrl+Z / Cmd+Z -> Undo, Ctrl+Y / Cmd+Y / Ctrl+Shift+Z -> Redo)
        document.addEventListener("keydown", (e) => {
            if (
                e.target.tagName === "INPUT" ||
                e.target.tagName === "TEXTAREA" ||
                e.target.isContentEditable
            )
                return;
            if (e.ctrlKey || e.metaKey) {
                if (e.key === "z" || e.key === "Z") {
                    if (e.shiftKey) {
                        e.preventDefault();
                        redo();
                    } else {
                        e.preventDefault();
                        undo();
                    }
                } else if (e.key === "y" || e.key === "Y") {
                    e.preventDefault();
                    redo();
                }
            }
        });

        // [Play Image Navigation Buttons] Delegated click handler for Play Image scroll left/right buttons
        document.addEventListener("click", (e) => {
            const scrollBtn = e.target.closest(".hb-play-scroll-nav-btn");
            if (!scrollBtn) return;
            e.preventDefault();
            e.stopPropagation();

            const parentContainer = scrollBtn.closest(".hb-play-container");
            if (!parentContainer) return;

            const scrollTarget = parentContainer.querySelector(
                ".hb-live-play-scroll",
            );
            if (!scrollTarget) return;

            const dir = scrollBtn.dataset.scrollDir === "left" ? -1 : 1;
            const distance = Math.max(
                200,
                Math.floor(scrollTarget.clientWidth * 0.75),
            );
            scrollTarget.scrollBy({ left: dir * distance, behavior: "smooth" });
        });

        // [Play Image Mouse Drag Scroll] Enable drag-to-scroll on Play Image scroll containers
        let isPlayRowMouseDown = false;
        let playRowStartX = 0;
        let playRowScrollLeft = 0;
        let activePlayScrollTarget = null;

        document.addEventListener("mousedown", (e) => {
            const container = e.target.closest(".hb-live-play-scroll");
            if (!container || e.target.closest(".hb-play-scroll-nav-btn"))
                return;
            isPlayRowMouseDown = true;
            activePlayScrollTarget = container;
            playRowStartX = e.pageX - container.offsetLeft;
            playRowScrollLeft = container.scrollLeft;
            container.style.cursor = "grabbing";
        });

        document.addEventListener("mouseleave", () => {
            if (activePlayScrollTarget)
                activePlayScrollTarget.style.cursor = "";
            isPlayRowMouseDown = false;
            activePlayScrollTarget = null;
        });

        document.addEventListener("mouseup", () => {
            if (activePlayScrollTarget)
                activePlayScrollTarget.style.cursor = "";
            isPlayRowMouseDown = false;
            activePlayScrollTarget = null;
        });

        document.addEventListener("mousemove", (e) => {
            if (!isPlayRowMouseDown || !activePlayScrollTarget) return;
            e.preventDefault();
            const x = e.pageX - activePlayScrollTarget.offsetLeft;
            const walk = (x - playRowStartX) * 1.5;
            activePlayScrollTarget.scrollLeft = playRowScrollLeft - walk;
        });

        // [Play Image Bullet Dot Click] Tapping on bullet dots scrolls directly to the target item
        document.addEventListener("click", (e) => {
            const bullet = e.target.closest(".hb-play-bullet-dot");
            if (!bullet) return;
            e.preventDefault();
            e.stopPropagation();

            const parentContainer = bullet.closest(".hb-play-container");
            if (!parentContainer) return;

            const scrollTarget = parentContainer.querySelector(
                ".hb-live-play-scroll",
            );
            if (!scrollTarget) return;

            const bulletIdx = parseInt(bullet.dataset.bulletIdx, 10) || 0;
            const items = scrollTarget.querySelectorAll(".hb-live-play-item");
            if (items[bulletIdx]) {
                const targetLeft =
                    items[bulletIdx].offsetLeft - scrollTarget.offsetLeft;
                scrollTarget.scrollTo({ left: targetLeft, behavior: "smooth" });
            }

            const bullets = parentContainer.querySelectorAll(
                ".hb-play-bullet-dot",
            );
            bullets.forEach((b, idx) => {
                if (idx === bulletIdx) {
                    b.classList.remove("bg-secondary");
                    b.classList.add("bg-primary");
                } else {
                    b.classList.remove("bg-primary");
                    b.classList.add("bg-secondary");
                }
            });
        });

        // [Play Image Bullet Highlight Sync] Update active bullet dot styling when container scrolls
        document.addEventListener(
            "scroll",
            (e) => {
                const scrollTarget =
                    e.target &&
                    e.target.classList &&
                    e.target.classList.contains("hb-live-play-scroll")
                        ? e.target
                        : null;
                if (!scrollTarget) return;

                const parentContainer =
                    scrollTarget.closest(".hb-play-container");
                if (!parentContainer) return;

                const bullets = parentContainer.querySelectorAll(
                    ".hb-play-bullet-dot",
                );
                if (!bullets.length) return;

                const items =
                    scrollTarget.querySelectorAll(".hb-live-play-item");
                if (!items.length) return;

                let activeIdx = 0;
                const currentScroll = scrollTarget.scrollLeft;

                items.forEach((item, idx) => {
                    const itemLeft = item.offsetLeft - scrollTarget.offsetLeft;
                    if (currentScroll >= itemLeft - item.clientWidth / 2) {
                        activeIdx = idx;
                    }
                });

                bullets.forEach((b, idx) => {
                    if (idx === activeIdx) {
                        b.classList.remove("bg-secondary");
                        b.classList.add("bg-primary");
                    } else {
                        b.classList.remove("bg-primary");
                        b.classList.add("bg-secondary");
                    }
                });
            },
            true,
        );

        // [Play Image Auto-Scroll Timers] Manage periodic scrolling for auto_scroll rows
        let playImageAutoScrollTimers = [];

        function clearPlayImageAutoScrollTimers() {
            playImageAutoScrollTimers.forEach((t) => clearInterval(t));
            playImageAutoScrollTimers = [];
        }

        function startPlayImageAutoScrollTimers(container) {
            clearPlayImageAutoScrollTimers();

            if (!container) return;

            container
                .querySelectorAll(
                    '.hb-live-play-scroll[data-auto-scroll="true"]',
                )
                .forEach((scrollTarget) => {
                    const timeSec = parseNum(
                        scrollTarget.dataset.autoScrollTime,
                        4,
                    );
                    const delayMs = Math.max(1, timeSec) * 1000;

                    const timer = setInterval(() => {
                        if (
                            !scrollTarget ||
                            !document.body.contains(scrollTarget) ||
                            scrollTarget.getAttribute("data-auto-scroll") !==
                                "true"
                        ) {
                            clearInterval(timer);
                            return;
                        }

                        const maxScroll =
                            scrollTarget.scrollWidth - scrollTarget.clientWidth;
                        if (maxScroll <= 5) return;

                        const firstItem =
                            scrollTarget.querySelector(".hb-live-play-item");
                        const itemWidth = firstItem
                            ? firstItem.clientWidth
                            : 200;
                        const gap = 12;
                        const nextLeft =
                            scrollTarget.scrollLeft + itemWidth + gap;

                        if (nextLeft >= maxScroll + 5) {
                            scrollTarget.scrollTo({
                                left: 0,
                                behavior: "smooth",
                            });
                        } else {
                            scrollTarget.scrollTo({
                                left: nextLeft,
                                behavior: "smooth",
                            });
                        }
                    }, delayMs);

                    playImageAutoScrollTimers.push(timer);
                });
        }

        // Navigation Guard for Unsaved Changes
        window.addEventListener("beforeunload", (e) => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue =
                    "You have unsaved layout changes. Are you sure you want to leave?";
                return e.returnValue;
            }
        });

        /* ---------- live preview panel & renderer ---------- */
        const previewDrawerEl = root.querySelector(
            '[data-role="preview-drawer"]',
        );
        const webFrameEl = previewDrawerEl
            ? previewDrawerEl.querySelector(".hb-web-frame")
            : null;
        const builderWorkspaceEl = root.querySelector(
            '[data-role="builder-workspace"]',
        );
        const canvasPreviewWrapperEl = root.querySelector(
            '[data-role="canvas-preview-wrapper"]',
        );
        const builderCanvasEl = root.querySelector(
            '[data-role="builder-canvas"]',
        );
        const builderInspectorEl = root.querySelector(
            '[data-role="builder-inspector"]',
        );
        const structurePanelEl = root.querySelector(
            '[data-role="structure-panel"]',
        );
        const structureToggleBtn = root.querySelector(
            '[data-role="structure-toggle"]',
        );
        const structureDragHandle = root.querySelector(
            '[data-role="structure-drag-handle"]',
        );

        // Structure Panel Toggle and Dragging Handler
        if (structureToggleBtn && structurePanelEl) {
            structureToggleBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                structurePanelEl.classList.toggle("is-collapsed");
            });
        }

        if (structureDragHandle && structurePanelEl && builderCanvasEl) {
            let isDragging = false;
            let startX, startY, initialLeft, initialTop;

            structureDragHandle.addEventListener("mousedown", (e) => {
                if (
                    e.target.closest(
                        "button, .btn, a, input, select, .dropdown-menu",
                    )
                )
                    return;
                isDragging = true;
                structurePanelEl.classList.add("is-dragging");
                const rect = structurePanelEl.getBoundingClientRect();
                const parentRect = builderCanvasEl.getBoundingClientRect();

                startX = e.clientX;
                startY = e.clientY;
                initialLeft = rect.left - parentRect.left;
                initialTop = rect.top - parentRect.top;

                structurePanelEl.style.bottom = "auto";
                structurePanelEl.style.right = "auto";
                structurePanelEl.style.left = initialLeft + "px";
                structurePanelEl.style.top = initialTop + "px";

                document.body.style.userSelect = "none";
            });

            window.addEventListener("mousemove", (e) => {
                if (!isDragging) return;
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                structurePanelEl.style.left =
                    Math.max(0, initialLeft + dx) + "px";
                structurePanelEl.style.top =
                    Math.max(0, initialTop + dy) + "px";
            });

            window.addEventListener("mouseup", () => {
                if (isDragging) {
                    isDragging = false;
                    structurePanelEl.classList.remove("is-dragging");
                    document.body.style.userSelect = "";
                }
            });
        }
        if (previewDrawerEl) {
            const closeBtn = previewDrawerEl.querySelector(
                '[data-role="close-drawer"]',
            );
            if (closeBtn)
                closeBtn.addEventListener("click", () => togglePreview(false));

            const simInput = previewDrawerEl.querySelector(
                '[data-role="sim-datetime"]',
            );
            const simReset = previewDrawerEl.querySelector(
                '[data-role="reset-sim-datetime"]',
            );
            const simZoneSelect = previewDrawerEl.querySelector(
                '[data-role="sim-zone"]',
            );
            const zoomOutBtn = previewDrawerEl.querySelector(
                '[data-role="zoom-out"]',
            );
            const zoomInBtn = previewDrawerEl.querySelector(
                '[data-role="zoom-in"]',
            );
            const zoomResetBtn = previewDrawerEl.querySelector(
                '[data-role="zoom-reset"]',
            );
            const zoomValEl = previewDrawerEl.querySelector(
                '[data-role="zoom-val"]',
            );
            const deviceEl = previewDrawerEl.querySelector(
                ".hb-device-wrapper .device",
            );

            let currentZoom = parseFloat(
                localStorage.getItem("hb_preview_zoom") || "0.68",
            );

            function applyPreviewZoom(scale) {
                currentZoom = Math.min(Math.max(scale, 0.5), 1.1);
                localStorage.setItem("hb_preview_zoom", currentZoom.toFixed(2));

                if (deviceEl) {
                    deviceEl.style.transform = "scale(" + currentZoom + ")";
                    // Base device height is ~840px, default scale 0.68 -> margin-bottom -270px
                    const mb = Math.round(-840 * (1 - currentZoom));
                    deviceEl.style.marginBottom = mb + "px";
                }
                if (zoomValEl) {
                    zoomValEl.textContent = Math.round(currentZoom * 100) + "%";
                }
            }

            if (deviceEl) {
                applyPreviewZoom(currentZoom);
            }

            if (zoomInBtn) {
                zoomInBtn.addEventListener("click", () =>
                    applyPreviewZoom(currentZoom + 0.1),
                );
            }
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener("click", () =>
                    applyPreviewZoom(currentZoom - 0.1),
                );
            }
            if (zoomResetBtn) {
                zoomResetBtn.addEventListener("click", () =>
                    applyPreviewZoom(0.68),
                );
            }

            const refreshBtn = previewDrawerEl.querySelector(
                '[data-role="refresh-preview"]',
            );
            if (refreshBtn) {
                refreshBtn.addEventListener("click", () => {
                    renderLivePreview();
                    Toast.fire({ icon: "info", title: "Preview refreshed" });
                });
            }

            // Web Viewport Device Switcher (Desktop / Tablet / Mobile)
            const webDeviceSwitcher = previewDrawerEl.querySelector(
                '[data-role="web-device-switcher"]',
            );

            if (webDeviceSwitcher && webFrameEl) {
                webDeviceSwitcher.addEventListener("click", (e) => {
                    const btn = e.target.closest("[data-device]");
                    if (!btn) return;

                    webDeviceSwitcher
                        .querySelectorAll("[data-device]")
                        .forEach((b) => b.classList.remove("active"));
                    btn.classList.add("active");

                    const device = btn.getAttribute("data-device");
                    webFrameEl.classList.remove("is-tablet", "is-mobile");

                    if (device === "tablet") {
                        webFrameEl.classList.add("is-tablet");
                    } else if (device === "mobile") {
                        webFrameEl.classList.add("is-mobile");
                    }
                    updatePreviewHeaderStyle();
                    renderLivePreview();
                });
            }

            // App Device Switcher (Android Pixel 6 Pro / iPhone 14 Pro / Tablet iPad Pro Toggle)
            const appDeviceSwitcher = previewDrawerEl.querySelector(
                '[data-role="app-device-switcher"]',
            );

            function applyAppDeviceType(deviceType) {
                if (!deviceEl) return;

                deviceEl.classList.remove(
                    "device-iphone-14-pro",
                    "device-google-pixel-6-pro",
                    "device-ipad-pro",
                );

                if (deviceType === "android") {
                    deviceEl.classList.add("device-google-pixel-6-pro");
                } else if (deviceType === "tablet") {
                    deviceEl.classList.add("device-ipad-pro");
                } else {
                    deviceEl.classList.add("device-iphone-14-pro");
                }

                if (appDeviceSwitcher) {
                    appDeviceSwitcher
                        .querySelectorAll("[data-device]")
                        .forEach((b) => {
                            b.classList.toggle(
                                "active",
                                b.getAttribute("data-device") === deviceType,
                            );
                        });
                }
                localStorage.setItem("hb_preview_app_device", deviceType);
            }

            if (appDeviceSwitcher) {
                appDeviceSwitcher.addEventListener("click", (e) => {
                    const btn = e.target.closest("[data-device]");
                    if (!btn) return;
                    const selectedDevice =
                        btn.getAttribute("data-device") || "iphone";
                    applyAppDeviceType(selectedDevice);
                });

                const savedAppDevice =
                    localStorage.getItem("hb_preview_app_device") || "iphone";
                applyAppDeviceType(savedAppDevice);
            }

            // Draggable Mobile Device Frame (App Platform Only)
            const deviceWrapperEl =
                previewDrawerEl.querySelector(".hb-device-wrapper");
            if (deviceEl && deviceWrapperEl) {
                let isDraggingDevice = false;
                let devStartX,
                    devStartY,
                    devInitialLeft = 0,
                    devInitialTop = 0;

                deviceEl.addEventListener("mousedown", (e) => {
                    // Do not drag if clicking inside interactive phone content
                    if (
                        e.target.closest(
                            ".device-screen, button, a, input, select, [data-role='cat-pill'], .hb-phone-header",
                        )
                    )
                        return;
                    isDraggingDevice = true;
                    deviceEl.classList.add("is-dragging");

                    devStartX = e.clientX;
                    devStartY = e.clientY;

                    const computedStyle = window.getComputedStyle(deviceEl);
                    const matrix = new DOMMatrixReadOnly(
                        computedStyle.transform,
                    );
                    devInitialLeft = parseFloat(deviceEl.style.left || "0");
                    devInitialTop = parseFloat(deviceEl.style.top || "0");

                    document.body.style.userSelect = "none";
                });

                window.addEventListener("mousemove", (e) => {
                    if (!isDraggingDevice) return;
                    const dx = e.clientX - devStartX;
                    const dy = e.clientY - devStartY;
                    deviceEl.style.left = devInitialLeft + dx + "px";
                    deviceEl.style.top = devInitialTop + dy + "px";
                });

                window.addEventListener("mouseup", () => {
                    if (isDraggingDevice) {
                        isDraggingDevice = false;
                        deviceEl.classList.remove("is-dragging");
                        document.body.style.userSelect = "";
                    }
                });
            }

            // Clean Preview Mode Toggle (Preview vs Edit Mode)
            const cleanPreviewToggleBtn = previewDrawerEl.querySelector(
                '[data-role="toggle-clean-preview"]',
            );
            const previewStatusEl = previewDrawerEl.querySelector(
                '[data-role="preview-status"]',
            );
            const statusTextEl = previewDrawerEl.querySelector(
                '[data-role="status-text"]',
            );
            const modeIconPreview = previewDrawerEl.querySelector(
                '[data-role="mode-icon-preview"]',
            );
            const modeIconEdit = previewDrawerEl.querySelector(
                '[data-role="mode-icon-edit"]',
            );
            const modeTextEl = previewDrawerEl.querySelector(
                '[data-role="mode-text"]',
            );

            let isCleanPreviewMode = false;

            function applyCleanPreviewModeState() {
                root.classList.toggle("hb-is-preview-mode", isCleanPreviewMode);

                if (cleanPreviewToggleBtn) {
                    cleanPreviewToggleBtn.classList.toggle(
                        "btn-primary",
                        isCleanPreviewMode,
                    );
                    cleanPreviewToggleBtn.classList.toggle(
                        "btn-outline-primary",
                        !isCleanPreviewMode,
                    );
                }

                if (modeIconPreview && modeIconEdit) {
                    modeIconPreview.classList.toggle(
                        "d-none",
                        isCleanPreviewMode,
                    );
                    modeIconEdit.classList.toggle(
                        "d-none",
                        !isCleanPreviewMode,
                    );
                }

                if (modeTextEl) {
                    modeTextEl.textContent = isCleanPreviewMode
                        ? "Back to edit"
                        : "Preview";
                }

                if (previewStatusEl) {
                    previewStatusEl.classList.toggle(
                        "is-preview-mode",
                        isCleanPreviewMode,
                    );
                }

                if (statusTextEl) {
                    statusTextEl.textContent = isCleanPreviewMode
                        ? "Preview"
                        : "Live";
                }
            }

            if (cleanPreviewToggleBtn) {
                cleanPreviewToggleBtn.addEventListener("click", () => {
                    isCleanPreviewMode = !isCleanPreviewMode;
                    applyCleanPreviewModeState();
                });
            }

            // (Fullscreen handling is attached globally below)

            // Ensure Bootstrap Modals and Backdrops render inside the Fullscreen layer
            document.addEventListener("show.bs.modal", (e) => {
                const fsEl = document.fullscreenElement;
                if (
                    fsEl &&
                    (fsEl.contains(hbContainerEl) || fsEl === hbContainerEl)
                ) {
                    if (e.target && !fsEl.contains(e.target)) {
                        fsEl.appendChild(e.target);
                    }
                    setTimeout(() => {
                        document
                            .querySelectorAll(".modal-backdrop")
                            .forEach((bd) => {
                                if (!fsEl.contains(bd)) {
                                    fsEl.appendChild(bd);
                                }
                            });
                    }, 0);
                }
            });

            document.addEventListener("hidden.bs.modal", () => {
                const fsEl = document.fullscreenElement;
                if (fsEl) {
                    fsEl.querySelectorAll(".modal-backdrop").forEach((bd) =>
                        bd.remove(),
                    );
                }
            });

            const simThemeToggle = previewDrawerEl.querySelector(
                '[data-role="sim-theme-toggle"]',
            );
            const themeIconLight = previewDrawerEl.querySelector(
                '[data-role="theme-icon-light"]',
            );
            const themeIconDark = previewDrawerEl.querySelector(
                '[data-role="theme-icon-dark"]',
            );

            function applySimTheme() {
                if (themeIconLight)
                    themeIconLight.classList.toggle(
                        "d-none",
                        simTheme === "dark",
                    );
                if (themeIconDark)
                    themeIconDark.classList.toggle(
                        "d-none",
                        simTheme !== "dark",
                    );
                if (deviceEl) {
                    deviceEl.classList.toggle(
                        "device-dark-mode",
                        simTheme === "dark",
                    );
                }
                const webFrame = previewDrawerEl.querySelector(
                    ".hb-web-frame, [data-frame='web']",
                );
                if (webFrame) {
                    webFrame.classList.toggle(
                        "hb-web-dark",
                        simTheme === "dark",
                    );
                }
                const webScreen = previewDrawerEl.querySelector(
                    ".hb-preview-screen, [data-role='preview-screen-web']",
                );
                if (webScreen) {
                    webScreen.classList.toggle(
                        "hb-pv-web-dark",
                        simTheme === "dark",
                    );
                }
                localStorage.setItem("hb_sim_theme", simTheme);
                renderLivePreview();
            }

            if (simThemeToggle) {
                simThemeToggle.addEventListener("click", () => {
                    simTheme = simTheme === "dark" ? "light" : "dark";
                    applySimTheme();
                });
                applySimTheme();
            }

            if (simZoneSelect) {
                // Populate delivery zone options from backend search
                const zoneUrl = new URL(cfg.zoneSearch, window.location.origin);
                fetch(zoneUrl.toString(), {
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "application/json",
                    },
                })
                    .then((r) => r.json())
                    .then((rows) => {
                        (rows || []).forEach((row) => {
                            const opt = document.createElement("option");
                            opt.value = row.value != null ? row.value : row.id;
                            opt.textContent =
                                row.text ||
                                row.name ||
                                row.title ||
                                "Zone #" + opt.value;
                            simZoneSelect.appendChild(opt);
                        });
                    })
                    .catch(() => {});

                simZoneSelect.addEventListener("change", () => {
                    simZoneId = simZoneSelect.value
                        ? +simZoneSelect.value
                        : null;
                    if (state.scope_type === "zone" && simZoneId) {
                        state.zone_id = simZoneId;
                        state.scope_id = null;
                    }
                    load();
                });
            }

            if (simInput) {
                simInput.addEventListener("change", () => {
                    simDateTime = simInput.value
                        ? simInput.value.replace("T", " ") + ":00"
                        : null;
                    if (simReset)
                        simReset.classList.toggle("d-none", !simDateTime);
                    renderSections();
                });
            }
            if (simReset) {
                simReset.addEventListener("click", () => {
                    simDateTime = null;
                    if (simInput) simInput.value = "";
                    simReset.classList.add("d-none");
                    renderSections();
                });
            }

            function syncCatPillIcons() {
                previewDrawerEl
                    .querySelectorAll('[data-role="cat-pill"]')
                    .forEach((p) => {
                        const isActive = p.classList.contains("active");
                        const iconUrl = isActive
                            ? p.dataset.catActiveIcon || p.dataset.catIcon
                            : p.dataset.catIcon || p.dataset.catActiveIcon;
                        const imgEl = p.querySelector(
                            ".hb-phone-cat-icon img, .hb-web-cat-pill-icon img",
                        );
                        if (imgEl && iconUrl) {
                            imgEl.src = iconUrl;
                        }
                    });
            }

            syncCatPillIcons();
        }

        function itemPreview(it) {
            if (!it) return null;
            return (
                it._imagePreview ||
                it.image ||
                (it.itemable &&
                    (it.itemable.main_image ||
                        it.itemable.image ||
                        it.itemable.icon)) ||
                null
            );
        }

        function duplicateSection(idx) {
            const original = state.sections[idx];
            if (!original) return;
            const cloned = JSON.parse(JSON.stringify(original));
            cloned.id = null;
            cloned.title = cloned.title ? cloned.title + " (Copy)" : "";
            if (cloned.items) {
                cloned.items.forEach((it) => {
                    it.id = null;
                });
            }
            state.sections.splice(idx + 1, 0, cloned);
            markDirty();
            selectSection(idx + 1);
            Toast.fire({ icon: "success", title: "Section duplicated" });
        }

        function previewPlayImageSection(s, useMobile) {
            const rows = normalizePlayImageRows(s.config || {});
            const items = s.items || [];

            return rows
                .map((row, rowIndex) => {
                    const rowItems = items.filter(
                        (item) =>
                            parseNum(
                                item.config && item.config.row_index,
                                0,
                            ) === rowIndex,
                    );
                    const isScroll = (row.style || s.style) === "scroll";
                    const visibleItems = resolvePlayImageVisibleItems(
                        row,
                        rowItems,
                        useMobile,
                    );
                    const preview =
                        s._rowBackgroundPreview &&
                        s._rowBackgroundPreview[rowIndex];
                    const rowBg =
                        row.background_type === "image" &&
                        (preview || row.background_image)
                            ? "background-image:url(" +
                              esc(preview || row.background_image) +
                              ");"
                            : row.background_type === "color"
                              ? "background-color:" +
                                esc(row.background_color || "#ffffff") +
                                ";"
                              : "";
                    const rowStyle =
                        rowBg +
                        "padding:" +
                        rowBoxSpacing(row).padding +
                        ";margin:" +
                        rowBoxSpacing(row).margin +
                        ";border-radius:" +
                        parseNum(row.border_radius, 12) +
                        "px;";
                    const gapPx = parseNum(row.grid_gap, 12);
                    const itemWidthExpr =
                        visibleItems > 1
                            ? "calc((100% - (" +
                              gapPx +
                              "px * " +
                              (visibleItems - 1) +
                              ")) / " +
                              visibleItems +
                              ")"
                            : "100%";
                    const layoutStyle = isScroll
                        ? "--hb-play-gap:" +
                          gapPx +
                          "px;--hb-play-item-width:" +
                          itemWidthExpr +
                          ";"
                        : "--hb-play-cols:12;--hb-play-gap:" + gapPx + "px;";

                    // [Play Image Nav Buttons & Bullets] Render scroll navigation buttons and pagination bullets in live preview when enabled
                    const navButtonsHtml =
                        isScroll && isConfigTrue(row.show_scroll_buttons, true)
                            ? '<button type="button" class="btn btn-icon btn-sm btn-outline-secondary position-absolute start-0 top-50 translate-middle-y ms-1 hb-play-scroll-nav-btn" data-scroll-dir="left" title="Scroll left" style="border-radius: 50%; opacity: 0.95; width: 30px; height: 30px; padding: 0; background: #ffffff; cursor: pointer; border: 1px solid #cbd5e1; box-shadow: 0 2px 5px rgba(0,0,0,0.15); z-index: 10;"><i class="ti ti-chevron-left"></i></button>' +
                              '<button type="button" class="btn btn-icon btn-sm btn-outline-secondary position-absolute end-0 top-50 translate-middle-y me-1 hb-play-scroll-nav-btn" data-scroll-dir="right" title="Scroll right" style="border-radius: 50%; opacity: 0.95; width: 30px; height: 30px; padding: 0; background: #ffffff; cursor: pointer; border: 1px solid #cbd5e1; box-shadow: 0 2px 5px rgba(0,0,0,0.15); z-index: 10;"><i class="ti ti-chevron-right"></i></button>'
                            : "";

                    const bulletsHtml =
                        isScroll &&
                        isConfigTrue(row.show_bullets, false)
                            ? '<div class="d-flex justify-content-center gap-1 mt-2 mb-1 hb-play-bullets-container">' +
                              rowItems
                                  .map(
                                      (_, i) =>
                                          '<span class="badge badge-empty ' +
                                          (i === 0
                                              ? "bg-primary"
                                              : "bg-secondary") +
                                          ' hb-play-bullet-dot" data-bullet-idx="' +
                                          i +
                                          '" style="width: 8px; height: 8px; border-radius: 50%; padding: 0; cursor: pointer; display: inline-block; transition: background-color 0.2s;"></span>',
                                  )
                                  .join("") +
                              "</div>"
                            : "";

                    return (
                        '<div class="hb-play-container hb-play-container-' +
                        esc(row.container_width || "container") +
                        '" style="' +
                        rowStyle +
                        'position:relative;">' +
                        (row.title
                            ? '<h4 class="hb-app-play-image-title">' +
                              esc(row.title) +
                              "</h4>"
                            : "") +
                        navButtonsHtml +
                        '<div class="' +
                        (isScroll
                            ? "hb-live-play-scroll"
                            : "hb-live-play-grid") +
                        '" data-auto-scroll="' +
                        (isConfigTrue(row.auto_scroll, false) ? "true" : "false") +
                        '" data-auto-scroll-time="' +
                        esc(row.auto_scroll_time || 4) +
                        '" style="' +
                        layoutStyle +
                        '">' +
                        (rowItems.length
                            ? rowItems
                                  .map((item, itemIdx) =>
                                      previewPlayImageItem(
                                          item,
                                          row,
                                          useMobile,
                                          itemIdx,
                                          rowItems,
                                      ),
                                  )
                                  .join("")
                            : '<div class="hb-live-play-empty"></div>') +
                        "</div>" +
                        bulletsHtml +
                        "</div>"
                    );
                })
                .join("");
        }

        function previewPlayImageItem(item, row, useMobile, itemIdx, rowItems) {
            const cfg = normalizePlayImageItemConfig(item.config, 0);
            const image = useMobile
                ? itemMobilePreview(item) || itemPreview(item)
                : itemPreview(item) || itemMobilePreview(item);
            const fallbackSpan = Math.max(
                1,
                Math.round(
                    12 /
                        parseNum(
                            useMobile
                                ? row.items_to_show_mobile
                                : row.items_to_show,
                            useMobile ? 1 : 3,
                        ),
                ),
            );
            const autoSpan =
                rowItems && rowItems.length
                    ? Math.max(
                          1,
                          Math.floor(12 / Math.min(12, rowItems.length)),
                      )
                    : fallbackSpan;
            const span = resolvePlayImageItemSpan(cfg, useMobile, autoSpan);
            // Play Image ratio "auto" uses the stable banner default; "Original Size" keeps natural height.
            const targetRatio =
                cfg.aspect_ratio && cfg.aspect_ratio !== "original"
                    ? cfg.aspect_ratio === "auto"
                        ? "16:9"
                        : cfg.aspect_ratio
                    : null;
            const aspect = targetRatio
                ? "aspect-ratio:" + esc(targetRatio.replace(":", "/")) + ";"
                : "";
            const imgStyle = targetRatio
                ? "width:100%;height:100%;display:block;object-fit:" +
                  esc(cfg.object_fit || "cover") +
                  ";object-position:" +
                  esc(cfg.object_position || "center") +
                  ";"
                : "width:100%;height:auto;max-width:100%;display:block;object-fit:" +
                  esc(cfg.object_fit || "cover") +
                  ";object-position:" +
                  esc(cfg.object_position || "center") +
                  ";";

            return (
                '<div class="hb-live-play-item hb-live-play-hover-' +
                esc(row.hover_effect || "none") +
                '" data-pv-item-idx="' +
                itemIdx +
                '" style="--hb-play-span:' +
                span +
                ";border-radius:" +
                parseNum(row.border_radius, 12) +
                "px;" +
                aspect +
                '">' +
                (image
                    ? '<img src="' +
                      esc(image) +
                      '" alt="' +
                      esc(cfg.alt || item.title || "") +
                      '" style="' +
                      imgStyle +
                      '">'
                    : '<div class="hb-live-play-placeholder"><i class="ti ti-photo"></i></div>') +
                "</div>"
            );
        }

        function renderLivePreview() {
            clearPlayImageAutoScrollTimers();
            if (!previewDrawerEl) return;
            const screens =
                previewDrawerEl.querySelectorAll(".hb-preview-screen");
            if (!screens.length) return;

            if (!state.sections.length) {
                screens.forEach((scr) => {
                    scr.innerHTML =
                        '<div class="text-center text-muted p-4 fs-4"><i class="ti ti-layers-subtract fs-1 mb-2 d-block text-secondary"></i>' +
                        esc(L("noSections", "No sections in layout")) +
                        "</div>";
                });
                return;
            }

            let html = "";
            state.sections.forEach((s, idx) => {
                const sp = normalizeSpacing(s.config ? s.config.spacing : null);
                const isSel = idx === sel;
                const sectionTitle = esc(s.title || nameOf(s.type, TYPE));
                const st = getScheduleStatus(s, simDateTime);
                const isSimActive = st.key === "active";

                // Do not display scheduled or expired sections in the live preview
                if (st.key === "scheduled" || st.key === "expired") return;

                // Zone filtering for simulated zone preview
                const sectionZoneIds = s.zone_ids || s.market_ids || [];
                const isZoneMatched =
                    !simZoneId ||
                    !sectionZoneIds.length ||
                    sectionZoneIds.map(Number).includes(Number(simZoneId));
                if (!isZoneMatched) return;

                const zoneBadgeHtml = sectionZoneIds.length
                    ? '<span class="badge bg-purple-lt ms-1" style="font-size:10px;" title="Assigned Delivery Zones"><i class="ti ti-map-pin me-1"></i>Zone</span>'
                    : "";

                let contentHtml = "";

                const isMobileWeb =
                    state.platform === "web" &&
                    webFrameEl &&
                    webFrameEl.classList.contains("is-mobile");

                if (s.type === "hero") {
                    const heroBg =
                        isMobileWeb &&
                        (sectionPreview(s, "hero_media_web_mobile") ||
                            s.hero_media_web_mobile)
                            ? sectionPreview(s, "hero_media_web_mobile")
                            : sectionPreview(s, "hero_media");
                    let bgHtml = "";
                    if (s.config.media_type === "lottie" && heroBg) {
                        bgHtml =
                            '<lottie-player src="' +
                            esc(heroBg) +
                            '" background="transparent" speed="1" style="width: 100%; height: 100%; object-fit: cover;" loop autoplay></lottie-player>';
                    } else if (heroBg) {
                        bgHtml =
                            '<img src="' +
                            esc(heroBg) +
                            '" style="width: 100%; height: auto; display: block;">';
                    } else {
                        const bgStyle =
                            "background-color: " +
                            esc(s.config.card_bg_color || "#F1EFE8") +
                            ";";
                        bgHtml =
                            '<div class="hb-pv-hero-banner" style="' +
                            bgStyle +
                            '">' +
                            '<div class="fw-bold fs-3 text-truncate" style="color: ' +
                            esc(s.config.card_title_color || "#222") +
                            ';">' +
                            sectionTitle +
                            "</div>" +
                            "</div>";
                    }
                    contentHtml = bgHtml;
                    if (
                        s.config.layout === "items" &&
                        s.items &&
                        s.items.length
                    ) {
                        contentHtml +=
                            '<div class="hb-pv-hero-items" data-pv-item-container="hero" data-pv-sidx="' +
                            idx +
                            '">' +
                            s.items
                                .map(
                                    (it, itemIdx) =>
                                        '<div class="hb-pv-hero-item-card" data-pv-item-idx="' +
                                        itemIdx +
                                        '" style="background:' +
                                        esc(
                                            s.config.card_bg_color || "#f1f5f9",
                                        ) +
                                        ';"><i class="ti ti-grip-vertical hb-pv-item-drag-handle me-1"></i><span class="text-truncate d-block fw-semibold">' +
                                        esc(
                                            it.title ||
                                                it.itemable_label ||
                                                "Item",
                                        ) +
                                        "</span></div>",
                                )
                                .join("") +
                            "</div>";
                    }
                } else if (s.type === "play_image") {
                    contentHtml = previewPlayImageSection(
                        s,
                        isMobileWeb || state.platform === "app",
                    );
                } else if (s.type === "banners") {
                    const items = s.items || [];
                    const r = (s.config &&
                        s.config.spacing &&
                        s.config.spacing.border_radius) || {
                        top_left: 12,
                        top_right: 12,
                        bottom_left: 12,
                        bottom_right: 12,
                    };
                    const bannerRadiusStyle =
                        typeof r === "object" && r !== null
                            ? (r.top_left || 0) +
                              "px " +
                              (r.top_right || 0) +
                              "px " +
                              (r.bottom_right || 0) +
                              "px " +
                              (r.bottom_left || 0) +
                              "px"
                            : (r || 12) + "px";

                    if (s.style === "peek") {
                        contentHtml =
                            '<div class="hb-pv-banner-peek" data-pv-item-container="banner" data-pv-sidx="' +
                            idx +
                            '">' +
                            (items.length
                                ? items
                                      .map((it, itemIdx) => {
                                          const img =
                                              isMobileWeb &&
                                              (it._imageMobilePreview ||
                                                  it.image_web_mobile)
                                                  ? itemMobilePreview(it)
                                                  : itemPreview(it);
                                          return (
                                              '<div class="hb-pv-banner-peek-item" data-pv-item-idx="' +
                                              itemIdx +
                                              '" style="border-radius: ' +
                                              bannerRadiusStyle +
                                              ';">' +
                                              (img
                                                  ? '<img src="' +
                                                    esc(img) +
                                                    '" style="width:100%; height:100%; aspect-ratio:16/9; object-fit:cover; border-radius:inherit; display:block;">'
                                                  : '<div style="width:100%; aspect-ratio:16/9; background:#e2e8f0; border-radius:inherit;"></div>') +
                                              '<i class="ti ti-grip-vertical hb-pv-item-drag-handle position-absolute top-0 start-0 m-1 bg-white rounded p-1 shadow-sm fs-5"></i></div>'
                                          );
                                      })
                                      .join("")
                                : '<div class="hb-pv-banner-peek-item" style="border-radius:' +
                                  bannerRadiusStyle +
                                  ';"><div style="width:100%; aspect-ratio:16/9; background:#e2e8f0; border-radius:inherit;"></div></div>') +
                            "</div>";
                    } else {
                        const firstItem = items.length ? items[0] : null;
                        const firstImg = firstItem
                            ? isMobileWeb &&
                              (firstItem._imageMobilePreview ||
                                  firstItem.image_web_mobile)
                                ? itemMobilePreview(firstItem)
                                : itemPreview(firstItem)
                            : null;
                        contentHtml =
                            '<div class="hb-pv-banner-full" style="border-radius: ' +
                            bannerRadiusStyle +
                            ';">' +
                            (firstImg
                                ? '<img src="' +
                                  esc(firstImg) +
                                  '" style="width:100%; height:auto; aspect-ratio:16/9; object-fit:cover; border-radius:inherit; display:block;">'
                                : '<div style="width:100%; aspect-ratio:16/9; background:#e2e8f0; border-radius:inherit;"></div>') +
                            "</div>";
                    }
                } else if (s.type === "products") {
                    contentHtml =
                        '<div class="hb-pv-section-header">' +
                        '<div class="hb-pv-section-title-wrap">' +
                        '<div class="hb-pv-section-icon"><i class="ti ti-shopping-bag"></i></div>' +
                        '<h4 class="hb-pv-section-title">' +
                        sectionTitle +
                        "</h4>" +
                        "</div>" +
                        '<span class="hb-pv-see-all">See All</span></div>';
                    const products =
                        s.resolved_products && s.resolved_products.length
                            ? s.resolved_products
                            : (s.items || []).filter(
                                  (it) => it.purpose !== "filter",
                              );
                    let productCardsHtml = "";
                    if (products.length) {
                        productCardsHtml = products
                            .map((it, n) => {
                                const img = it.image || itemPreview(it);
                                const imgStyle = img
                                    ? "background-image:url(" +
                                      esc(img) +
                                      "); background-size:cover; background-position:center;"
                                    : "";
                                const title = esc(
                                    it.title ||
                                        it.itemable_label ||
                                        "Product " + (n + 1),
                                );
                                const priceVal = it.price
                                    ? "₹" + parseFloat(it.price).toFixed(2)
                                    : "₹99.00";
                                const origPriceVal =
                                    it.original_price &&
                                    parseFloat(it.original_price) >
                                        parseFloat(it.price || 0)
                                        ? "₹" +
                                          parseFloat(it.original_price).toFixed(
                                              2,
                                          )
                                        : "";
                                const discountBadge =
                                    it.discount_percent &&
                                    it.discount_percent > 0
                                        ? '<span class="hb-pv-discount-badge">' +
                                          it.discount_percent +
                                          "% off</span>"
                                        : "";

                                return (
                                    '<div class="hb-pv-product-card">' +
                                    '<div class="hb-pv-product-img-wrap">' +
                                    discountBadge +
                                    '<i class="ti ti-bookmark hb-pv-wishlist-icon"></i>' +
                                    '<div class="hb-pv-product-img" style="' +
                                    imgStyle +
                                    '"></div>' +
                                    "</div>" +
                                    '<div class="hb-pv-product-title">' +
                                    title +
                                    "</div>" +
                                    '<div class="hb-pv-price-row">' +
                                    '<div class="hb-pv-price-wrap">' +
                                    '<span class="hb-pv-product-price">' +
                                    priceVal +
                                    "</span>" +
                                    (origPriceVal
                                        ? '<span class="hb-pv-original-price">' +
                                          origPriceVal +
                                          "</span>"
                                        : "") +
                                    "</div>" +
                                    '<button type="button" class="hb-pv-add-btn">Add</button>' +
                                    "</div>" +
                                    "</div>"
                                );
                            })
                            .join("");
                    } else {
                        productCardsHtml = [1, 2, 3, 4, 5, 6]
                            .map(
                                (n) =>
                                    '<div class="hb-pv-product-card">' +
                                    '<div class="hb-pv-product-img-wrap">' +
                                    '<span class="hb-pv-discount-badge">10% off</span>' +
                                    '<i class="ti ti-bookmark hb-pv-wishlist-icon"></i>' +
                                    '<div class="hb-pv-product-img"></div>' +
                                    "</div>" +
                                    '<div class="hb-pv-product-title">Sample Product ' +
                                    n +
                                    "</div>" +
                                    '<div class="hb-pv-price-row">' +
                                    '<div class="hb-pv-price-wrap"><span class="hb-pv-product-price">₹448.00</span><span class="hb-pv-original-price">₹500.00</span></div>' +
                                    '<button type="button" class="hb-pv-add-btn">Add</button>' +
                                    "</div>" +
                                    "</div>",
                            )
                            .join("");
                    }
                    if (s.config && s.config.orientation === "vertical") {
                        contentHtml +=
                            '<div class="hb-pv-products-grid">' +
                            productCardsHtml +
                            "</div>";
                    } else {
                        contentHtml +=
                            '<div class="hb-pv-products-scroll">' +
                            productCardsHtml +
                            "</div>";
                    }
                } else if (s.type === "categories") {
                    contentHtml =
                        '<div class="hb-pv-section-header">' +
                        '<div class="hb-pv-section-title-wrap">' +
                        '<div class="hb-pv-section-icon"><i class="ti ti-category"></i></div>' +
                        '<h4 class="hb-pv-section-title">' +
                        sectionTitle +
                        "</h4>" +
                        "</div>" +
                        '<span class="hb-pv-see-all">See All</span></div>';
                    const categories =
                        s.resolved_categories && s.resolved_categories.length
                            ? s.resolved_categories
                            : (s.items || []).filter(
                                  (it) => it.purpose !== "filter",
                              );

                    const isCustomImage = s.style === "custom_image";
                    const layoutMode =
                        s.config && s.config.rows_layout
                            ? s.config.rows_layout
                            : isCustomImage
                              ? "grid"
                              : "row";
                    const isGrid = layoutMode === "grid" || layoutMode === "2";
                    const cols =
                        s.config && s.config.grid_columns
                            ? s.config.grid_columns
                            : isCustomImage || isGrid
                              ? "2"
                              : "4";

                    const bRadius = sp.border_radius;
                    let itemRadiusStyle = "";
                    if (typeof bRadius === "object" && bRadius !== null) {
                        itemRadiusStyle =
                            "border-radius: " +
                            (bRadius.top_left || 0) +
                            "px " +
                            (bRadius.top_right || 0) +
                            "px " +
                            (bRadius.bottom_right || 0) +
                            "px " +
                            (bRadius.bottom_left || 0) +
                            "px;";
                    } else if (typeof bRadius === "number") {
                        itemRadiusStyle = "border-radius: " + bRadius + "px;";
                    } else {
                        itemRadiusStyle = isCustomImage
                            ? "border-radius: 16px;"
                            : isGrid
                              ? "border-radius: 18px;"
                              : "border-radius: 50%;";
                    }

                    let catCardsHtml = "";
                    if (categories.length) {
                        catCardsHtml = categories
                            .map((it, n) => {
                                const img = it.image || itemPreview(it);
                                if (isCustomImage) {
                                    return (
                                        '<div class="hb-pv-custom-img-card" style="' +
                                        itemRadiusStyle +
                                        '">' +
                                        (img
                                            ? '<img src="' +
                                              esc(img) +
                                              '" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:inherit; display:block;">'
                                            : '<div class="hb-pv-custom-img-placeholder" style="width:100%; aspect-ratio:1/1; background:#e2e8f0; border-radius:inherit; display:flex; align-items:center; justify-content:center;"><i class="ti ti-photo text-muted fs-2"></i></div>') +
                                        "</div>"
                                    );
                                }
                                const title = esc(
                                    it.title ||
                                        it.itemable_label ||
                                        "Category " + (n + 1),
                                );
                                return (
                                    '<div class="hb-pv-cat-item">' +
                                    '<div class="hb-pv-cat-img-box" style="' +
                                    itemRadiusStyle +
                                    '">' +
                                    (img
                                        ? '<img src="' +
                                          esc(img) +
                                          '" alt="' +
                                          title +
                                          '">'
                                        : '<i class="ti ti-category text-muted fs-2"></i>') +
                                    "</div>" +
                                    '<span class="hb-pv-cat-name">' +
                                    title +
                                    "</span>" +
                                    "</div>"
                                );
                            })
                            .join("");
                    } else {
                        const count = isCustomImage || isGrid ? 4 : 8;
                        catCardsHtml = Array.from(
                            { length: count },
                            (_, i) => i + 1,
                        )
                            .map((n) => {
                                if (isCustomImage) {
                                    return (
                                        '<div class="hb-pv-custom-img-card" style="' +
                                        itemRadiusStyle +
                                        '"><div class="hb-pv-custom-img-placeholder" style="width:100%; aspect-ratio:1/1; background:#e2e8f0; border-radius:inherit; display:flex; align-items:center; justify-content:center;"><i class="ti ti-photo text-muted fs-2"></i></div></div>'
                                    );
                                }
                                return (
                                    '<div class="hb-pv-cat-item">' +
                                    '<div class="hb-pv-cat-img-box" style="' +
                                    itemRadiusStyle +
                                    '"><i class="ti ti-category text-muted fs-2"></i></div>' +
                                    '<span class="hb-pv-cat-name">Category ' +
                                    n +
                                    "</span>" +
                                    "</div>"
                                );
                            })
                            .join("");
                    }
                    const gridClass = isGrid
                        ? "hb-pv-categories-grid hb-pv-grid-cols hb-pv-grid-cols-" +
                          cols
                        : "hb-pv-categories-grid hb-pv-cat-single-row";
                    contentHtml +=
                        '<div class="' +
                        gridClass +
                        '">' +
                        catCardsHtml +
                        "</div>";
                } else if (s.type === "brands") {
                    contentHtml =
                        '<div class="hb-pv-section-header">' +
                        '<div class="hb-pv-section-title-wrap">' +
                        '<div class="hb-pv-section-icon"><i class="ti ti-building-store"></i></div>' +
                        '<h4 class="hb-pv-section-title">' +
                        sectionTitle +
                        "</h4>" +
                        "</div>" +
                        '<span class="hb-pv-see-all">See All</span></div>';
                    const brands =
                        s.resolved_brands && s.resolved_brands.length
                            ? s.resolved_brands
                            : (s.items || []).filter(
                                  (it) => it.purpose !== "filter",
                              );
                    const isCustomImage = s.style === "custom_image";
                    const isImageTitle = s.style === "image_title";
                    const bRadius = sp.border_radius;
                    let brandRadiusStyle = "";
                    if (typeof bRadius === "object" && bRadius !== null) {
                        brandRadiusStyle =
                            "border-radius: " +
                            (bRadius.top_left || 0) +
                            "px " +
                            (bRadius.top_right || 0) +
                            "px " +
                            (bRadius.bottom_right || 0) +
                            "px " +
                            (bRadius.bottom_left || 0) +
                            "px;";
                    } else if (typeof bRadius === "number") {
                        brandRadiusStyle = "border-radius: " + bRadius + "px;";
                    } else {
                        brandRadiusStyle = isCustomImage
                            ? "border-radius: 12px;"
                            : "border-radius: 8px;";
                    }

                    let brandCardsHtml = "";
                    if (brands.length) {
                        brandCardsHtml = brands
                            .map((b, n) => {
                                const img = b.image || itemPreview(b);
                                if (isCustomImage) {
                                    return (
                                        '<div class="hb-pv-custom-img-card" style="' +
                                        brandRadiusStyle +
                                        '">' +
                                        (img
                                            ? '<img src="' +
                                              esc(img) +
                                              '" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:inherit; display:block;">'
                                            : '<div class="hb-pv-custom-img-placeholder" style="width:100%; aspect-ratio:1/1; background:#e2e8f0; border-radius:inherit; display:flex; align-items:center; justify-content:center;"><i class="ti ti-building-store text-muted fs-2"></i></div>') +
                                        "</div>"
                                    );
                                }
                                const title = esc(
                                    b.name ||
                                        b.title ||
                                        b.itemable_label ||
                                        "Brand " + (n + 1),
                                );
                                const brandItemBox =
                                    '<div class="hb-pv-brand-item" style="' +
                                    brandRadiusStyle +
                                    '">' +
                                    (img
                                        ? '<img src="' +
                                          esc(img) +
                                          '" alt="' +
                                          title +
                                          '">'
                                        : '<i class="ti ti-building-store text-muted fs-3"></i>') +
                                    "</div>";

                                if (isImageTitle) {
                                    return (
                                        '<div class="hb-pv-brand-card">' +
                                        brandItemBox +
                                        '<span class="hb-pv-brand-name">' +
                                        title +
                                        "</span>" +
                                        "</div>"
                                    );
                                }
                                return brandItemBox;
                            })
                            .join("");
                    } else {
                        const count = isCustomImage ? 4 : 5;
                        brandCardsHtml = Array.from(
                            { length: count },
                            (_, i) => i + 1,
                        )
                            .map((n) => {
                                if (isCustomImage) {
                                    return (
                                        '<div class="hb-pv-custom-img-card" style="' +
                                        brandRadiusStyle +
                                        '"><div class="hb-pv-custom-img-placeholder" style="width:100%; aspect-ratio:1/1; background:#e2e8f0; border-radius:inherit; display:flex; align-items:center; justify-content:center;"><i class="ti ti-building-store text-muted fs-2"></i></div></div>'
                                    );
                                }
                                const brandItemBox =
                                    '<div class="hb-pv-brand-item" style="' +
                                    brandRadiusStyle +
                                    '"><i class="ti ti-building-store text-muted fs-3"></i></div>';
                                if (isImageTitle) {
                                    return (
                                        '<div class="hb-pv-brand-card">' +
                                        brandItemBox +
                                        '<span class="hb-pv-brand-name">Brand ' +
                                        n +
                                        "</span></div>"
                                    );
                                }
                                return brandItemBox;
                            })
                            .join("");
                    }
                    const layoutMode =
                        s.config && s.config.rows_layout
                            ? s.config.rows_layout
                            : isCustomImage
                              ? "grid"
                              : "row";
                    const isGrid = layoutMode === "grid" || layoutMode === "2";
                    const cols =
                        s.config && s.config.grid_columns
                            ? s.config.grid_columns
                            : isCustomImage
                              ? "2"
                              : "4";
                    const gridClass = isGrid
                        ? "hb-pv-brands-scroll hb-pv-grid-cols hb-pv-grid-cols-" +
                          cols
                        : "hb-pv-brands-scroll";
                    contentHtml +=
                        '<div class="' +
                        gridClass +
                        '">' +
                        brandCardsHtml +
                        "</div>";
                } else if (s.type === "stories") {
                    const ringColor =
                        (s.config && s.config.ring_color) || "#E1306C";
                    const showName =
                        isConfigTrue(s.config?.show_profile_name, true);
                    const limit = (s.config && s.config.limit) || 8;
                    const pool =
                        s.resolved_stories && s.resolved_stories.length
                            ? s.resolved_stories
                            : state.preview_stories || [];
                    const stories = pool.slice(0, limit);
                    const count = stories.length || Math.min(limit, 8);

                    const SAMPLE_STORY_PRESETS = [
                        {
                            name: "Fresh Bakery",
                            icon: "ti-cookie",
                            bg: "linear-gradient(135deg, #FF6B6B 0%, #FFE66D 100%)",
                        },
                        {
                            name: "Organic Farm",
                            icon: "ti-leaf",
                            bg: "linear-gradient(135deg, #4E65FF 0%, #92EFFD 100%)",
                        },
                        {
                            name: "Daily Supermart",
                            icon: "ti-shopping-bag",
                            bg: "linear-gradient(135deg, #FF9A8B 0%, #FF6A88 100%)",
                        },
                        {
                            name: "Fashion Hub",
                            icon: "ti-hanger",
                            bg: "linear-gradient(135deg, #F093FB 0%, #F5576C 100%)",
                        },
                        {
                            name: "Tech Zone",
                            icon: "ti-device-mobile",
                            bg: "linear-gradient(135deg, #5EE7DF 0%, #B490CA 100%)",
                        },
                        {
                            name: "Beauty Care",
                            icon: "ti-sparkles",
                            bg: "linear-gradient(135deg, #FA709A 0%, #FEE140 100%)",
                        },
                    ];

                    contentHtml =
                        '<div class="hb-pv-section-header">' +
                        '<div class="hb-pv-section-title-wrap">' +
                        '<div class="hb-pv-section-icon"><i class="ti ti-history"></i></div>' +
                        '<h4 class="hb-pv-section-title">' +
                        (sectionTitle || "Stories & Updates") +
                        "</h4>" +
                        "</div>" +
                        '<span class="hb-pv-see-all">View All</span></div>';

                    let storiesHtml = "";
                    if (stories.length) {
                        storiesHtml = stories
                            .map((st, n) => {
                                const preset =
                                    SAMPLE_STORY_PRESETS[
                                        n % SAMPLE_STORY_PRESETS.length
                                    ];
                                const avatarSrc =
                                    st.avatar || st.photo_url || st.image;
                                const avatarImg = avatarSrc
                                    ? '<img src="' +
                                      esc(avatarSrc) +
                                      '" class="hb-pv-story-avatar-img">'
                                    : '<div class="hb-pv-story-avatar-placeholder" style="background:' +
                                      preset.bg +
                                      ';"><i class="ti ' +
                                      preset.icon +
                                      ' text-white" style="font-size:16px;"></i></div>';
                                const name =
                                    st.display_name ||
                                    st.name ||
                                    st.username ||
                                    st.handle ||
                                    preset.name;
                                return (
                                    '<div class="hb-pv-story-circle-item">' +
                                    '<div class="hb-pv-story-ring" style="border-color:' +
                                    esc(ringColor) +
                                    ';">' +
                                    '<div class="hb-pv-story-avatar-box">' +
                                    avatarImg +
                                    "</div>" +
                                    "</div>" +
                                    (showName
                                        ? '<span class="hb-pv-story-name text-truncate">' +
                                          esc(name) +
                                          "</span>"
                                        : "") +
                                    "</div>"
                                );
                            })
                            .join("");
                    } else {
                        storiesHtml = Array.from(
                            { length: count },
                            (_, n) => {
                                const preset =
                                    SAMPLE_STORY_PRESETS[
                                        n % SAMPLE_STORY_PRESETS.length
                                    ];
                                return (
                                    '<div class="hb-pv-story-circle-item">' +
                                    '<div class="hb-pv-story-ring" style="border-color:' +
                                    esc(ringColor) +
                                    ';">' +
                                    '<div class="hb-pv-story-avatar-box">' +
                                    '<div class="hb-pv-story-avatar-placeholder" style="background:' +
                                    preset.bg +
                                    ';"><i class="ti ' +
                                    preset.icon +
                                    ' text-white" style="font-size:16px;"></i></div>' +
                                    "</div>" +
                                    "</div>" +
                                    (showName
                                        ? '<span class="hb-pv-story-name text-truncate">' +
                                          esc(preset.name) +
                                          "</span>"
                                        : "") +
                                    "</div>"
                                );
                            },
                        ).join("");
                    }

                    contentHtml +=
                        '<div class="hb-pv-stories-scroll">' +
                        storiesHtml +
                        "</div>";
                } else if (s.type === "reels") {
                    const isGrid = s.style === "grid";
                    const showTitle =
                        isConfigTrue(s.config?.show_title, true);
                    const showLikes =
                        isConfigTrue(s.config?.show_likes, true);
                    const limit = (s.config && s.config.limit) || 6;
                    const pool =
                        s.resolved_reels && s.resolved_reels.length
                            ? s.resolved_reels
                            : state.preview_reels || [];
                    const reels = pool.slice(0, limit);
                    const count = reels.length || Math.min(limit, 6);

                    const SAMPLE_REEL_PRESETS = [
                        {
                            title: "Special discounts on daily essentials ✨",
                            store: "Daily Mart",
                            likes: "1.4k",
                            bg: "linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.85) 100%), linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                        },
                        {
                            title: "Trending summer outfits in stock 👗",
                            store: "Fashion Hub",
                            likes: "2.8k",
                            bg: "linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.85) 100%), linear-gradient(135deg, #f093fb 0%, #f5576c 100%)",
                        },
                        {
                            title: "Freshly baked artisan croissants 🥐",
                            store: "Bakery Fresh",
                            likes: "950",
                            bg: "linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.85) 100%), linear-gradient(135deg, #ff9a8b 0%, #ff6a88 100%)",
                        },
                        {
                            title: "Smart electronics & accessories unboxing 🎧",
                            store: "Tech Hub",
                            likes: "3.1k",
                            bg: "linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.85) 100%), linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)",
                        },
                        {
                            title: "Organic cold-pressed juice recipes 🥤",
                            store: "Organic Farm",
                            likes: "1.1k",
                            bg: "linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.85) 100%), linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)",
                        },
                        {
                            title: "Skin care routine essential kit ✨",
                            store: "Glow Beauty",
                            likes: "4.2k",
                            bg: "linear-gradient(180deg, rgba(15,23,42,0.1) 0%, rgba(15,23,42,0.85) 100%), linear-gradient(135deg, #fa709a 0%, #fee140 100%)",
                        },
                    ];

                    contentHtml =
                        '<div class="hb-pv-section-header">' +
                        '<div class="hb-pv-section-title-wrap">' +
                        '<div class="hb-pv-section-icon"><i class="ti ti-video"></i></div>' +
                        '<h4 class="hb-pv-section-title">' +
                        (sectionTitle || "Watch & Shop") +
                        "</h4>" +
                        "</div>" +
                        '<span class="hb-pv-see-all">Watch All</span></div>';

                    let reelCardsHtml = "";
                    if (reels.length) {
                        reelCardsHtml = reels
                            .map((r, n) => {
                                const preset =
                                    SAMPLE_REEL_PRESETS[
                                        n % SAMPLE_REEL_PRESETS.length
                                    ];
                                const thumbSrc =
                                    r.thumbnail ||
                                    r.cover_url ||
                                    r.cover ||
                                    r.video_url;
                                const bgStyle = thumbSrc
                                    ? "background-image:linear-gradient(180deg, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0.85) 100%), url(" +
                                      esc(thumbSrc) +
                                      ");"
                                    : "background:" + preset.bg + ";";
                                const likesText =
                                    (r.like_count || 0) + " likes";
                                const storeName =
                                    r.store_name ||
                                    r.store ||
                                    (r.profile && r.profile.username) ||
                                    preset.store;

                                return (
                                    '<div class="hb-pv-reel-card" style="aspect-ratio:9/16; ' +
                                    bgStyle +
                                    '">' +
                                    '<div class="hb-pv-reel-top"><div class="hb-pv-reel-avatar"><i class="ti ti-video text-primary"></i></div><span class="text-truncate">' +
                                    esc(storeName) +
                                    "</span></div>" +
                                    '<div class="hb-pv-reel-play-icon"><i class="ti ti-player-play-filled"></i></div>' +
                                    '<div class="hb-pv-reel-bottom">' +
                                    (showTitle
                                        ? '<div class="hb-pv-reel-title text-truncate">' +
                                          esc(r.caption || "Reel #" + (n + 1)) +
                                          "</div>"
                                        : "") +
                                    '<div class="hb-pv-reel-stats">' +
                                    (showLikes
                                        ? '<span><i class="ti ti-heart-filled text-danger me-1"></i>' +
                                          likesText +
                                          "</span>"
                                        : "") +
                                    "</div>" +
                                    "</div>" +
                                    "</div>"
                                );
                            })
                            .join("");
                    } else {
                        reelCardsHtml = Array.from(
                            { length: count },
                            (_, n) => {
                                const preset =
                                    SAMPLE_REEL_PRESETS[
                                        n % SAMPLE_REEL_PRESETS.length
                                    ];
                                return (
                                    '<div class="hb-pv-reel-card" style="aspect-ratio:9/16; background:' +
                                    preset.bg +
                                    ';">' +
                                    '<div class="hb-pv-reel-top"><div class="hb-pv-reel-avatar"><i class="ti ti-video text-primary"></i></div><span class="text-truncate">' +
                                    esc(preset.store) +
                                    "</span></div>" +
                                    '<div class="hb-pv-reel-play-icon"><i class="ti ti-player-play-filled"></i></div>' +
                                    '<div class="hb-pv-reel-bottom">' +
                                    (showTitle
                                        ? '<div class="hb-pv-reel-title">' +
                                          esc(preset.title) +
                                          "</div>"
                                        : "") +
                                    '<div class="hb-pv-reel-stats">' +
                                    (showLikes
                                        ? '<span><i class="ti ti-heart-filled text-danger me-1"></i>' +
                                          preset.likes +
                                          "</span>"
                                        : "") +
                                    "</div>" +
                                    "</div>" +
                                    "</div>"
                                );
                            },
                        ).join("");
                    }

                    const containerClass = isGrid
                        ? "hb-pv-reels-grid"
                        : "hb-pv-reels-scroll";
                    contentHtml +=
                        '<div class="' +
                        containerClass +
                        '">' +
                        reelCardsHtml +
                        "</div>";
                }

                const sectionBgImg = sectionPreview(s, "background");
                let sectionBg = "";
                let sectionTextColor = "";
                if (
                    s.config &&
                    s.config.background_type === "image" &&
                    sectionBgImg
                ) {
                    const isHalf = s.config.background_image_style === "half";
                    if (isHalf) {
                        sectionBg =
                            "background-image:url(" +
                            esc(sectionBgImg) +
                            "); background-size:100% 50%; background-repeat:no-repeat; background-position:top center;";
                    } else {
                        sectionBg =
                            "background-image:url(" +
                            esc(sectionBgImg) +
                            "); background-size:cover; background-position:center; background-repeat:no-repeat;";
                    }
                } else if (
                    s.config &&
                    s.config.background_type === "color" &&
                    s.config.background_color
                ) {
                    const isDark = simTheme === "dark";
                    const bgColor =
                        typeof s.config.background_color === "object" &&
                        s.config.background_color !== null
                            ? isDark
                                ? s.config.background_color.dark || "#1E293B"
                                : s.config.background_color.light || "#FFFFFF"
                            : s.config.background_color;
                    if (bgColor) {
                        sectionBg = "background-color:" + esc(bgColor) + ";";
                    }
                }

                if (s.config && s.config.text_color) {
                    const isDark = simTheme === "dark";
                    const txtColor =
                        typeof s.config.text_color === "object" &&
                        s.config.text_color !== null
                            ? isDark
                                ? s.config.text_color.dark || "#F8FAFC"
                                : s.config.text_color.light || "#1E293B"
                            : s.config.text_color;
                    if (txtColor) {
                        sectionTextColor =
                            "color:" +
                            esc(txtColor) +
                            "; --hb-text-color:" +
                            esc(txtColor) +
                            "; ";
                    }
                }

                let topPad = sp.inner.top;
                let bottomPad = sp.inner.bottom;
                let sidesPad = sp.inner.sides;

                const isCardSection =
                    s.type === "products" ||
                    s.type === "categories" ||
                    s.type === "brands";
                const isVert =
                    s.type === "products"
                        ? s.config && s.config.orientation === "vertical"
                        : s.config &&
                          (s.config.rows_layout === "grid" ||
                              s.config.rows_layout === "2");

                const vGap =
                    sp.item_gap && typeof sp.item_gap === "object"
                        ? sp.item_gap.vertical
                        : sp.item_gap;
                const hGap =
                    sp.item_gap && typeof sp.item_gap === "object"
                        ? sp.item_gap.horizontal
                        : sp.item_gap;

                if (
                    s.type === "hero" ||
                    s.type === "banners" ||
                    s.type === "play_image" ||
                    isCardSection
                ) {
                    topPad = 0;
                    bottomPad = 0;
                    sidesPad = 0;
                }

                const paddingStyle =
                    "padding: " +
                    topPad +
                    "px " +
                    sidesPad +
                    "px " +
                    bottomPad +
                    "px " +
                    sidesPad +
                    "px; ";

                const r = sp.border_radius;
                let radiusStyle = "border-radius: 0px; ";
                if (isCardSection) {
                    radiusStyle = "border-radius: 0px; ";
                } else if (typeof r === "object" && r !== null) {
                    radiusStyle =
                        "border-radius: " +
                        (r.top_left || 0) +
                        "px " +
                        (r.top_right || 0) +
                        "px " +
                        (r.bottom_right || 0) +
                        "px " +
                        (r.bottom_left || 0) +
                        "px; ";
                } else {
                    radiusStyle = "border-radius: " + r + "px; ";
                }
                const gapStyle =
                    s.type === "banners"
                        ? "--hb-item-gap: 0px; "
                        : "--hb-row-gap: " +
                          (isVert ? vGap : 0) +
                          "px; --hb-col-gap: " +
                          hGap +
                          "px; --hb-item-gap: " +
                          hGap +
                          "px; ";
                const sectionSpacingStyle =
                    "margin: " +
                    sp.outer.top +
                    "px " +
                    sp.outer.right +
                    "px " +
                    sp.outer.bottom +
                    "px " +
                    sp.outer.left +
                    "px; " +
                    paddingStyle +
                    radiusStyle +
                    gapStyle;

                // Floating quick action toolbar
                const actionBar = cfg.canEdit
                    ? '<div class="hb-pv-action-bar">' +
                      '<button type="button" class="hb-pv-btn hb-pv-drag-handle" title="Drag to reorder"><i class="ti ti-grip-vertical"></i></button>' +
                      '<button type="button" class="hb-pv-btn" data-pv-act="up" ' +
                      (idx === 0 ? 'disabled style="opacity:0.3"' : "") +
                      ' title="Move Up"><i class="ti ti-arrow-up"></i></button>' +
                      '<button type="button" class="hb-pv-btn" data-pv-act="down" ' +
                      (idx === state.sections.length - 1
                          ? 'disabled style="opacity:0.3"'
                          : "") +
                      ' title="Move Down"><i class="ti ti-arrow-down"></i></button>' +
                      '<button type="button" class="hb-pv-btn" data-pv-act="toggle" title="' +
                      (s.is_active ? "Hide Section" : "Show Section") +
                      '"><i class="ti ti-' +
                      (s.is_active ? "eye" : "eye-off") +
                      '"></i></button>' +
                      '<button type="button" class="hb-pv-btn" data-pv-act="duplicate" title="Duplicate Section"><i class="ti ti-copy"></i></button>' +
                      '<button type="button" class="hb-pv-btn" data-pv-act="edit" title="Edit Section"><i class="ti ti-pencil"></i></button>' +
                      '<button type="button" class="hb-pv-btn hb-pv-btn-danger" data-pv-act="delete" title="Delete Section"><i class="ti ti-trash"></i></button>' +
                      "</div>"
                    : "";

                html +=
                    '<div class="hb-pv-section' +
                    (isSel ? " is-selected" : "") +
                    (s.is_active && isSimActive ? "" : " is-disabled") +
                    '" style="' +
                    sectionSpacingStyle +
                    sectionBg +
                    sectionTextColor +
                    '" data-pv-i="' +
                    idx +
                    '">' +
                    actionBar +
                    contentHtml +
                    "</div>";
            });

            if (!html) {
                html =
                    '<div class="text-center text-muted p-4 fs-4"><i class="ti ti-layers-subtract fs-1 mb-2 d-block text-secondary"></i>' +
                    esc(L("noSections", "No matching sections for this zone")) +
                    "</div>";
            }

            screens.forEach((screenEl) => {
                screenEl.innerHTML = html;

                // Wire quick action bar events
                screenEl
                    .querySelectorAll(".hb-pv-action-bar [data-pv-act]")
                    .forEach((btn) => {
                        btn.addEventListener("click", (e) => {
                            e.stopPropagation();
                            const card = btn.closest("[data-pv-i]");
                            if (!card) return;
                            const i = +card.dataset.pvI;
                            const act = btn.dataset.pvAct;

                            if (act === "up" && i > 0) {
                                const tmp = state.sections[i];
                                state.sections[i] = state.sections[i - 1];
                                state.sections[i - 1] = tmp;
                                sel = i - 1;
                                markDirty();
                                renderSections();
                                renderEditor();
                            } else if (
                                act === "down" &&
                                i < state.sections.length - 1
                            ) {
                                const tmp = state.sections[i];
                                state.sections[i] = state.sections[i + 1];
                                state.sections[i + 1] = tmp;
                                sel = i + 1;
                                markDirty();
                                renderSections();
                                renderEditor();
                            } else if (act === "toggle") {
                                state.sections[i].is_active =
                                    !state.sections[i].is_active;
                                markDirty();
                                renderSections();
                            } else if (act === "duplicate") {
                                duplicateSection(i);
                            } else if (act === "edit") {
                                selectSection(i);
                                const ed = root.querySelector(
                                    '[data-role="col-editor"]',
                                );
                                if (ed)
                                    ed.scrollIntoView({ behavior: "smooth" });
                            } else if (act === "delete") {
                                ConfirmModal.open(
                                    L("removeConfirm", "Remove this section?"),
                                    () => {
                                        state.sections.splice(i, 1);
                                        selectSection(
                                            Math.min(
                                                sel,
                                                state.sections.length - 1,
                                            ),
                                        );
                                        markDirty();
                                    },
                                );
                            }
                        });
                    });

                // Wire section selection click
                screenEl.querySelectorAll("[data-pv-i]").forEach((card) =>
                    card.addEventListener("click", (e) => {
                        if (
                            e.target.closest(".hb-pv-action-bar") ||
                            e.target.closest(".hb-play-scroll-nav-btn") ||
                            e.target.closest(".hb-play-bullet-dot")
                        ) {
                            return;
                        }
                        e.stopPropagation();
                        const i = +card.dataset.pvI;
                        selectSection(i);
                    }),
                );

                // Initialize Canvas Sortable on Preview Screen
                if (cfg.canEdit && window.Sortable) {
                    window.Sortable.create(screenEl, {
                        handle: ".hb-pv-drag-handle",
                        animation: 150,
                        ghostClass: "hb-pv-ghost",
                        chosenClass: "hb-pv-chosen",
                        onEnd: function () {
                            const order = Array.from(screenEl.children)
                                .filter((c) => c.hasAttribute("data-pv-i"))
                                .map((c) => +c.dataset.pvI);

                            if (!order.length) return;
                            const selObj = state.sections[sel];
                            state.sections = order.map(
                                (idx) => state.sections[idx],
                            );
                            sel = state.sections.indexOf(selObj);
                            markDirty();
                            renderSections();
                        },
                    });

                    // Initialize Item-level Sortable inside Preview screen
                    screenEl
                        .querySelectorAll("[data-pv-item-container]")
                        .forEach((itemContainer) => {
                            const sIdx = +itemContainer.dataset.pvSidx;
                            window.Sortable.create(itemContainer, {
                                handle: ".hb-pv-item-drag-handle",
                                animation: 150,
                                ghostClass: "hb-pv-ghost",
                                onEnd: function () {
                                    const itemOrder = Array.from(
                                        itemContainer.children,
                                    )
                                        .filter((c) =>
                                            c.hasAttribute("data-pv-item-idx"),
                                        )
                                        .map((c) => +c.dataset.pvItemIdx);

                                    if (
                                        !itemOrder.length ||
                                        !state.sections[sIdx]
                                    )
                                        return;
                                    state.sections[sIdx].items = itemOrder.map(
                                        (idx) =>
                                            state.sections[sIdx].items[idx],
                                    );
                                    markDirty();
                                    if (sel === sIdx) renderEditor();
                                },
                            });
                        });
                }
            });

            updatePreviewHeaderStyle();
            startPlayImageAutoScrollTimers(previewDrawerEl);
        }

        let globalAppearanceData = null;

        function fetchGlobalAppearanceData(cb) {
            const url =
                root.dataset.globalAppearanceUrl ||
                "/admin/home-builder/global-appearance";
            api("get", url)
                .then((r) => {
                    globalAppearanceData =
                        r.data && r.data.data ? r.data.data : {};
                    updatePreviewHeaderStyle();
                    if (cb) cb();
                })
                .catch(() => {
                    if (cb) cb();
                });
        }

        function updatePreviewHeaderStyle(targetCatId) {
            const phoneHeader = root.querySelector(".hb-phone-header");
            const webHeader = root.querySelector(".hb-web-header");
            if (!phoneHeader && !webHeader) return;

            const catIdToUse =
                targetCatId !== undefined
                    ? targetCatId
                    : state.scope_type === "category"
                      ? state.scope_id
                      : null;

            let pillToUse = null;
            if (catIdToUse && catIdToUse !== "all") {
                pillToUse = root.querySelector(
                    '.hb-phone-cat-item[data-cat-id="' +
                        catIdToUse +
                        '"], .hb-web-cat-pill[data-cat-id="' +
                        catIdToUse +
                        '"]',
                );
            }

            const ga = globalAppearanceData || {};

            function applyHeader(header, isWeb) {
                if (!header) return;

                let finalBgImage = "";
                let finalBgColor = "";
                let fontColor = "";
                let animUrl = "";

                if (pillToUse) {
                    const isMobileWeb =
                        isWeb &&
                        header
                            .closest(".hb-web-frame")
                            ?.classList.contains("is-mobile");
                    const bgType = pillToUse.dataset.catBgType || "none";
                    const bgColor =
                        (isWeb
                            ? pillToUse.dataset.catBgColorWeb
                            : pillToUse.dataset.catBgColorApp) ||
                        pillToUse.dataset.catBgColor ||
                        "";
                    const bgImage =
                        (isWeb
                            ? isMobileWeb &&
                              pillToUse.dataset.catBgImageWebMobile
                                ? pillToUse.dataset.catBgImageWebMobile
                                : pillToUse.dataset.catBgImageWeb
                            : pillToUse.dataset.catBgImageApp) ||
                        pillToUse.dataset.catBgImage ||
                        "";
                    const bgAnim =
                        (isWeb
                            ? pillToUse.dataset.catBgAnimWeb
                            : pillToUse.dataset.catBgAnimApp) ||
                        pillToUse.dataset.catBgAnim ||
                        "";
                    const fColor = pillToUse.dataset.catFontColor || "";

                    if (bgType === "color" && bgColor) {
                        finalBgColor = bgColor;
                    } else if (bgType === "image" && bgImage) {
                        finalBgImage = bgImage;
                    } else if (bgType === "animation" && bgAnim) {
                        animUrl = bgAnim;
                    } else if (bgImage) {
                        finalBgImage = bgImage;
                    } else if (bgColor) {
                        finalBgColor = bgColor;
                    }

                    if (fColor) fontColor = fColor;
                } else {
                    const isMobileWeb =
                        isWeb &&
                        header
                            .closest(".hb-web-frame")
                            ?.classList.contains("is-mobile");
                    const bgType = ga.backgroundType || "none";
                    const fallbackColor = isWeb ? "#ffffff" : "#0066cc";
                    const bgColor =
                        (isWeb
                            ? ga.backgroundColorWeb
                            : ga.backgroundColorApp) ||
                        ga.backgroundColor ||
                        fallbackColor;
                    const bgImage =
                        (isWeb
                            ? isMobileWeb && ga.backgroundImageWebMobile
                                ? ga.backgroundImageWebMobile
                                : ga.backgroundImageWeb || ga.backgroundImage
                            : ga.backgroundImageApp) ||
                        ga.backgroundImage ||
                        "";
                    const bgAnim =
                        (isWeb
                            ? ga.backgroundAnimationWeb
                            : ga.backgroundAnimationApp) ||
                        ga.backgroundAnimation ||
                        "";
                    const fColor = ga.fontColor || "";

                    if (bgType === "color" && bgColor) {
                        finalBgColor = bgColor;
                    } else if (bgType === "image" && bgImage) {
                        finalBgImage = bgImage;
                    } else if (bgType === "animation" && bgAnim) {
                        animUrl = bgAnim;
                    } else if (bgImage) {
                        finalBgImage = bgImage;
                    } else if (bgColor) {
                        finalBgColor = bgColor;
                    }

                    if (fColor) fontColor = fColor;
                }

                header.style.background = "";
                if (finalBgImage) {
                    header.style.backgroundImage =
                        'url("' + finalBgImage + '")';
                    header.style.backgroundSize = "cover";
                    header.style.backgroundPosition = "center";
                    header.style.backgroundRepeat = "no-repeat";
                    header.style.backgroundColor = "";
                } else if (finalBgColor) {
                    header.style.backgroundImage = "";
                    header.style.backgroundColor = finalBgColor;
                } else {
                    header.style.backgroundImage = "";
                    header.style.backgroundColor = isWeb
                        ? "#ffffff"
                        : "#0066cc";
                }

                if (fontColor) {
                    header.style.color = fontColor;
                } else {
                    header.style.color = "";
                }

                let lottieEl = header.querySelector(".hb-header-lottie-bg");
                if (animUrl) {
                    if (!lottieEl) {
                        lottieEl = document.createElement("lottie-player");
                        lottieEl.className = "hb-header-lottie-bg";
                        lottieEl.setAttribute("background", "transparent");
                        lottieEl.setAttribute("speed", "1");
                        lottieEl.setAttribute("loop", "");
                        lottieEl.setAttribute("autoplay", "");
                        lottieEl.style.cssText =
                            "position:absolute; inset:0; width:100%; height:100%; object-fit:cover; pointer-events:none; z-index:0;";
                        header.prepend(lottieEl);
                    }
                    lottieEl.setAttribute("src", animUrl);
                } else if (lottieEl) {
                    lottieEl.remove();
                }
            }

            if (phoneHeader) applyHeader(phoneHeader, false);
            if (webHeader) applyHeader(webHeader, true);
        }

        function getSectionSlug(s, idx) {
            if (!s) return null;
            const titlePart = (s.title || s.type || "section")
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "");
            return (s.id ? "s-" + s.id + "-" : "idx-" + idx + "-") + titlePart;
        }

        function updateUrlSection(s, idx) {
            if (!window.history || !window.history.replaceState) return;
            const url = new URL(window.location.href);
            if (s && idx >= 0) {
                const slug = getSectionSlug(s, idx);
                url.searchParams.set("section", slug);
            } else {
                url.searchParams.delete("section");
            }
            window.history.replaceState(null, "", url.toString());
        }

        function selectSectionFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            const target = urlParams.get("section");
            if (!target || !state.sections.length) return;

            let foundIdx = state.sections.findIndex(
                (s, idx) => getSectionSlug(s, idx) === target,
            );

            if (foundIdx === -1) {
                const idMatch = target.match(/^s-(\d+)/);
                if (idMatch) {
                    const id = Number(idMatch[1]);
                    foundIdx = state.sections.findIndex((s) => s.id === id);
                }
            }

            if (foundIdx === -1) {
                const idxMatch = target.match(/^idx-(\d+)/);
                if (idxMatch) {
                    const idx = Number(idxMatch[1]);
                    if (idx >= 0 && idx < state.sections.length) foundIdx = idx;
                }
            }

            if (foundIdx !== -1) {
                sel = foundIdx;
            }
        }

        function selectSection(i, shouldScrollPreview = true) {
            sel = i;
            if (i >= 0 && state.sections[i]) {
                updateUrlSection(state.sections[i], i);
            } else {
                updateUrlSection(null, -1);
            }
            renderSections();
            renderEditor();

            if (shouldScrollPreview && i >= 0 && previewDrawerEl) {
                setTimeout(() => {
                    const targetPreviewCard = previewDrawerEl.querySelector(
                        '.hb-pv-section[data-pv-i="' + i + '"]',
                    );
                    if (targetPreviewCard) {
                        targetPreviewCard.scrollIntoView({
                            behavior: "smooth",
                            block: "center",
                        });
                    }
                }, 50);
            }
        }

        /* ---------- load ---------- */
        function load() {
            fetchGlobalAppearanceData();
            const params = new URLSearchParams({
                platform: state.platform,
                scope_type: state.scope_type,
            });
            if (state.scope_id) params.set("scope_id", state.scope_id);
            if (state.zone_id) params.set("zone_id", state.zone_id);
            if (simZoneId) params.set("sim_zone_id", simZoneId);
            api("get", cfg.showUrl + "?" + params.toString())
                .then((r) => {
                    const d = r.data.data || {};
                    state.is_active = isConfigTrue(d.is_active, true);
                    state.preview_stories = d.preview_stories || [];
                    state.preview_reels = d.preview_reels || [];
                    state.sections = (d.sections || []).map(normalizeSection);
                    sel = -1;
                    selectSectionFromUrl();
                    isDirty = false;
                    renderSections();
                    renderEditor();
                })
                .catch((e) => {
                    console.log(e);

                    Toast.fire({
                        icon: "error",
                        title: "Failed to load layout",
                    });
                });
        }

        function parseNum(val, fallback) {
            if (val === null || val === undefined || val === "")
                return fallback;
            const n = Number(val);
            return isNaN(n) ? fallback : n;
        }

        function parseBool(val, defaultVal) {
            if (val === undefined || val === null) return defaultVal;
            if (typeof val === "boolean") return val;
            if (typeof val === "number") return val !== 0;
            if (typeof val === "string") {
                const lowered = val.toLowerCase().trim();
                return ["1", "true", "yes", "on"].includes(lowered);
            }
            return !!val;
        }

        function sectionUiKey(section) {
            if (!section._uiKey) {
                section._uiKey =
                    "section_" +
                    Date.now().toString(36) +
                    "_" +
                    Math.random().toString(36).slice(2, 8);
            }
            return section._uiKey;
        }

        function itemUiKey(item) {
            if (!item._uiKey) {
                item._uiKey =
                    "item_" +
                    Date.now().toString(36) +
                    "_" +
                    Math.random().toString(36).slice(2, 8);
            }
            return item._uiKey;
        }

        function disclosureInfo(section, kind, identifier, defaultOpen) {
            const key = sectionUiKey(section) + ":" + kind + ":" + identifier;
            const open = disclosureState.has(key)
                ? disclosureState.get(key)
                : defaultOpen;

            return {
                key,
                expanded: open ? "true" : "false",
                show: open ? " show" : "",
            };
        }

        function paddingSides(rowConfig) {
            const fallback = parseNum(rowConfig.outer_padding, 0);
            const raw = String(rowConfig.outer_padding_custom || "")
                .split("/")
                .map((value) => parseNum(value, fallback));
            const values =
                raw.length === 4
                    ? raw
                    : [fallback, fallback, fallback, fallback];

            return [
                { side: "top", value: values[0] },
                { side: "right", value: values[1] },
                { side: "bottom", value: values[2] },
                { side: "left", value: values[3] },
            ];
        }

        function rowBoxSpacing(rowConfig) {
            const values = paddingSides(rowConfig).map((entry) => entry.value);
            const padding = values
                .map((value) => Math.max(0, value) + "px")
                .join(" ");
            const margin = values
                .map((value) => Math.min(0, value) + "px")
                .join(" ");

            return { padding, margin };
        }

        function playImageOptionHtml(options, current) {
            return options
                .map(
                    (option) =>
                        '<option value="' +
                        esc(option[0]) +
                        '"' +
                        (String(option[0]) === String(current)
                            ? " selected"
                            : "") +
                        ">" +
                        esc(option[1]) +
                        "</option>",
                )
                .join("");
        }

        function resolvePlayImageVisibleItems(row, rowItems, useMobile) {
            const configured = String(
                useMobile ? row.items_to_show_mobile : row.items_to_show,
            );
            if (configured !== "auto") {
                return Math.max(1, parseNum(configured, useMobile ? 1 : 3));
            }

            const spans = (rowItems || [])
                .map((item) =>
                    resolvePlayImageItemSpan(
                        item.config || {},
                        useMobile,
                        useMobile ? 12 : 4,
                    ),
                )
                .filter((span) => span > 0);
            const averageSpan = spans.length
                ? spans.reduce((total, span) => total + span, 0) / spans.length
                : useMobile
                  ? 12
                  : 4;

            return Math.max(1, Math.min(12, 12 / averageSpan));
        }

        function resolvePlayImageItemSpan(config, useMobile, fallback) {
            const raw = String(useMobile ? config.cols_mobile : config.cols);
            if (raw === "auto") {
                return fallback;
            }

            return Math.max(1, Math.min(12, parseNum(raw, fallback)));
        }

        function scrollToPlayItemInPreview(s, itemIndex) {
            if (!s || itemIndex == null) return;
            const activeScreen =
                document.querySelector(
                    "#preview-screen-mobile:not(.d-none), #preview-screen-web:not(.d-none)",
                ) || document;
            const sectionIndex = state.sections.indexOf(s);
            if (sectionIndex < 0) return;
            const sectionEl = activeScreen.querySelector(
                '[data-pv-i="' + sectionIndex + '"]',
            );
            if (!sectionEl) return;
            const itemEl = sectionEl.querySelector(
                '[data-pv-item-idx="' + itemIndex + '"]',
            );
            if (!itemEl) return;
            const scrollContainer = sectionEl.querySelector(
                ".hb-live-play-scroll",
            );
            if (scrollContainer) {
                scrollContainer.scrollTo({
                    left: itemEl.offsetLeft - scrollContainer.offsetLeft,
                    behavior: "smooth",
                });
                const bulletsContainer = sectionEl.querySelector(
                    ".hb-play-bullets-container",
                );
                if (bulletsContainer) {
                    bulletsContainer
                        .querySelectorAll(".hb-play-bullet-dot")
                        .forEach((dot, idx) => {
                            if (idx === itemIndex) {
                                dot.classList.remove("bg-secondary");
                                dot.classList.add("bg-primary");
                            } else {
                                dot.classList.remove("bg-primary");
                                dot.classList.add("bg-secondary");
                            }
                        });
                }
            }
        }

        function normalizeSpacing(spacing) {
            const sp = spacing || {};
            const outer = sp.outer || {};
            const inner = sp.inner || {};
            const rawGap = sp.item_gap;
            const rawRadius = sp.border_radius;

            let gapObj = { vertical: 12, horizontal: 12 };
            if (
                typeof rawGap === "number" ||
                (typeof rawGap === "string" &&
                    rawGap !== "" &&
                    !isNaN(Number(rawGap)))
            ) {
                const g = parseNum(rawGap, 12);
                gapObj = { vertical: g, horizontal: g };
            } else if (rawGap && typeof rawGap === "object") {
                gapObj = {
                    vertical: parseNum(rawGap.vertical, 12),
                    horizontal: parseNum(rawGap.horizontal, 12),
                };
            }

            let radiusObj = {
                top_left: 12,
                top_right: 12,
                bottom_left: 12,
                bottom_right: 12,
            };
            if (
                typeof rawRadius === "number" ||
                (typeof rawRadius === "string" &&
                    rawRadius !== "" &&
                    !isNaN(Number(rawRadius)))
            ) {
                const r = parseNum(rawRadius, 12);
                radiusObj = {
                    top_left: r,
                    top_right: r,
                    bottom_left: r,
                    bottom_right: r,
                };
            } else if (rawRadius && typeof rawRadius === "object") {
                radiusObj = {
                    top_left: parseNum(rawRadius.top_left, 12),
                    top_right: parseNum(rawRadius.top_right, 12),
                    bottom_left: parseNum(rawRadius.bottom_left, 12),
                    bottom_right: parseNum(rawRadius.bottom_right, 12),
                };
            }

            return {
                outer: {
                    top: parseNum(outer.top, 0),
                    bottom: parseNum(outer.bottom, 0),
                    left: parseNum(outer.left, 0),
                    right: parseNum(outer.right, 0),
                },
                inner: {
                    top: parseNum(inner.top, 0),
                    bottom: parseNum(inner.bottom, 0),
                    sides: parseNum(inner.sides, 0),
                },
                item_gap: gapObj,
                border_radius: radiusObj,
            };
        }

        function defaultPlayImageRowConfig() {
            return {
                row_index: 0,
                title: "",
                style: "grid",
                container_width: "container",
                outer_padding: 0,
                outer_padding_custom: "",
                grid_gap: 12,
                background_type: "none",
                background_color: "#ffffff",
                background_image: null,
                media_key: "row_" + Date.now().toString(36),
                border_radius: 12,
                hover_effect: "zoom",
                items_to_show: 3,
                items_to_show_mobile: 1,
                show_scroll_buttons: true,
                show_bullets: false,
                auto_scroll: false,
                auto_scroll_time: 4,
            };
        }

        function normalizePlayImageRows(config) {
            const source =
                config && Array.isArray(config.rows) && config.rows.length
                    ? config.rows
                    : [defaultPlayImageRowConfig()];

            return source.map((row, index) => {
                const raw = row || {};

                return Object.assign(defaultPlayImageRowConfig(), raw, {
                    row_index: index,
                    container_width:
                        raw.container_width || raw.container || "container",
                    outer_padding: parseNum(
                        raw.outer_padding ?? raw.padding_sides,
                        0,
                    ),
                    grid_gap: parseNum(raw.grid_gap ?? raw.gap, 12),
                    border_radius: parseNum(raw.border_radius, 12),
                    items_to_show: String(
                        raw.items_to_show ?? raw.columns ?? "auto",
                    ),
                    items_to_show_mobile: String(
                        raw.items_to_show_mobile ??
                            raw.columns_mobile ??
                            "auto",
                    ),
                    // [Play Image Slider Controls] Normalize scroll row boolean flags and auto scroll time
                    show_scroll_buttons: isConfigTrue(raw.show_scroll_buttons, true),
                    show_bullets: isConfigTrue(raw.show_bullets, false),
                    auto_scroll: isConfigTrue(raw.auto_scroll, false),
                    auto_scroll_time: parseNum(raw.auto_scroll_time, 4),
                    media_key:
                        raw.media_key ||
                        "row_" + Date.now().toString(36) + "_" + index,
                });
            });
        }

        function defaultPlayImageItem(rowIndex) {
            return {
                id: null,
                purpose: "display",
                itemable_type: null,
                itemable_id: null,
                itemable_label: null,
                title: "",
                link_url: "",
                _link_type: "none",
                _targets: {},
                image: null,
                image_web_mobile: null,
                config: {
                    row_index: rowIndex,
                    media_type: "image",
                    link_type: "none",
                    cols: "auto",
                    cols_mobile: "auto",
                    aspect_ratio: "auto",
                    object_fit: "cover",
                    object_position: "center",
                    alt: "",
                },
            };
        }

        function normalizePlayImageItemConfig(config, rowIndex) {
            const raw = config || {};

            return Object.assign(defaultPlayImageItem(rowIndex).config, raw, {
                row_index: parseNum(raw.row_index, rowIndex),
                cols: String(raw.cols ?? "auto"),
                cols_mobile: String(raw.cols_mobile ?? "auto"),
            });
        }

        function normalizeSection(s) {
            const rawCfg = s.config;
            const cfg =
                rawCfg && typeof rawCfg === "object" && !Array.isArray(rawCfg)
                    ? Object.assign({}, rawCfg)
                    : {};
            cfg.spacing = normalizeSpacing(cfg.spacing);
            if (s.type === "play_image") {
                cfg.rows = normalizePlayImageRows(cfg);
            }
            if (s.type === "stories") {
                s.style = "avatar_circle";
                cfg.show_profile_name = isConfigTrue(cfg.show_profile_name, true);
            }
            if (s.type === "reels") {
                cfg.show_title = isConfigTrue(cfg.show_title, true);
                cfg.show_likes = isConfigTrue(cfg.show_likes, true);
            }
            // Ensure preselected default grid_columns ('4') is initialized when layout mode is vertical (grid)
            if (
                (s.type === "brands" || s.type === "categories") &&
                cfg.rows_layout === "grid" &&
                !cfg.grid_columns
            ) {
                cfg.grid_columns = "4";
            }
            return {
                id: s.id || null,
                type: s.type,
                title: s.title || "",
                style: s.type === "stories" ? "avatar_circle" : s.style || null,
                config: cfg,
                sort_order: s.sort_order || 0,
                is_active: isConfigTrue(s.is_active, true),
                is_global_tagged: !!s.is_global_tagged,
                start_at: s.start_at || null,
                end_at: s.end_at || null,
                zone_ids: s.zone_ids || s.market_ids || [],
                background_image: s.background_image || null,
                hero_media: s.hero_media || null,
                hero_media_web_mobile: s.hero_media_web_mobile || null,
                card_background_image: s.card_background_image || null,
                resolved_products: s.resolved_products || [],
                resolved_categories: s.resolved_categories || [],
                resolved_brands: s.resolved_brands || [],
                resolved_stories:
                    s.resolved_stories && s.resolved_stories.length
                        ? s.resolved_stories
                        : state.preview_stories || [],
                resolved_reels:
                    s.resolved_reels && s.resolved_reels.length
                        ? s.resolved_reels
                        : state.preview_reels || [],
                items: (s.items || []).map((it) => ({
                    config:
                        s.type === "play_image"
                            ? normalizePlayImageItemConfig(
                                  it.config,
                                  parseNum(it.config && it.config.row_index, 0),
                              )
                            : it.config || {},
                    id: it.id || null,
                    purpose: it.purpose || "display",
                    itemable_type: it.itemable_type || null,
                    itemable_id: it.itemable_id || null,
                    itemable_label: it.itemable_label || null,
                    title: it.title || "",
                    link_url: it.link_url || "",
                    _link_type: it.link_url
                        ? "custom"
                        : it.itemable_type || null,
                    image: it.image || null,
                    image_web_mobile: it.image_web_mobile || null,
                })),
            };
        }

        function defaultSection(type) {
            const s = {
                id: null,
                type,
                title: "",
                style: null,
                config: {
                    spacing: normalizeSpacing(null),
                },
                sort_order: 0,
                is_active: true,
                start_at: null,
                end_at: null,
                zone_ids: [],
                items: [],
                background_image: null,
                hero_media: null,
                card_background_image: null,
            };
            if (type === "hero") {
                s.style = "image_title";
                s.config = Object.assign(s.config, {
                    media_type: "image",
                    layout: "items",
                    item_source: "categories",
                    card_bg_type: "color",
                    card_bg_color: "#F1EFE8",
                    card_title_color: "#222222",
                });
            }
            if (type === "products")
                s.config = Object.assign(s.config, {
                    source: "newly_added",
                    orientation: "horizontal",
                    background_type: "none",
                    background_image_style: "full",
                });
            if (type === "categories") {
                s.style = "overlay";
                s.config = Object.assign(s.config, { source: "all" });
            }
            if (type === "brands") {
                s.style = "image_title";
                s.config = Object.assign(s.config, { source: "all" });
            }
            if (type === "banners") {
                s.style = "full";
                s.config = Object.assign(s.config, {});
            }
            if (type === "play_image") {
                s.style = "grid";
                s.config = Object.assign(s.config, {
                    rows: [defaultPlayImageRowConfig()],
                });
                s.items = [defaultPlayImageItem(0)];
            }
            if (type === "stories") {
                s.style = "avatar_circle";
                s.resolved_stories = (state.preview_stories || []).slice(0, 10);
                s.config = Object.assign(s.config, {
                    ring_color: "#E1306C",
                    limit: 10,
                    show_profile_name: true,
                });
            }
            if (type === "reels") {
                s.style = "card_slider";
                s.resolved_reels = (state.preview_reels || []).slice(0, 8);
                s.config = Object.assign(s.config, {
                    source: "trending",
                    limit: 8,
                    show_likes: true,
                    show_title: true,
                });
            }
            return s;
        }

        let simDateTime = null; // Simulation timestamp if active

        function getScheduleStatus(s, nowOverride) {
            if (!s.is_active) {
                return {
                    key: "disabled",
                    label: "Disabled",
                    class: "hb-status-disabled",
                    icon: "eye-off",
                };
            }

            const ref = nowOverride ? new Date(nowOverride) : new Date();

            if (s.start_at) {
                const start = new Date(s.start_at.replace(" ", "T"));
                if (start > ref) {
                    return {
                        key: "scheduled",
                        label: "Scheduled",
                        class: "hb-status-scheduled",
                        icon: "clock",
                    };
                }
            }

            if (s.end_at) {
                const end = new Date(s.end_at.replace(" ", "T"));
                if (end < ref) {
                    return {
                        key: "expired",
                        label: "Expired",
                        class: "hb-status-expired",
                        icon: "clock-x",
                    };
                }
            }

            return {
                key: "active",
                label: "Active",
                class: "hb-status-active",
                icon: "circle-check",
            };
        }

        /* ---------- sections list ---------- */
        function badge(s) {
            if (s.type === "products")
                return nameOf(s.config.source, PRODUCT_SOURCE);
            if (s.type === "hero")
                return s.config.layout === "items"
                    ? "With items"
                    : "Banner only";
            if (s.type === "stories") return nameOf(s.style, STYLES.stories);
            if (s.type === "reels")
                return (
                    nameOf(s.config.source || "trending", REEL_SOURCES) +
                    " • " +
                    nameOf(s.style, STYLES.reels)
                );
            if (STYLES[s.type]) return nameOf(s.style, STYLES[s.type]);
            return nameOf(s.type, TYPE);
        }

        function renderSections() {
            sectionsEl.innerHTML = "";
            if (!state.sections.length) {
                sectionsEl.innerHTML =
                    '<div class="text-center text-muted py-4 px-2 small">' +
                    '<i class="ti ti-layout-grid-add fs-1 text-secondary d-block mb-1"></i>' +
                    "<span>No sections in this layout yet.</span>" +
                    "</div>";
                renderLivePreview();
                return;
            }
            state.sections.forEach((s, i) => {
                const st = getScheduleStatus(s, simDateTime);
                const globalBadgeHtml = s.is_global_tagged
                    ? '<span class="badge bg-purple-lt hb-badge" title="Global Section Tagged for this Zone"><i class="ti ti-world me-1"></i>Global</span>'
                    : "";
                const row = document.createElement("div");
                row.className =
                    "hb-row" +
                    (i === sel ? " active" : "") +
                    (s.is_global_tagged
                        ? " border-start border-3 border-purple"
                        : "");
                row.dataset.i = i;
                row.innerHTML =
                    '<i class="ti ti-grip-vertical hb-handle"></i>' +
                    '<i class="ti ' +
                    ICON[s.type] +
                    '"></i>' +
                    '<div class="hb-row-title"><div>' +
                    esc(s.title || nameOf(s.type, TYPE)) +
                    "</div>" +
                    '<div class="d-flex align-items-center gap-1 mt-1"><span class="badge bg-secondary-lt hb-badge">' +
                    esc(badge(s)) +
                    "</span>" +
                    globalBadgeHtml +
                    '<span class="badge ' +
                    st.class +
                    ' hb-badge"><i class="ti ti-' +
                    st.icon +
                    ' me-1"></i>' +
                    st.label +
                    "</span></div></div>" +
                    '<label class="form-check form-switch m-0"><input class="form-check-input hb-enable" type="checkbox"' +
                    (s.is_active ? " checked" : "") +
                    (cfg.canEdit ? "" : " disabled") +
                    "></label>";
                row.addEventListener("click", (e) => {
                    if (e.target.classList.contains("hb-enable")) return;
                    selectSection(i);
                });
                row.querySelector(".hb-enable").addEventListener(
                    "change",
                    (e) => {
                        s.is_active = e.target.checked;
                        markDirty();
                    },
                );
                sectionsEl.appendChild(row);
            });
            initSortable();
            renderLivePreview();
        }

        function initSortable() {
            if (!cfg.canEdit || !window.Sortable) return;
            window.Sortable.create(sectionsEl, {
                handle: ".hb-handle",
                animation: 150,
                ghostClass: "hb-ghost",
                onEnd: function () {
                    const order = Array.from(sectionsEl.children).map(
                        (c) => +c.dataset.i,
                    );
                    const selObj = state.sections[sel];
                    state.sections = order.map((idx) => state.sections[idx]);
                    sel = state.sections.indexOf(selObj);
                    markDirty();
                    renderSections();
                },
            });
        }

        /* ---------- editor ---------- */
        function seg(field, options, current) {
            const name = "hbseg-" + segCounter++;
            return (
                '<div class="form-selectgroup form-selectgroup-pills hb-seg" data-seg="' +
                field +
                '">' +
                options
                    .map(
                        (o) =>
                            '<label class="form-selectgroup-item"><input type="radio" name="' +
                            name +
                            '" value="' +
                            o[0] +
                            '" class="form-selectgroup-input"' +
                            (o[0] === current ? " checked" : "") +
                            '><span class="form-selectgroup-label">' +
                            esc(o[1]) +
                            "</span></label>",
                    )
                    .join("") +
                "</div>"
            );
        }

        function preset(field, options, current) {
            return (
                '<div class="hb-preset" data-preset="' +
                field +
                '">' +
                options
                    .map(
                        (o) =>
                            '<div class="hb-preset-card ' +
                            (o[0] === current ? "active" : "") +
                            '" data-val="' +
                            o[0] +
                            '"><div class="hb-thumb"></div>' +
                            esc(o[1]) +
                            "</div>",
                    )
                    .join("") +
                "</div>"
            );
        }

        function fieldTpl(label, inner) {
            return (
                '<div class="hb-field"><label class="form-label">' +
                esc(label) +
                "</label>" +
                inner +
                "</div>"
            );
        }

        function imageSlot(kind, j, preview, nonImage, customAspect) {
            let inner;
            const aspectLabels = {
                hero_media: "16:9",
                background: "16:9",
                card_background: "16:9",
                item: "1:1",
                item_mobile: "16:9",
                row_background: "16:9",
                banner_item: "16:9",
            };
            const aspectText = customAspect || aspectLabels[kind] || "";
            const aspectTag = aspectText
                ? '<span class="hb-aspect-badge">' + aspectText + "</span>"
                : "";

            if (nonImage) {
                if (preview) {
                    inner =
                        '<lottie-player src="' +
                        esc(preview) +
                        '" background="transparent" speed="1" style="width:100%; height:100%; object-fit:contain;" loop autoplay></lottie-player>';
                } else {
                    inner =
                        '<i class="ti ti-movie fs-1 text-muted"></i><span class="fs-5 text-muted d-block mt-1">Drop JSON File</span>';
                }
            } else {
                inner = preview
                    ? '<img src="' +
                      esc(preview) +
                      '" style="width:100%; height:100%; object-fit:cover;">'
                    : '<i class="ti ti-photo text-muted fs-1"></i><span class="fs-5 text-muted d-block mt-1">Drop Image</span>';
            }

            const actionsOverlay = preview
                ? '<div class="hb-img-slot-actions">' +
                  '<button type="button" class="hb-img-action-btn" data-slot-act="replace" title="Replace file"><i class="ti ti-replace"></i></button>' +
                  '<button type="button" class="hb-img-action-btn btn-danger" data-slot-act="delete" title="Delete media"><i class="ti ti-trash"></i></button>' +
                  "</div>"
                : "";

            return (
                '<div class="hb-img-slot" data-target-kind="' +
                kind +
                '"' +
                (j != null ? ' data-j="' + j + '"' : "") +
                ">" +
                inner +
                aspectTag +
                actionsOverlay +
                "</div>"
            );
        }

        function itemPreview(it) {
            return (
                (it && it._imagePreview) ||
                (it && it.image) ||
                (it && it._imageMobilePreview) ||
                (it && it.image_web_mobile) ||
                null
            );
        }

        function itemMobilePreview(it) {
            return (
                (it && it._imageMobilePreview) ||
                (it && it.image_web_mobile) ||
                (it && it._imagePreview) ||
                (it && it.image) ||
                null
            );
        }

        function sectionPreview(s, kind) {
            return (
                (s._mediaPreview && s._mediaPreview[kind]) ||
                s[MEDIA_FIELD[kind]] ||
                null
            );
        }

        function sliderField(label, path, min, max, val, disabled) {
            return (
                '<div class="mb-2">' +
                '<div class="d-flex justify-content-between align-items-center mb-1">' +
                '<label class="form-label m-0" style="font-size:0.8rem;">' +
                esc(label) +
                "</label>" +
                '<span class="badge bg-secondary-lt font-monospace" data-spacing-val="' +
                path +
                '" style="font-size:0.75rem;">' +
                val +
                "px</span>" +
                "</div>" +
                '<input type="range" class="form-range hb-spacing-input" data-spacing-path="' +
                path +
                '" min="' +
                min +
                '" max="' +
                max +
                '" value="' +
                val +
                '"' +
                (disabled ? " disabled" : "") +
                ">" +
                "</div>"
            );
        }

        function spacingControlsHtml(s, canEditSection) {
            const sp = normalizeSpacing(s.config ? s.config.spacing : null);
            const disabled = !canEditSection;
            const isHero = s.type === "hero";
            const isBanner = s.type === "banners" || s.type === "play_image";
            const isBrands = s.type === "brands";
            const isStories = s.type === "stories";
            const isReels = s.type === "reels";
            const isCardSection =
                s.type === "products" ||
                s.type === "categories" ||
                s.type === "brands";
            const isVerticalCardSection =
                isCardSection &&
                (s.type === "products"
                    ? s.config &&
                      (s.config.orientation === "vertical" ||
                          s.config.rows_layout === "grid" ||
                          s.config.rows_layout === "vertical")
                    : s.config &&
                      (s.config.rows_layout === "grid" ||
                          s.config.rows_layout === "2" ||
                          s.config.rows_layout === "vertical" ||
                          s.config.orientation === "vertical"));

            let innerControls = "";
            if (isHero || isBanner || isCardSection || isStories || isReels) {
                innerControls = "";
            } else {
                innerControls =
                    '<div class="mb-1 pt-2 border-top"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Inner Spacing (Padding)</strong></div>' +
                    '<div class="row g-2 mb-2">' +
                    '<div class="col-6">' +
                    sliderField(
                        "Top (0-48px)",
                        "inner.top",
                        0,
                        48,
                        sp.inner.top,
                        disabled,
                    ) +
                    "</div>" +
                    '<div class="col-6">' +
                    sliderField(
                        "Bottom (0-48px)",
                        "inner.bottom",
                        0,
                        48,
                        sp.inner.bottom,
                        disabled,
                    ) +
                    "</div>" +
                    '<div class="col-12">' +
                    sliderField(
                        "Sides (0-32px)",
                        "inner.sides",
                        0,
                        32,
                        sp.inner.sides,
                        disabled,
                    ) +
                    "</div>" +
                    "</div>";
            }

            let itemGapControls = "";
            if (isCardSection) {
                if (isVerticalCardSection) {
                    itemGapControls =
                        '<div class="mb-1 pt-2 border-top"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Item Spacing (Vertical & Side)</strong></div>' +
                        '<div class="row g-2 mb-2">' +
                        '<div class="col-6">' +
                        sliderField(
                            "Top / Vertical Spacing (0-48px)",
                            "item_gap.vertical",
                            0,
                            48,
                            sp.item_gap.vertical,
                            disabled,
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        sliderField(
                            "Side Spacing (0-32px)",
                            "item_gap.horizontal",
                            0,
                            32,
                            sp.item_gap.horizontal,
                            disabled,
                        ) +
                        "</div>" +
                        "</div>";
                } else {
                    itemGapControls =
                        '<div class="mb-1 pt-2 border-top"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Item Spacing (Side)</strong></div>' +
                        '<div class="row g-2 mb-2">' +
                        '<div class="col-12">' +
                        sliderField(
                            "Side Spacing (0-32px)",
                            "item_gap.horizontal",
                            0,
                            32,
                            sp.item_gap.horizontal,
                            disabled,
                        ) +
                        "</div>" +
                        "</div>";
                }
            }

            let geometryControls = "";
            if (isHero || isBanner || isBrands) {
                const r = sp.border_radius;
                geometryControls =
                    '<div class="mb-1 pt-2 border-top"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Corner Radii (Border Radius)</strong></div>' +
                    '<div class="row g-2 mb-2">' +
                    '<div class="col-6">' +
                    sliderField(
                        "Top-Left (0-32px)",
                        "border_radius.top_left",
                        0,
                        32,
                        r.top_left,
                        disabled,
                    ) +
                    "</div>" +
                    '<div class="col-6">' +
                    sliderField(
                        "Top-Right (0-32px)",
                        "border_radius.top_right",
                        0,
                        32,
                        r.top_right,
                        disabled,
                    ) +
                    "</div>" +
                    '<div class="col-6">' +
                    sliderField(
                        "Bottom-Left (0-32px)",
                        "border_radius.bottom_left",
                        0,
                        32,
                        r.bottom_left,
                        disabled,
                    ) +
                    "</div>" +
                    '<div class="col-6">' +
                    sliderField(
                        "Bottom-Right (0-32px)",
                        "border_radius.bottom_right",
                        0,
                        32,
                        r.bottom_right,
                        disabled,
                    ) +
                    "</div>" +
                    "</div>";
            }

            return (
                '<div class="card card-sm mt-3 mb-2 bg-body-tertiary">' +
                '<div class="card-body py-2 px-3">' +
                '<h4 class="card-title mb-2 d-flex align-items-center" style="font-size:0.9rem;"><i class="ti ti-dimensions text-primary me-1"></i>Spacing & Geometry</h4>' +
                '<div class="mb-1"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Outer Spacing (Margin)</strong></div>' +
                '<div class="row g-2 mb-2">' +
                '<div class="col-6">' +
                sliderField(
                    "Top (0-64px)",
                    "outer.top",
                    0,
                    64,
                    sp.outer.top,
                    disabled,
                ) +
                "</div>" +
                '<div class="col-6">' +
                sliderField(
                    "Bottom (0-64px)",
                    "outer.bottom",
                    0,
                    64,
                    sp.outer.bottom,
                    disabled,
                ) +
                "</div>" +
                '<div class="col-6">' +
                sliderField(
                    "Left (0-32px)",
                    "outer.left",
                    0,
                    32,
                    sp.outer.left,
                    disabled,
                ) +
                "</div>" +
                '<div class="col-6">' +
                sliderField(
                    "Right (0-32px)",
                    "outer.right",
                    0,
                    32,
                    sp.outer.right,
                    disabled,
                ) +
                "</div>" +
                "</div>" +
                innerControls +
                itemGapControls +
                geometryControls +
                "</div></div>"
            );
        }

        function renderEditor() {
            editorEl.querySelectorAll("select").forEach((x) => {
                if (x.tomselect) x.tomselect.destroy();
            });

            const s = state.sections[sel];
            if (!s) {
                editorEl.innerHTML =
                    '<div class="text-muted">' +
                    esc(L("selectSection", "Select a section to edit")) +
                    "</div>";
                return;
            }

            const canEditSection = cfg.canEdit && !s.is_global_tagged;

            let h = "";

            if (s.is_global_tagged) {
                h +=
                    '<div class="alert alert-info d-flex align-items-center mb-3" role="alert">' +
                    '<i class="ti ti-info-circle fs-2 me-2"></i>' +
                    "<div>" +
                    "<strong>Global Section (Read-Only)</strong><br>" +
                    "This section is inherited from the Global Layout. To edit its contents or settings, please switch to the <strong>Global Layout</strong> tab." +
                    "</div>" +
                    "</div>";
            }

            h +=
                '<div class="d-flex align-items-center gap-2 mb-3">' +
                '<i class="ti ' +
                ICON[s.type] +
                ' fs-2"></i>' +
                '<h3 class="card-title m-0">' +
                esc(nameOf(s.type, TYPE)) +
                "</h3>" +
                '<div class="ms-auto d-flex align-items-center gap-1">' +
                '<button type="button" class="btn btn-sm btn-outline-primary" id="hb-save-preset-btn" title="Save this section configuration as a custom preset"' +
                (canEditSection ? "" : " disabled") +
                '><i class="ti ti-bookmark"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" id="hb-del-section" title="Delete this section"' +
                (canEditSection ? "" : " disabled") +
                '><i class="ti ti-trash"></i></button>' +
                "</div></div>";

            if (s.type !== "hero" && s.type !== "banners") {
                h += fieldTpl(
                    L("sectionTitle", "Section title"),
                    '<input type="text" class="form-control" id="hb-title" value="' +
                        esc(s.title) +
                        '" placeholder="' +
                        esc(L("optionalHeading", "Optional heading")) +
                        '"' +
                        (canEditSection ? "" : " disabled") +
                        ">",
                );
            }
            h += body(s);
            h += spacingControlsHtml(s, canEditSection);
            h +=
                '<div class="hb-advanced mt-3 pt-2 border-top">' +
                '<div class="hb-advanced-head text-muted" id="hb-adv-toggle"><i class="ti ti-chevron-' +
                (s._adv ? "down" : "right") +
                '"></i> ' +
                esc(L("advanced", "Advanced")) +
                "</div>" +
                (s._adv ? '<div class="mt-2">' + advBody(s) + "</div>" : "") +
                "</div>";

            editorEl.innerHTML = h;

            if (!canEditSection) {
                editorEl
                    .querySelectorAll(
                        "input, select, button:not(#hb-adv-toggle)",
                    )
                    .forEach((el) => {
                        el.disabled = true;
                    });
            }

            wireEditor(s);
        }

        function body(s) {
            if (s.type === "hero") {
                let h = fieldTpl(
                    L("background", "Background"),
                    seg("media_type", HERO_MEDIA, s.config.media_type),
                );
                if (
                    state.platform === "web" &&
                    s.config.media_type === "image"
                ) {
                    h +=
                        fieldTpl(
                            "Background image (Desktop)",
                            imageSlot(
                                "hero_media",
                                null,
                                sectionPreview(s, "hero_media"),
                                false,
                                "16:9",
                            ),
                        ) +
                        fieldTpl(
                            "Background image (Mobile Web)",
                            imageSlot(
                                "hero_media_web_mobile",
                                null,
                                sectionPreview(s, "hero_media_web_mobile"),
                                false,
                                "16:9",
                            ),
                        );
                } else {
                    h += fieldTpl(
                        s.config.media_type === "lottie"
                            ? "Animation file (JSON)"
                            : "Background image",
                        imageSlot(
                            "hero_media",
                            null,
                            sectionPreview(s, "hero_media"),
                            s.config.media_type === "lottie",
                        ),
                    );
                }
                h += fieldTpl(
                    "Layout",
                    seg("layout", HERO_LAYOUT, s.config.layout),
                );
                if (s.config.layout === "items") {
                    h +=
                        fieldTpl(
                            "Items",
                            seg(
                                "item_source",
                                ITEM_SOURCE,
                                s.config.item_source,
                            ),
                        ) +
                        fieldTpl(
                            "Item style",
                            preset("style", STYLES.hero, s.style),
                        );
                    if (s.style === "image_title") {
                        h +=
                            '<div class="card card-sm mb-3"><div class="card-body">' +
                            fieldTpl(
                                "Card background",
                                seg(
                                    "card_bg_type",
                                    [
                                        ["color", "Color"],
                                        ["image", "Image"],
                                    ],
                                    s.config.card_bg_type,
                                ),
                            ) +
                            (s.config.card_bg_type === "image"
                                ? fieldTpl(
                                      "Card background image",
                                      imageSlot(
                                          "card_background",
                                          null,
                                          sectionPreview(s, "card_background"),
                                      ),
                                  )
                                : '<div class="hb-field"><input type="color" class="form-control form-control-color" style="max-width:60px" id="hb-cardcolor" value="' +
                                  esc(s.config.card_bg_color || "#F1EFE8") +
                                  '" title="' +
                                  esc(L("background", "Background")) +
                                  '"></div>') +
                            fieldTpl(
                                "Card title color",
                                '<input type="color" class="form-control form-control-color" style="max-width:60px" id="hb-cardtitlecolor" value="' +
                                    esc(
                                        s.config.card_title_color || "#222222",
                                    ) +
                                    '" title="Card title color">',
                            ) +
                            "</div></div>";
                    }
                    h += heroItemsEditor(s);
                }
                return h;
            }
            if (s.type === "products") {
                let h = fieldTpl(
                    "Product list",
                    seg("source", PRODUCT_SOURCE, s.config.source),
                );
                h +=
                    s.config.source === "custom"
                        ? fieldTpl(
                              L("selectedProducts", "Selected products"),
                              '<select class="form-select hb-entity" data-entity="product" data-purpose="display" multiple></select>',
                          )
                        : fieldTpl(
                              L("limitCategories", "Categories (required)"),
                              '<select class="form-select hb-entity" data-entity="category" data-purpose="filter" multiple></select>',
                          );
                h += fieldTpl(
                    "Scroll",
                    preset("orientation", ORIENTATION, s.config.orientation),
                );
                return h;
            }
            if (s.type === "categories") {
                const categoryStyles =
                    s.config.source === "custom"
                        ? STYLES.categories
                        : STYLES.categories.filter(
                              (o) => o[0] !== "custom_image",
                          );

                if (
                    s.config.source !== "custom" &&
                    s.style === "custom_image"
                ) {
                    s.style = "default";
                }

                let catHtml =
                    fieldTpl(
                        "Source",
                        seg("source", SOURCE_MODE, s.config.source),
                    ) +
                    fieldTpl(
                        "Style",
                        preset("style", categoryStyles, s.style),
                    ) +
                    fieldTpl(
                        "Layout Mode",
                        preset(
                            "rows_layout",
                            CATEGORY_ROWS,
                            s.config.rows_layout || "row",
                        ),
                    );

                if (
                    state.platform === "app" &&
                    (s.config.rows_layout || "row") === "grid"
                ) {
                    // Ensure preselected default grid_columns ('4') is saved to config so it is sent via API
                    if (!s.config.grid_columns) {
                        s.config.grid_columns = "4";
                    }
                    catHtml += fieldTpl(
                        "Items Per Row",
                        preset(
                            "grid_columns",
                            GRID_COLUMNS,
                            s.config.grid_columns || "4",
                        ),
                    );
                }

                if (s.config.source === "custom") {
                    if (s.style === "custom_image") {
                        catHtml += categoryCustomItemsEditor(s);
                    } else {
                        catHtml += fieldTpl(
                            "Categories",
                            '<select class="form-select hb-entity" data-entity="category" data-purpose="display" multiple></select>',
                        );
                    }
                }
                return catHtml;
            }
            if (s.type === "brands") {
                const brandStyles =
                    s.config.source === "custom"
                        ? STYLES.brands
                        : STYLES.brands.filter((o) => o[0] !== "custom_image");

                if (
                    s.config.source !== "custom" &&
                    s.style === "custom_image"
                ) {
                    s.style = "full";
                }

                let brandHtml =
                    fieldTpl(
                        "Source",
                        seg("source", SOURCE_MODE, s.config.source),
                    ) +
                    fieldTpl("Style", preset("style", brandStyles, s.style)) +
                    fieldTpl(
                        "Layout Mode",
                        preset(
                            "rows_layout",
                            BRAND_ROWS,
                            s.config.rows_layout || "row",
                        ),
                    );

                if (
                    state.platform === "app" &&
                    (s.config.rows_layout || "row") === "grid"
                ) {
                    // Ensure preselected default grid_columns ('4') is saved to config so it is sent via API
                    if (!s.config.grid_columns) {
                        s.config.grid_columns = "4";
                    }
                    brandHtml += fieldTpl(
                        "Items Per Row",
                        preset(
                            "grid_columns",
                            GRID_COLUMNS,
                            s.config.grid_columns || "4",
                        ),
                    );
                }

                if (s.config.source === "custom") {
                    if (s.style === "custom_image") {
                        brandHtml += brandCustomItemsEditor(s);
                    } else {
                        brandHtml += fieldTpl(
                            "Brands",
                            '<select class="form-select hb-entity" data-entity="brand" data-purpose="display" multiple></select>',
                        );
                    }
                }
                return brandHtml;
            }
            if (s.type === "banners") {
                return (
                    fieldTpl(
                        "Style",
                        preset("style", STYLES.banners, s.style),
                    ) + bannerItemsEditor(s)
                );
            }
            if (s.type === "play_image") {
                return playImageRowsEditor(s);
            }
            if (s.type === "stories") {
                let storyHtml = "";

                const swatchesHtml = STORY_RING_COLORS.map(
                    (c) =>
                        '<span class="hb-story-color-swatch' +
                        ((s.config.ring_color || "#E1306C").toLowerCase() ===
                        c.toLowerCase()
                            ? " active"
                            : "") +
                        '" data-color="' +
                        c +
                        '" style="background:' +
                        c +
                        "; width:24px; height:24px; border-radius:50%; display:inline-block; cursor:pointer; margin-right:6px; border:2px solid " +
                        ((s.config.ring_color || "#E1306C").toLowerCase() ===
                        c.toLowerCase()
                            ? "#000"
                            : "transparent") +
                        ';"></span>',
                ).join("");

                storyHtml += fieldTpl(
                    "Story Ring Color",
                    '<div class="d-flex align-items-center gap-2 mb-2">' +
                        swatchesHtml +
                        '<input type="color" class="form-control form-control-color" style="max-width:50px" id="hb-story-ring-color" value="' +
                        esc(s.config.ring_color || "#E1306C") +
                        '" title="Custom ring color">' +
                        "</div>",
                );

                storyHtml += fieldTpl(
                    "Story Limit",
                    '<input type="number" class="form-control" id="hb-story-limit" min="1" max="50" value="' +
                        esc(s.config.limit || 10) +
                        '">',
                );

                storyHtml += fieldTpl(
                    "Display Options",
                    '<label class="form-check form-switch m-0">' +
                        '<input class="form-check-input" type="checkbox" id="hb-story-show-name"' +
                        (isConfigTrue(s.config.show_profile_name, true)
                            ? " checked"
                            : "") +
                        ">" +
                        '<span class="form-check-label">Show seller name</span>' +
                        "</label>",
                );

                return storyHtml;
            }
            if (s.type === "reels") {
                let reelHtml =
                    fieldTpl(
                        "Feed Source",
                        seg(
                            "source",
                            REEL_SOURCES,
                            s.config.source || "trending",
                        ),
                    ) +
                    fieldTpl(
                        "Layout Style",
                        preset("style", STYLES.reels, s.style),
                    );

                reelHtml += fieldTpl(
                    "Max Reels Limit",
                    '<input type="number" class="form-control" id="hb-reel-limit" min="1" max="50" value="' +
                        esc(s.config.limit || 8) +
                        '">',
                );

                reelHtml += fieldTpl(
                    "Display Toggles",
                    '<div class="d-flex flex-column gap-2">' +
                        '<label class="form-check form-switch m-0">' +
                        '<input class="form-check-input" type="checkbox" id="hb-reel-show-title"' +
                        (isConfigTrue(s.config.show_title, true) ? " checked" : "") +
                        ">" +
                        '<span class="form-check-label">Show reel title / caption</span>' +
                        "</label>" +
                        '<label class="form-check form-switch m-0">' +
                        '<input class="form-check-input" type="checkbox" id="hb-reel-show-likes"' +
                        (isConfigTrue(s.config.show_likes, true) ? " checked" : "") +
                        ">" +
                        '<span class="form-check-label">Show like count</span>' +
                        "</label>" +
                        "</div>",
                );

                return reelHtml;
            }
            return "";
        }

        function heroItemsEditor(s) {
            const rows = s.items
                .map(
                    (it, j) =>
                        '<div class="hb-item-row" data-j="' +
                        j +
                        '">' +
                        imageSlot("item", j, itemPreview(it)) +
                        '<select class="form-select hb-hero-item" data-entity="' +
                        SRC_TO_KEY[s.config.item_source] +
                        '"></select>' +
                        '<input type="text" class="form-control hb-item-title" value="' +
                        esc(it.title) +
                        '" placeholder="Item title" style="max-width:180px">' +
                        '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button>' +
                        "</div>",
                )
                .join("");
            const canAdd = s.items.length < 6;
            return fieldTpl(
                L("selectItems", "Select items") +
                    " (" +
                    s.items.length +
                    "/6)",
                rows +
                    '<button type="button" class="btn btn-sm btn-outline-primary hb-add-item"' +
                    (canAdd ? "" : " disabled") +
                    '><i class="ti ti-plus me-1"></i>' +
                    esc(L("addItem", "Add item")) +
                    (canAdd ? "" : " (max 6)") +
                    "</button>",
            );
        }

        function categoryCustomItemsEditor(s) {
            const rows = s.items
                .map(
                    (it, j) =>
                        '<div class="hb-item-row" data-j="' +
                        j +
                        '">' +
                        imageSlot("item", j, itemPreview(it)) +
                        '<div class="flex-fill"><select class="form-select hb-category-item" data-entity="category"></select></div>' +
                        '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button>' +
                        "</div>",
                )
                .join("");
            return fieldTpl(
                L("selectCategoryItems", "Select category items") +
                    " (" +
                    s.items.length +
                    ")",
                rows +
                    '<button type="button" class="btn btn-sm btn-outline-primary hb-add-item"><i class="ti ti-plus me-1"></i>' +
                    esc(L("addItem", "Add item")) +
                    "</button>",
            );
        }

        function brandCustomItemsEditor(s) {
            const rows = s.items
                .map(
                    (it, j) =>
                        '<div class="hb-item-row" data-j="' +
                        j +
                        '">' +
                        imageSlot("item", j, itemPreview(it)) +
                        '<div class="flex-fill"><select class="form-select hb-brand-item" data-entity="brand"></select></div>' +
                        '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button>' +
                        "</div>",
                )
                .join("");
            return fieldTpl(
                L("selectBrandItems", "Select brand items") +
                    " (" +
                    s.items.length +
                    ")",
                rows +
                    '<button type="button" class="btn btn-sm btn-outline-primary hb-add-item"><i class="ti ti-plus me-1"></i>' +
                    esc(L("addItem", "Add item")) +
                    "</button>",
            );
        }

        function currentBannerLinkType(it) {
            return (
                it._link_type ||
                (it.config && it.config.link_type) ||
                (it.link_url ? "custom" : it.itemable_type || "product")
            );
        }

        function rememberBannerTarget(it) {
            const type = currentBannerLinkType(it);
            it._targets = it._targets || {};
            it._targets[type] = {
                itemable_type: it.itemable_type || null,
                itemable_id: it.itemable_id || null,
                itemable_label: it.itemable_label || null,
                link_url: it.link_url || "",
            };
        }

        function switchBannerTarget(it, nextType) {
            const currentType = currentBannerLinkType(it);
            if (currentType === nextType) return;

            rememberBannerTarget(it);
            const saved = (it._targets || {})[nextType] || {};

            it._link_type = nextType;
            it.config = it.config || {};
            it.config.link_type = nextType;
            if (nextType === "none") {
                it.itemable_type = null;
                it.itemable_id = null;
                it.itemable_label = null;
                it.link_url = "";
                return;
            }
            if (nextType === "custom") {
                it.itemable_type = null;
                it.itemable_id = null;
                it.itemable_label = null;
                it.link_url = saved.link_url || "";
                return;
            }

            it.itemable_type = nextType;
            it.itemable_id =
                saved.itemable_type === nextType
                    ? saved.itemable_id || null
                    : null;
            it.itemable_label =
                saved.itemable_type === nextType
                    ? saved.itemable_label || null
                    : null;
            it.link_url = "";
        }

        function bannerItemsEditor(s) {
            const isWeb = state.platform === "web";
            const rows = s.items
                .map((it, j) => {
                    const linkType = currentBannerLinkType(it);
                    const imgSlots = isWeb
                        ? '<div class="d-flex flex-column gap-2" style="min-width:120px;">' +
                          '<div><span class="small text-muted d-block mb-1">Desktop (16:9)</span>' +
                          imageSlot("item", j, itemPreview(it), false, "16:9") +
                          "</div>" +
                          '<div><span class="small text-muted d-block mb-1">Mobile Web (16:9)</span>' +
                          imageSlot(
                              "item_mobile",
                              j,
                              itemMobilePreview(it),
                              false,
                              "16:9",
                          ) +
                          "</div>" +
                          "</div>"
                        : imageSlot("item", j, itemPreview(it), false, "16:9");

                    return (
                        '<div class="card card-sm mb-2 hb-banner-item" data-j="' +
                        j +
                        '"><div class="card-body d-flex align-items-start gap-2">' +
                        imgSlots +
                        '<div class="flex-fill">' +
                        seg("__linktype", LINK_TYPES, linkType).replace(
                            'data-seg="__linktype"',
                            'data-banner-linktype="' + j + '"',
                        ) +
                        '<div class="mt-2">' +
                        (linkType === "custom"
                            ? '<input type="url" class="form-control hb-banner-url" value="' +
                              esc(it.link_url) +
                              '" placeholder="https://…">'
                            : '<select class="form-select hb-banner-entity" data-entity="' +
                              linkType +
                              '"></select>') +
                        "</div></div>" +
                        '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button>' +
                        "</div></div>"
                    );
                })
                .join("");
            return fieldTpl(
                L("banners", "Banners"),
                rows +
                    '<button type="button" class="btn btn-sm btn-outline-primary hb-add-item"><i class="ti ti-plus me-1"></i>' +
                    esc(L("addItem", "Add banner")) +
                    "</button>",
            );
        }

        function playImageRowsEditor(s) {
            s.config = s.config || {};
            s.config.rows = normalizePlayImageRows(s.config);
            s.items = s.items || [];

            const rowsHtml = s.config.rows
                .map((row, rIdx) => {
                    const rowItems = s.items.filter(
                        (item) =>
                            parseNum(
                                item.config && item.config.row_index,
                                0,
                            ) === rIdx,
                    );
                    const backgroundPreview =
                        (s._rowBackgroundPreview &&
                            s._rowBackgroundPreview[rIdx]) ||
                        row.background_image ||
                        null;
                    const rowBg =
                        row.background_type === "image"
                            ? fieldTpl(
                                  "Row background",
                                  imageSlot(
                                      "row_background",
                                      rIdx,
                                      backgroundPreview,
                                      false,
                                      "16:9",
                                  ),
                              )
                            : row.background_type === "color"
                              ? fieldTpl(
                                    "Row background",
                                    '<input type="color" class="form-control form-control-color hb-play-row-control" data-row="' +
                                        rIdx +
                                        '" data-field="background_color" value="' +
                                        esc(row.background_color || "#ffffff") +
                                        '">',
                                )
                              : "";
                    const rowKey = row.media_key || "row_" + rIdx;
                    const rowDisclosure = disclosureInfo(
                        s,
                        "play-row",
                        rowKey,
                        rIdx === 0,
                    );
                    const rowCollapseId =
                        "hb-play-row-" + sectionUiKey(s) + "-" + rowKey;
                    const isFocusedRow =
                        focusedPlayRow &&
                        focusedPlayRow.sectionKey === sectionUiKey(s) &&
                        focusedPlayRow.rowIndex === rIdx;
                    const paddingInputs = paddingSides(row)
                        .map(
                            (padding) =>
                                '<div class="col-6 col-md-3">' +
                                '<label class="form-label">' +
                                padding.side.charAt(0).toUpperCase() +
                                padding.side.slice(1) +
                                "</label>" +
                                '<div class="input-group input-group-sm">' +
                                '<input type="number" class="form-control hb-row-padding-input" data-row="' +
                                rIdx +
                                '" data-side="' +
                                padding.side +
                                '" value="' +
                                esc(padding.value) +
                                '">' +
                                '<span class="input-group-text">px</span>' +
                                "</div></div>",
                        )
                        .join("");
                    const itemsHtml = rowItems.length
                        ? rowItems
                              .map((item) =>
                                  playImageItemEditor(
                                      s,
                                      s.items.indexOf(item),
                                      rIdx,
                                  ),
                              )
                              .join("")
                        : '<div class="hb-play-empty-row">No items in this row yet.</div>';

                    return (
                        '<div class="hb-row-panel hb-play-row' +
                        (isFocusedRow ? " is-focused" : "") +
                        '" data-row="' +
                        rIdx +
                        '">' +
                        '<div class="hb-row-panel-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' +
                        rowCollapseId +
                        '" data-disclosure-key="' +
                        rowDisclosure.key +
                        '" aria-expanded="' +
                        rowDisclosure.expanded +
                        '">' +
                        '<div class="min-w-0"><strong><i class="ti ti-chevron-right me-2 hb-disclosure-chevron"></i>Row ' +
                        (rIdx + 1) +
                        '</strong><span class="hb-row-summary">' +
                        rowItems.length +
                        " item" +
                        (rowItems.length === 1 ? "" : "s") +
                        " · " +
                        (row.style === "scroll" ? "Slider" : "Grid") +
                        "</span></div>" +
                        '<button type="button" class="btn btn-sm btn-outline-primary hb-add-play-item" data-row="' +
                        rIdx +
                        '"><i class="ti ti-plus me-1"></i>Add item</button>' +
                        '<button type="button" class="btn btn-sm btn-ghost-danger hb-remove-play-row" data-row="' +
                        rIdx +
                        '"' +
                        (s.config.rows.length <= 1 ? " disabled" : "") +
                        '><i class="ti ti-trash"></i></button>' +
                        "</div>" +
                        '<div id="' +
                        rowCollapseId +
                        '" class="collapse' +
                        rowDisclosure.show +
                        '" data-disclosure-key="' +
                        rowDisclosure.key +
                        '"><div class="hb-row-panel-body">' +
                        '<div class="hb-row-settings-card">' +
                        '<div class="row g-2">' +
                        '<div class="col-12">' +
                        fieldTpl(
                            "Row title",
                            '<input type="text" class="form-control hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="title" value="' +
                                esc(row.title || "") +
                                '" placeholder="Optional row heading">',
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            "Layout",
                            '<select class="form-select hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="style"><option value="grid"' +
                                (row.style === "grid" ? " selected" : "") +
                                '>Grid</option><option value="scroll"' +
                                (row.style === "scroll" ? " selected" : "") +
                                ">Scroll</option></select>",
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            "Width",
                            '<select class="form-select hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="container_width"><option value="container"' +
                                (row.container_width === "container"
                                    ? " selected"
                                    : "") +
                                '>Container</option><option value="fluid"' +
                                (row.container_width === "fluid"
                                    ? " selected"
                                    : "") +
                                '>Fluid</option><option value="full"' +
                                (row.container_width === "full"
                                    ? " selected"
                                    : "") +
                                ">Full</option></select>",
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            row.style === "scroll"
                                ? "Desktop visible items"
                                : "Grid columns",
                            '<select class="form-select hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="items_to_show">' +
                                playImageOptionHtml(
                                    PLAY_IMAGE_VISIBLE_OPTIONS,
                                    row.items_to_show,
                                ) +
                                "</select>",
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            row.style === "scroll"
                                ? "Mobile visible items"
                                : "Mobile grid columns",
                            '<select class="form-select hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="items_to_show_mobile">' +
                                playImageOptionHtml(
                                    PLAY_IMAGE_VISIBLE_MOBILE_OPTIONS,
                                    row.items_to_show_mobile,
                                ) +
                                "</select>",
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            "Inner spacing",
                            '<input type="range" min="-100" max="100" class="form-range hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="grid_gap" value="' +
                                esc(row.grid_gap) +
                                '"><span class="hb-range-value"><strong>' +
                                esc(row.grid_gap) +
                                "</strong><small>px</small></span>",
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            "Border radius",
                            '<input type="range" min="0" max="50" class="form-range hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="border_radius" value="' +
                                esc(row.border_radius) +
                                '"><span class="hb-range-value"><strong>' +
                                esc(row.border_radius) +
                                "</strong><small>px</small></span>",
                        ) +
                        "</div>" +
                        '<div class="col-12">' +
                        fieldTpl(
                            "Outer spacing",
                            '<input type="range" min="-100" max="100" class="form-range hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="outer_padding" value="' +
                                esc(row.outer_padding) +
                                '"><span class="hb-range-value"><strong>' +
                                esc(
                                    row.outer_padding_custom
                                        ? "Custom"
                                        : row.outer_padding,
                                ) +
                                (row.outer_padding_custom
                                    ? ""
                                    : "</strong><small>px</small>") +
                                "</span>",
                        ) +
                        "</div>" +
                        '<div class="col-12"><div class="row g-2 hb-padding-sides">' +
                        paddingInputs +
                        "</div></div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            "Background",
                            '<select class="form-select hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="background_type"><option value="none"' +
                                (row.background_type === "none"
                                    ? " selected"
                                    : "") +
                                '>None</option><option value="color"' +
                                (row.background_type === "color"
                                    ? " selected"
                                    : "") +
                                '>Color</option><option value="image"' +
                                (row.background_type === "image"
                                    ? " selected"
                                    : "") +
                                ">Image</option></select>",
                        ) +
                        "</div>" +
                        '<div class="col-6">' +
                        fieldTpl(
                            "Hover",
                            '<select class="form-select hb-play-row-control" data-row="' +
                                rIdx +
                                '" data-field="hover_effect"><option value="none"' +
                                (row.hover_effect === "none"
                                    ? " selected"
                                    : "") +
                                '>None</option><option value="zoom"' +
                                (row.hover_effect === "zoom"
                                    ? " selected"
                                    : "") +
                                '>Zoom</option><option value="lift"' +
                                (row.hover_effect === "lift"
                                    ? " selected"
                                    : "") +
                                ">Lift</option></select>",
                        ) +
                        "</div>" +
                        // [Play Image Slider Controls] Render Scroll Buttons, Bullets, Auto Scroll, and Delay inputs when row style is scroll
                        (row.style === "scroll"
                            ? '<div class="col-6">' +
                              '<label class="form-check form-switch mt-2">' +
                              '<input class="form-check-input hb-play-row-control" type="checkbox" data-row="' +
                              rIdx +
                              '" data-field="show_scroll_buttons"' +
                              (isConfigTrue(row.show_scroll_buttons, true)
                                  ? " checked"
                                  : "") +
                              '><span class="form-check-label small text-muted">Scroll buttons</span>' +
                              "</label>" +
                              "</div>" +
                              '<div class="col-6">' +
                              '<label class="form-check form-switch mt-2">' +
                              '<input class="form-check-input hb-play-row-control" type="checkbox" data-row="' +
                              rIdx +
                              '" data-field="show_bullets"' +
                              (isConfigTrue(row.show_bullets, false) ? " checked" : "") +
                              '><span class="form-check-label small text-muted">Bullets</span>' +
                              "</label>" +
                              "</div>" +
                              '<div class="col-6">' +
                              '<label class="form-check form-switch mt-2">' +
                              '<input class="form-check-input hb-play-row-control" type="checkbox" data-row="' +
                              rIdx +
                              '" data-field="auto_scroll"' +
                              (isConfigTrue(row.auto_scroll, false) ? " checked" : "") +
                              '><span class="form-check-label small text-muted">Auto scroll</span>' +
                              "</label>" +
                              "</div>" +
                              '<div class="col-6"' +
                              (isConfigTrue(row.auto_scroll, false)
                                  ? ""
                                  : ' style="display: none;"') +
                              ">" +
                              fieldTpl(
                                  "Auto scroll delay (sec)",
                                  '<input type="number" min="1" max="30" class="form-control form-control-sm hb-play-row-control" data-row="' +
                                      rIdx +
                                      '" data-field="auto_scroll_time" value="' +
                                      esc(row.auto_scroll_time || 4) +
                                      '">',
                              ) +
                              "</div>"
                            : "") +
                        '<div class="col-12">' +
                        rowBg +
                        "</div>" +
                        "</div></div>" +
                        '<div class="hb-items-stack mt-3">' +
                        itemsHtml +
                        "</div>" +
                        "</div></div>"
                    );
                })
                .join("");

            return (
                '<div class="hb-play-row-list">' +
                rowsHtml +
                "</div>" +
                '<button type="button" class="btn btn-sm btn-outline-primary hb-add-play-row"><i class="ti ti-plus me-1"></i>Add row</button>'
            );
        }

        function playImageItemEditor(s, itemIndex, rowIndex) {
            const it = s.items[itemIndex];
            it.config = normalizePlayImageItemConfig(it.config, rowIndex);
            const linkType = currentBannerLinkType(it);
            const isWeb = state.platform === "web";
            const itemKey = itemUiKey(it);
            const itemDisclosure = disclosureInfo(
                s,
                "play-item",
                itemKey,
                false,
            );
            const itemCollapseId =
                "hb-play-item-" + sectionUiKey(s) + "-" + itemKey;
            const imgSlots = isWeb
                ? '<div class="d-flex flex-column gap-2 hb-play-media-stack">' +
                  '<div><span class="small text-muted d-block mb-1">Desktop</span>' +
                  imageSlot("item", itemIndex, itemPreview(it), false, "16:9") +
                  "</div>" +
                  '<div><span class="small text-muted d-block mb-1">Mobile Web</span>' +
                  imageSlot(
                      "item_mobile",
                      itemIndex,
                      itemMobilePreview(it),
                      false,
                      "16:9",
                  ) +
                  "</div></div>"
                : imageSlot("item", itemIndex, itemPreview(it), false, "16:9");

            return (
                '<div class="hb-item-panel hb-play-image-item" data-j="' +
                itemIndex +
                '">' +
                '<div class="hb-item-collapse-head hb-disclosure-trigger" data-bs-toggle="collapse" data-bs-target="#' +
                itemCollapseId +
                '" data-disclosure-key="' +
                itemDisclosure.key +
                '" aria-expanded="' +
                itemDisclosure.expanded +
                '">' +
                '<span><i class="ti ti-chevron-right me-2 hb-disclosure-chevron"></i>' +
                esc(it.title || "Image item " + (itemIndex + 1)) +
                '<small class="hb-item-summary">' +
                esc(
                    linkType === "none"
                        ? "No link"
                        : nameOf(linkType, GRID_LINK_TYPES),
                ) +
                "</small></span>" +
                '<button type="button" class="btn btn-sm btn-ghost-danger hb-item-remove"><i class="ti ti-x"></i></button>' +
                "</div>" +
                '<div id="' +
                itemCollapseId +
                '" class="collapse' +
                itemDisclosure.show +
                '" data-disclosure-key="' +
                itemDisclosure.key +
                '"><div class="hb-item-panel-body d-flex align-items-start gap-3">' +
                imgSlots +
                '<div class="flex-fill">' +
                '<div class="row g-2">' +
                '<div class="col-6">' +
                fieldTpl(
                    "Desktop span",
                    '<select class="form-select hb-play-item-control" data-field="cols">' +
                        playImageOptionHtml(
                            PLAY_IMAGE_SPAN_OPTIONS,
                            it.config.cols,
                        ) +
                        "</select>",
                ) +
                "</div>" +
                '<div class="col-6">' +
                fieldTpl(
                    "Mobile span",
                    '<select class="form-select hb-play-item-control" data-field="cols_mobile">' +
                        playImageOptionHtml(
                            PLAY_IMAGE_SPAN_OPTIONS,
                            it.config.cols_mobile,
                        ) +
                        "</select>",
                ) +
                "</div>" +
                '<div class="col-6">' +
                fieldTpl(
                    "Ratio",
                    '<select class="form-select hb-play-item-control" data-field="aspect_ratio"><option value="16:9"' +
                        (it.config.aspect_ratio === "16:9" ? " selected" : "") +
                        '>16:9</option><option value="21:9"' +
                        (it.config.aspect_ratio === "21:9" ? " selected" : "") +
                        '>21:9 (Ultrawide)</option><option value="3:1"' +
                        (it.config.aspect_ratio === "3:1" ? " selected" : "") +
                        '>3:1 (Wide Banner)</option><option value="1:1"' +
                        (it.config.aspect_ratio === "1:1" ? " selected" : "") +
                        '>1:1</option><option value="4:5"' +
                        (it.config.aspect_ratio === "4:5" ? " selected" : "") +
                        '>4:5</option><option value="auto"' +
                        (it.config.aspect_ratio === "auto" ? " selected" : "") +
                        '>Auto (16:9)</option><option value="original"' +
                        (it.config.aspect_ratio === "original"
                            ? " selected"
                            : "") +
                        ">Original Size</option></select>",
                ) +
                "</div>" +
                '<div class="col-6">' +
                fieldTpl(
                    "Fit",
                    '<select class="form-select hb-play-item-control" data-field="object_fit"><option value="cover"' +
                        (it.config.object_fit === "cover" ? " selected" : "") +
                        '>Cover</option><option value="contain"' +
                        (it.config.object_fit === "contain"
                            ? " selected"
                            : "") +
                        ">Contain</option></select>",
                ) +
                "</div>" +
                '<div class="col-6">' +
                fieldTpl(
                    "Position",
                    '<select class="form-select hb-play-item-control" data-field="object_position"><option value="center"' +
                        ((it.config.object_position || "center") === "center"
                            ? " selected"
                            : "") +
                        '>Center</option><option value="top"' +
                        (it.config.object_position === "top"
                            ? " selected"
                            : "") +
                        '>Top</option><option value="bottom"' +
                        (it.config.object_position === "bottom"
                            ? " selected"
                            : "") +
                        '>Bottom</option><option value="left"' +
                        (it.config.object_position === "left"
                            ? " selected"
                            : "") +
                        '>Left</option><option value="right"' +
                        (it.config.object_position === "right"
                            ? " selected"
                            : "") +
                        ">Right</option></select>",
                ) +
                "</div>" +
                '<div class="col-12">' +
                seg("__linktype", GRID_LINK_TYPES, linkType).replace(
                    'data-seg="__linktype"',
                    'data-banner-linktype="' + itemIndex + '"',
                ) +
                '<div class="mt-2">' +
                (linkType === "custom"
                    ? '<input type="url" class="form-control hb-banner-url" value="' +
                      esc(it.link_url) +
                      '" placeholder="https://...">'
                    : linkType === "none"
                      ? '<div class="text-muted small">No click action</div>'
                      : '<select class="form-select hb-banner-entity" data-entity="' +
                        linkType +
                        '"></select>') +
                "</div></div>" +
                "</div></div>" +
                "</div></div></div>"
            );
        }

        function advBody(s) {
            let h = "";
            if (
                s.type === "products" ||
                s.type === "categories" ||
                s.type === "brands" ||
                s.type === "stories" ||
                s.type === "reels"
            ) {
                h += fieldTpl(
                    "Background",
                    seg(
                        "background_type",
                        BACKGROUND,
                        s.config.background_type || "none",
                    ),
                );
                if (s.config.background_type === "color") {
                    const bgLight =
                        typeof s.config.background_color === "object" &&
                        s.config.background_color !== null
                            ? s.config.background_color.light || "#FFFFFF"
                            : s.config.background_color || "#FFFFFF";
                    const bgDark =
                        typeof s.config.background_color === "object" &&
                        s.config.background_color !== null
                            ? s.config.background_color.dark || "#1E293B"
                            : "#1E293B";

                    h +=
                        '<div class="card card-sm mb-2 mt-2 bg-body-tertiary"><div class="card-body py-2 px-3">' +
                        '<div class="mb-2"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;"><i class="ti ti-palette me-1"></i>Background Colors</strong></div>' +
                        '<div class="row g-2">' +
                        '<div class="col-6">' +
                        '<label class="form-label mb-1" style="font-size:0.75rem;"><i class="ti ti-sun text-warning me-1"></i>Light Mode</label>' +
                        '<div class="d-flex align-items-center gap-2">' +
                        '<input type="color" class="form-control form-control-color" style="max-width:40px; height:32px; padding:2px;" id="hb-bgcolor-light" value="' +
                        esc(bgLight) +
                        '" title="Light Mode Background">' +
                        '<span class="text-muted small font-monospace" id="hb-bgcolor-light-label">' +
                        esc(bgLight) +
                        "</span>" +
                        "</div></div>" +
                        '<div class="col-6">' +
                        '<label class="form-label mb-1" style="font-size:0.75rem;"><i class="ti ti-moon text-indigo me-1"></i>Dark Mode</label>' +
                        '<div class="d-flex align-items-center gap-2">' +
                        '<input type="color" class="form-control form-control-color" style="max-width:40px; height:32px; padding:2px;" id="hb-bgcolor-dark" value="' +
                        esc(bgDark) +
                        '" title="Dark Mode Background">' +
                        '<span class="text-muted small font-monospace" id="hb-bgcolor-dark-label">' +
                        esc(bgDark) +
                        "</span>" +
                        "</div></div>" +
                        "</div>" +
                        "</div></div>";
                }
                if (s.config.background_type === "image") {
                    if (s.type === "products") {
                        h += fieldTpl(
                            "Image Coverage",
                            seg(
                                "background_image_style",
                                BACKGROUND_IMAGE_STYLE,
                                s.config.background_image_style || "full",
                            ),
                        );
                    }
                    h += fieldTpl(
                        "Background image",
                        imageSlot(
                            "background",
                            null,
                            sectionPreview(s, "background"),
                        ),
                    );
                }

                // Title & Text Colors - Always available for sections with titles
                const txtLight =
                    typeof s.config.text_color === "object" &&
                    s.config.text_color !== null
                        ? s.config.text_color.light || "#1E293B"
                        : s.config.text_color || "#1E293B";
                const txtDark =
                    typeof s.config.text_color === "object" &&
                    s.config.text_color !== null
                        ? s.config.text_color.dark || "#F8FAFC"
                        : "#F8FAFC";

                h +=
                    '<div class="card card-sm mb-2 mt-2 bg-body-tertiary"><div class="card-body py-2 px-3">' +
                    '<div class="mb-2"><strong class="text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;"><i class="ti ti-typography me-1"></i>Title & Text Colors</strong></div>' +
                    '<div class="row g-2">' +
                    '<div class="col-6">' +
                    '<label class="form-label mb-1" style="font-size:0.75rem;"><i class="ti ti-sun text-warning me-1"></i>Light Mode Text</label>' +
                    '<div class="d-flex align-items-center gap-2">' +
                    '<input type="color" class="form-control form-control-color" style="max-width:40px; height:32px; padding:2px;" id="hb-textcolor-light" value="' +
                    esc(txtLight) +
                    '" title="Light Mode Text">' +
                    '<span class="text-muted small font-monospace" id="hb-textcolor-light-label">' +
                    esc(txtLight) +
                    "</span>" +
                    "</div></div>" +
                    '<div class="col-6">' +
                    '<label class="form-label mb-1" style="font-size:0.75rem;"><i class="ti ti-moon text-indigo me-1"></i>Dark Mode Text</label>' +
                    '<div class="d-flex align-items-center gap-2">' +
                    '<input type="color" class="form-control form-control-color" style="max-width:40px; height:32px; padding:2px;" id="hb-textcolor-dark" value="' +
                    esc(txtDark) +
                    '" title="Dark Mode Text">' +
                    '<span class="text-muted small font-monospace" id="hb-textcolor-dark-label">' +
                    esc(txtDark) +
                    "</span>" +
                    "</div></div>" +
                    "</div>" +
                    "</div></div>";
            }
            h += fieldTpl(
                L("markets", "Delivery Zones"),
                '<select class="form-select hb-markets" multiple></select>',
            );

            const startVal = s.start_at
                ? s.start_at.replace(" ", "T").slice(0, 16)
                : "";
            const endVal = s.end_at
                ? s.end_at.replace(" ", "T").slice(0, 16)
                : "";

            h +=
                '<div class="card card-sm mt-3 mb-2 bg-body-tertiary"><div class="card-body py-2 px-3">' +
                '<h4 class="card-title mb-2 d-flex align-items-center"><i class="ti ti-calendar-event text-primary me-1"></i>Campaign Scheduling (Optional)</h4>' +
                '<div class="row g-2">' +
                '<div class="col-6">' +
                fieldTpl(
                    "Start Date & Time",
                    '<input type="datetime-local" class="form-control form-control-sm" id="hb-start-at" value="' +
                        esc(startVal) +
                        '">',
                ) +
                "</div>" +
                '<div class="col-6">' +
                fieldTpl(
                    "End Date & Time",
                    '<input type="datetime-local" class="form-control form-control-sm" id="hb-end-at" value="' +
                        esc(endVal) +
                        '">',
                ) +
                "</div>" +
                "</div></div></div>";

            return h;
        }

        /* ---------- editor wiring ---------- */
        function wireEditor(s) {
            function bindInput(selector, fn) {
                const el = editorEl.querySelector(selector);
                if (el) {
                    el.addEventListener("input", (e) => fn(e.target.value));
                    el.addEventListener("change", (e) => fn(e.target.value));
                }
            }

            const advHead = editorEl.querySelector("#hb-adv-toggle");
            if (advHead)
                advHead.addEventListener("click", () => {
                    s._adv = !s._adv;
                    renderEditor();
                });

            editorEl
                .querySelectorAll(".hb-disclosure-trigger")
                .forEach((trigger) =>
                    trigger.addEventListener("click", () => {
                        const target = editorEl.querySelector(
                            trigger.dataset.bsTarget,
                        );
                        disclosureState.set(
                            trigger.dataset.disclosureKey,
                            !(target && target.classList.contains("show")),
                        );
                    }),
                );
            editorEl
                .querySelectorAll(".collapse[data-disclosure-key]")
                .forEach((collapse) => {
                    collapse.addEventListener("shown.bs.collapse", () => {
                        disclosureState.set(
                            collapse.dataset.disclosureKey,
                            true,
                        );
                    });
                    collapse.addEventListener("hidden.bs.collapse", () => {
                        disclosureState.set(
                            collapse.dataset.disclosureKey,
                            false,
                        );
                    });
                });

            const canEditSection = cfg.canEdit && !s.is_global_tagged;
            if (!canEditSection) {
                return;
            }

            editorEl.querySelectorAll(".hb-spacing-input").forEach((inp) => {
                const updateSpacing = () => {
                    const path = inp.dataset.spacingPath;
                    const val = parseInt(inp.value, 10) || 0;

                    if (
                        !s.config ||
                        typeof s.config !== "object" ||
                        Array.isArray(s.config)
                    )
                        s.config = {};
                    if (
                        !s.config.spacing ||
                        typeof s.config.spacing !== "object" ||
                        Array.isArray(s.config.spacing)
                    )
                        s.config.spacing = normalizeSpacing(null);

                    const keys = path.split(".");
                    if (keys.length === 2) {
                        if (
                            typeof s.config.spacing[keys[0]] !== "object" ||
                            s.config.spacing[keys[0]] === null
                        ) {
                            s.config.spacing[keys[0]] = {};
                        }
                        s.config.spacing[keys[0]][keys[1]] = val;
                    } else {
                        s.config.spacing[keys[0]] = val;
                    }

                    const badgeEl = editorEl.querySelector(
                        '[data-spacing-val="' + path + '"]',
                    );
                    if (badgeEl) badgeEl.textContent = val + "px";

                    markDirty();
                };
                inp.addEventListener("input", updateSpacing);
                inp.addEventListener("change", updateSpacing);
            });
            editorEl.querySelectorAll("[data-seg]").forEach((g) => {
                const fld = g.dataset.seg;
                g.querySelectorAll("input.form-selectgroup-input").forEach(
                    (inp) =>
                        inp.addEventListener("change", () => {
                            if (!inp.checked) return;
                            if (fld === "item_source") {
                                s._items_by_source = s._items_by_source || {};
                                s._items_by_source[s.config.item_source] =
                                    s.items;
                                s.items = s._items_by_source[inp.value] || [];
                            }
                            if (fld in s) s[fld] = inp.value;
                            else s.config[fld] = inp.value;
                            markDirty();
                            renderSections();
                            renderEditor();
                        }),
                );
            });
            editorEl.querySelectorAll("[data-preset]").forEach((g) => {
                const fld = g.dataset.preset;
                g.querySelectorAll(".hb-preset-card").forEach((c) =>
                    c.addEventListener("click", () => {
                        if (fld in s) s[fld] = c.dataset.val;
                        else s.config[fld] = c.dataset.val;
                        // When switching layout mode to vertical (grid), ensure default grid_columns is initialized
                        if (
                            fld === "rows_layout" &&
                            c.dataset.val === "grid" &&
                            !s.config.grid_columns
                        ) {
                            s.config.grid_columns = "4";
                        }
                        markDirty();
                        renderSections();
                        renderEditor();
                    }),
                );
            });
            bindInput("#hb-title", (v) => {
                s.title = v;
                markDirty();
                updateRowTitle();
            });
            bindInput("#hb-cardcolor", (v) => {
                s.config.card_bg_color = v;
                markDirty();
            });
            bindInput("#hb-cardtitlecolor", (v) => {
                s.config.card_title_color = v;
                markDirty();
            });
            bindInput("#hb-bgcolor-light", (v) => {
                if (
                    typeof s.config.background_color !== "object" ||
                    !s.config.background_color
                ) {
                    s.config.background_color = { light: v, dark: "#1E293B" };
                } else {
                    s.config.background_color.light = v;
                }
                const label = editorEl.querySelector("#hb-bgcolor-light-label");
                if (label) label.textContent = v;
                markDirty();
                renderLivePreview();
            });
            bindInput("#hb-bgcolor-dark", (v) => {
                if (
                    typeof s.config.background_color !== "object" ||
                    !s.config.background_color
                ) {
                    s.config.background_color = { light: "#FFFFFF", dark: v };
                } else {
                    s.config.background_color.dark = v;
                }
                const label = editorEl.querySelector("#hb-bgcolor-dark-label");
                if (label) label.textContent = v;
                markDirty();
                renderLivePreview();
            });
            bindInput("#hb-textcolor-light", (v) => {
                if (
                    typeof s.config.text_color !== "object" ||
                    !s.config.text_color
                ) {
                    s.config.text_color = { light: v, dark: "#F8FAFC" };
                } else {
                    s.config.text_color.light = v;
                }
                const label = editorEl.querySelector(
                    "#hb-textcolor-light-label",
                );
                if (label) label.textContent = v;
                markDirty();
                renderLivePreview();
            });
            bindInput("#hb-textcolor-dark", (v) => {
                if (
                    typeof s.config.text_color !== "object" ||
                    !s.config.text_color
                ) {
                    s.config.text_color = { light: "#1E293B", dark: v };
                } else {
                    s.config.text_color.dark = v;
                }
                const label = editorEl.querySelector(
                    "#hb-textcolor-dark-label",
                );
                if (label) label.textContent = v;
                markDirty();
                renderLivePreview();
            });
            bindInput("#hb-story-ring-color", (v) => {
                s.config.ring_color = v;
                editorEl
                    .querySelectorAll(".hb-story-color-swatch")
                    .forEach((el) => {
                        const match =
                            el.dataset.color.toLowerCase() === v.toLowerCase();
                        el.classList.toggle("active", match);
                        el.style.border = match
                            ? "2px solid #000"
                            : "2px solid transparent";
                    });
                markDirty();
                renderLivePreview();
            });
            editorEl
                .querySelectorAll(".hb-story-color-swatch")
                .forEach((swatch) => {
                    swatch.addEventListener("click", () => {
                        const color = swatch.dataset.color;
                        s.config.ring_color = color;
                        const picker = editorEl.querySelector(
                            "#hb-story-ring-color",
                        );
                        if (picker) picker.value = color;
                        editorEl
                            .querySelectorAll(".hb-story-color-swatch")
                            .forEach((el) => {
                                const match =
                                    el.dataset.color.toLowerCase() ===
                                    color.toLowerCase();
                                el.classList.toggle("active", match);
                                el.style.border = match
                                    ? "2px solid #000"
                                    : "2px solid transparent";
                            });
                        markDirty();
                        renderLivePreview();
                    });
                });
            bindInput("#hb-story-limit", (v) => {
                s.config.limit = parseInt(v, 10) || 10;
                markDirty();
                renderLivePreview();
            });
            const storyNameCheck = editorEl.querySelector(
                "#hb-story-show-name",
            );
            if (storyNameCheck) {
                storyNameCheck.addEventListener("change", (e) => {
                    s.config.show_profile_name = e.target.checked;
                    markDirty();
                    renderLivePreview();
                });
            }
            bindInput("#hb-reel-limit", (v) => {
                s.config.limit = parseInt(v, 10) || 8;
                markDirty();
                renderLivePreview();
            });
            const reelTitleCheck = editorEl.querySelector(
                "#hb-reel-show-title",
            );
            if (reelTitleCheck) {
                reelTitleCheck.addEventListener("change", (e) => {
                    s.config.show_title = e.target.checked;
                    markDirty();
                    renderLivePreview();
                });
            }
            const reelLikesCheck = editorEl.querySelector(
                "#hb-reel-show-likes",
            );
            if (reelLikesCheck) {
                reelLikesCheck.addEventListener("change", (e) => {
                    s.config.show_likes = e.target.checked;
                    markDirty();
                    renderLivePreview();
                });
            }
            bindInput("#hb-start-at", (v) => {
                s.start_at = v ? v.replace("T", " ") + ":00" : null;
                markDirty();
                renderSections();
            });
            bindInput("#hb-end-at", (v) => {
                s.end_at = v ? v.replace("T", " ") + ":00" : null;
                markDirty();
                renderSections();
            });

            const savePresetBtn = editorEl.querySelector("#hb-save-preset-btn");
            if (savePresetBtn)
                savePresetBtn.addEventListener("click", () => {
                    openSavePresetModal(s);
                });

            const del = editorEl.querySelector("#hb-del-section");
            if (del)
                del.addEventListener("click", () =>
                    ConfirmModal.open(
                        L("removeConfirm", "Remove this section?"),
                        () => {
                            state.sections.splice(sel, 1);
                            sel = Math.min(sel, state.sections.length - 1);
                            markDirty();
                            renderSections();
                            renderEditor();
                        },
                    ),
                );

            editorEl.querySelectorAll(".hb-add-item").forEach((b) =>
                b.addEventListener("click", () => {
                    if (s.type === "hero" && s.items.length >= 6) return;
                    s.items.push({
                        id: null,
                        purpose: "display",
                        itemable_type:
                            s.type === "hero"
                                ? SRC_TO_KEY[s.config.item_source]
                                : null,
                        itemable_id: null,
                        itemable_label: null,
                        title: "",
                        link_url: "",
                        _link_type: s.type === "banners" ? "product" : null,
                        _targets: {},
                        image: null,
                    });
                    markDirty();
                    renderEditor();
                }),
            );
            editorEl.querySelectorAll(".hb-add-play-row").forEach((b) =>
                b.addEventListener("click", (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    s.config = s.config || {};
                    s.config.rows = normalizePlayImageRows(s.config);
                    const row = defaultPlayImageRowConfig();
                    row.row_index = s.config.rows.length;
                    row.media_key =
                        "row_" +
                        Date.now().toString(36) +
                        "_" +
                        s.config.rows.length;
                    s.config.rows.push(row);
                    disclosureState.set(
                        sectionUiKey(s) + ":play-row:" + row.media_key,
                        true,
                    );
                    focusedPlayRow = {
                        sectionKey: sectionUiKey(s),
                        rowIndex: s.config.rows.length - 1,
                    };
                    markDirty();
                    renderEditor();
                }),
            );
            editorEl.querySelectorAll(".hb-add-play-item").forEach((b) =>
                b.addEventListener("click", (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const rowIndex = parseNum(b.dataset.row, 0);
                    s.items = s.items || [];
                    const item = defaultPlayImageItem(rowIndex);
                    s.items.push(item);
                    disclosureState.set(
                        sectionUiKey(s) + ":play-item:" + itemUiKey(item),
                        true,
                    );
                    markDirty();
                    renderEditor();
                }),
            );
            editorEl.querySelectorAll(".hb-remove-play-row").forEach((b) =>
                b.addEventListener("click", (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const rowIndex = parseNum(b.dataset.row, 0);
                    s.config.rows.splice(rowIndex, 1);
                    s.items = (s.items || []).filter((item) => {
                        const itemRow = parseNum(
                            item.config && item.config.row_index,
                            0,
                        );
                        if (itemRow === rowIndex) return false;
                        if (itemRow > rowIndex) {
                            item.config = item.config || {};
                            item.config.row_index = itemRow - 1;
                        }
                        return true;
                    });
                    markDirty();
                    renderEditor();
                }),
            );
            editorEl
                .querySelectorAll(".hb-play-row-control")
                .forEach((input) => {
                    const updatePlayRow = () => {
                        const rowIndex = parseNum(input.dataset.row, 0);
                        const field = input.dataset.field;
                        const numericFields = [
                            "grid_gap",
                            "outer_padding",
                            "border_radius",
                            "auto_scroll_time",
                        ];
                        const autoFields = [
                            "items_to_show",
                            "items_to_show_mobile",
                        ];
                        // [Play Image Slider Controls] Support boolean checkbox inputs and auto_scroll toggle
                        const booleanFields = [
                            "show_scroll_buttons",
                            "show_bullets",
                            "auto_scroll",
                        ];
                        s.config.rows = normalizePlayImageRows(s.config);
                        s.config.rows[rowIndex][field] = booleanFields.includes(
                            field,
                        )
                            ? input.checked
                            : autoFields.includes(field) &&
                                input.value === "auto"
                              ? "auto"
                              : numericFields.includes(field)
                                ? parseNum(input.value, 0)
                                : input.value;
                        if (field === "outer_padding") {
                            s.config.rows[rowIndex].outer_padding_custom = "";
                        }
                        const valueBadge =
                            input.parentElement &&
                            input.parentElement.querySelector(
                                ".hb-range-value",
                            );
                        if (valueBadge) {
                            valueBadge.innerHTML = numericFields.includes(field)
                                ? "<strong>" +
                                  esc(s.config.rows[rowIndex][field]) +
                                  "</strong>" +
                                  (field === "items_to_show" ||
                                  field === "items_to_show_mobile"
                                      ? ""
                                      : "<small>px</small>")
                                : esc(s.config.rows[rowIndex][field]);
                        }
                        markDirty();
                        renderLivePreview();
                        if (
                            field === "style" ||
                            field === "background_type" ||
                            field === "auto_scroll"
                        ) {
                            renderEditor();
                        }
                    };
                    input.addEventListener("input", updatePlayRow);
                    input.addEventListener("change", updatePlayRow);
                });
            editorEl
                .querySelectorAll(".hb-row-padding-input")
                .forEach((input) => {
                    const updatePadding = () => {
                        const rowIndex = parseNum(input.dataset.row, 0);
                        const row = s.config.rows[rowIndex];
                        const sideIndex = {
                            top: 0,
                            right: 1,
                            bottom: 2,
                            left: 3,
                        }[input.dataset.side];
                        const values = paddingSides(row).map(
                            (entry) => entry.value,
                        );

                        values[sideIndex] = parseNum(input.value, 0);
                        row.outer_padding = values.every(
                            (value) => value === values[0],
                        )
                            ? values[0]
                            : Math.max(...values);
                        row.outer_padding_custom = values.every(
                            (value) => value === values[0],
                        )
                            ? ""
                            : values.join("/");
                        markDirty();
                        renderLivePreview();
                    };
                    input.addEventListener("input", updatePadding);
                    input.addEventListener("change", updatePadding);
                });
            editorEl
                .querySelectorAll(".hb-play-image-item")
                .forEach((panel) => {
                    panel.addEventListener("click", (e) => {
                        if (e.target.closest(".hb-item-remove")) return;
                        const j = +panel.dataset.j;
                        scrollToPlayItemInPreview(s, j);
                    });
                });
            editorEl
                .querySelectorAll(".hb-play-item-control")
                .forEach((input) => {
                    const updatePlayItem = () => {
                        const j = +input.closest("[data-j]").dataset.j;
                        const field = input.dataset.field;
                        s.items[j].config = s.items[j].config || {};
                        s.items[j].config[field] = input.value;
                        markDirty();
                        renderLivePreview();
                        scrollToPlayItemInPreview(s, j);
                    };
                    input.addEventListener("input", updatePlayItem);
                    input.addEventListener("change", updatePlayItem);
                });
            editorEl.querySelectorAll(".hb-item-remove").forEach((b) =>
                b.addEventListener("click", (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const j = +b.closest("[data-j]").dataset.j;
                    s.items.splice(j, 1);
                    markDirty();
                    renderEditor();
                }),
            );
            editorEl.querySelectorAll(".hb-item-title").forEach((inp) =>
                inp.addEventListener("input", () => {
                    const j = +inp.closest("[data-j]").dataset.j;
                    s.items[j].title = inp.value;
                    markDirty();
                }),
            );
            editorEl.querySelectorAll(".hb-banner-url").forEach((inp) =>
                inp.addEventListener("input", () => {
                    const j = +inp.closest("[data-j]").dataset.j;
                    s.items[j].link_url = inp.value;
                    s.items[j].itemable_type = null;
                    s.items[j].itemable_id = null;
                    s.items[j]._link_type = "custom";
                    s.items[j].config = s.items[j].config || {};
                    s.items[j].config.link_type = "custom";
                    s.items[j]._targets = s.items[j]._targets || {};
                    s.items[j]._targets.custom = {
                        itemable_type: null,
                        itemable_id: null,
                        itemable_label: null,
                        link_url: inp.value,
                    };
                    markDirty();
                }),
            );
            editorEl.querySelectorAll("[data-banner-linktype]").forEach((g) =>
                g
                    .querySelectorAll("input.form-selectgroup-input")
                    .forEach((inp) =>
                        inp.addEventListener("change", () => {
                            if (!inp.checked) return;
                            const j = +g.dataset.bannerLinktype;
                            const it = s.items[j];
                            switchBannerTarget(it, inp.value);
                            markDirty();
                            renderEditor();
                        }),
                    ),
            );
            editorEl.querySelectorAll(".hb-img-slot").forEach((slot) => {
                const kind = slot.dataset.targetKind;
                const j = slot.dataset.j != null ? +slot.dataset.j : null;

                // Handle quick action button clicks (Delete / Replace / Open modal)
                slot.addEventListener("click", (e) => {
                    const actBtn = e.target.closest("[data-slot-act]");
                    const act = actBtn ? actBtn.dataset.slotAct : null;

                    if (act === "delete") {
                        e.preventDefault();
                        e.stopPropagation();
                        if (kind === "item" && j != null) {
                            s.items[j]._imageFile = null;
                            s.items[j]._imagePreview = null;
                            s.items[j].image = null;
                            s.items[j]._removeImage = true;
                        } else if (kind === "item_mobile" && j != null) {
                            s.items[j]._imageMobileFile = null;
                            s.items[j]._imageMobilePreview = null;
                            s.items[j].image_web_mobile = null;
                            s.items[j]._removeImage = true;
                        } else if (kind === "row_background" && j != null) {
                            s._rowBackgroundFiles = s._rowBackgroundFiles || {};
                            s._rowBackgroundPreview =
                                s._rowBackgroundPreview || {};
                            delete s._rowBackgroundFiles[j];
                            delete s._rowBackgroundPreview[j];
                            s.config.rows = normalizePlayImageRows(s.config);
                            s.config.rows[j].background_image = null;
                        } else {
                            s._mediaFiles = s._mediaFiles || {};
                            s._mediaPreview = s._mediaPreview || {};
                            delete s._mediaFiles[kind];
                            delete s._mediaPreview[kind];
                            if (MEDIA_FIELD[kind]) {
                                delete s[MEDIA_FIELD[kind]];
                            }
                        }
                        markDirty();
                        renderEditor();
                        return;
                    }

                    if (!cfg.canEdit) return;

                    const isLottie =
                        (kind === "hero_media" ||
                            kind === "hero_media_web_mobile") &&
                        s.config &&
                        s.config.media_type === "lottie";
                    const existing =
                        kind === "item" && j != null
                            ? itemPreview(s.items[j])
                            : kind === "item_mobile" && j != null
                              ? itemMobilePreview(s.items[j])
                              : kind === "row_background" && j != null
                                ? (s._rowBackgroundPreview &&
                                      s._rowBackgroundPreview[j]) ||
                                  (s.config.rows &&
                                      s.config.rows[j] &&
                                      s.config.rows[j].background_image)
                                : sectionPreview(s, kind);

                    ImageModal.open(
                        {
                            isLottie,
                            existing,
                            allowCrop:
                                s.type === "play_image" &&
                                (kind === "item" ||
                                    kind === "item_mobile" ||
                                    kind === "row_background"),
                        },
                        (nativeFile) => {
                            if (nativeFile) {
                                const url = URL.createObjectURL(nativeFile);
                                if (kind === "item" && j != null) {
                                    s.items[j]._imageFile = nativeFile;
                                    s.items[j]._imagePreview = url;
                                    s.items[j]._removeImage = false;
                                } else if (
                                    kind === "item_mobile" &&
                                    j != null
                                ) {
                                    s.items[j]._imageMobileFile = nativeFile;
                                    s.items[j]._imageMobilePreview = url;
                                    s.items[j]._removeImage = false;
                                } else if (
                                    kind === "row_background" &&
                                    j != null
                                ) {
                                    s._rowBackgroundFiles =
                                        s._rowBackgroundFiles || {};
                                    s._rowBackgroundPreview =
                                        s._rowBackgroundPreview || {};
                                    s._rowBackgroundFiles[j] = nativeFile;
                                    s._rowBackgroundPreview[j] = url;
                                    s.config.rows = normalizePlayImageRows(
                                        s.config,
                                    );
                                    s.config.rows[j].background_type = "image";
                                } else {
                                    s._mediaFiles = s._mediaFiles || {};
                                    s._mediaPreview = s._mediaPreview || {};
                                    s._mediaFiles[kind] = nativeFile;
                                    s._mediaPreview[kind] = url;
                                }
                                markDirty();
                                renderEditor();
                                Toast.fire({
                                    icon: "success",
                                    title: "Image selected",
                                });
                            }
                        },
                    );
                });

                // HTML5 Drag-and-Drop file dropzone listeners
                slot.addEventListener("dragenter", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    slot.classList.add("drag-over");
                });
                slot.addEventListener("dragover", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    slot.classList.add("drag-over");
                });
                slot.addEventListener("dragleave", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    slot.classList.remove("drag-over");
                });
                slot.addEventListener("drop", (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    slot.classList.remove("drag-over");

                    if (
                        !cfg.canEdit ||
                        !e.dataTransfer ||
                        !e.dataTransfer.files.length
                    )
                        return;
                    const file = e.dataTransfer.files[0];
                    const isLottie =
                        (kind === "hero_media" ||
                            kind === "hero_media_web_mobile") &&
                        s.config &&
                        s.config.media_type === "lottie";

                    if (
                        isLottie &&
                        !file.name.endsWith(".json") &&
                        file.type !== "application/json"
                    ) {
                        Toast.fire({
                            icon: "error",
                            title: "Please drop a valid Lottie JSON file",
                        });
                        return;
                    }
                    if (!isLottie && !file.type.startsWith("image/")) {
                        Toast.fire({
                            icon: "error",
                            title: "Please drop a valid image file",
                        });
                        return;
                    }

                    const url = URL.createObjectURL(file);
                    if (kind === "item" && j != null) {
                        const it = s.items[j];
                        it._imageFile = file;
                        it._imagePreview = url;
                        it._removeImage = false;
                    } else if (kind === "item_mobile" && j != null) {
                        const it = s.items[j];
                        it._imageMobileFile = file;
                        it._imageMobilePreview = url;
                        it._removeImage = false;
                    } else if (kind === "row_background" && j != null) {
                        s._rowBackgroundFiles = s._rowBackgroundFiles || {};
                        s._rowBackgroundPreview = s._rowBackgroundPreview || {};
                        s._rowBackgroundFiles[j] = file;
                        s._rowBackgroundPreview[j] = url;
                        s.config.rows = normalizePlayImageRows(s.config);
                        s.config.rows[j].background_type = "image";
                    } else {
                        s._mediaFiles = s._mediaFiles || {};
                        s._mediaPreview = s._mediaPreview || {};
                        s._mediaFiles[kind] = file;
                        s._mediaPreview[kind] = url;
                    }
                    markDirty();
                    renderEditor();
                    Toast.fire({
                        icon: "success",
                        title: "File staged successfully",
                    });
                });
            });

            initEditorSelects(s);
        }

        function bindInput(selector, cb) {
            const el = editorEl.querySelector(selector);
            if (el) el.addEventListener("input", () => cb(el.value));
        }

        function updateRowTitle() {
            const row = sectionsEl.querySelector(
                ".hb-row.active .hb-row-title > div",
            );
            if (row)
                row.textContent =
                    state.sections[sel].title ||
                    nameOf(state.sections[sel].type, TYPE);
        }

        /* ---------- TomSelect pickers ---------- */
        function entityCfg(key) {
            if (key === "brand") return { url: cfg.brandSearch, param: "q" };
            if (key === "category")
                return { url: cfg.categorySearch, param: "search" };
            return { url: cfg.productSearch, param: "search" };
        }

        function remoteOpts(key, multiple) {
            const ec = entityCfg(key);
            return {
                valueField: "value",
                labelField: "text",
                searchField: "text",
                dropdownParent: "body",
                maxItems: multiple ? null : 1,
                placeholder: "Search…",
                load: function (query, cb) {
                    if (!query.length) return cb();
                    const u = new URL(ec.url, window.location.origin);
                    u.searchParams.set(ec.param, query);
                    fetch(u.toString(), {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest",
                            Accept: "application/json",
                        },
                    })
                        .then((r) => r.json())
                        .then((payload) => {
                            const rows = Array.isArray(payload)
                                ? payload
                                : payload.results || payload.data || [];

                            cb(
                                (rows || []).map((r) => ({
                                    value: String(
                                        r.value != null ? r.value : r.id,
                                    ),
                                    text:
                                        r.text ||
                                        r.title ||
                                        r.name ||
                                        "#" + (r.value || r.id),
                                })),
                            );
                        })
                        .catch(() => cb());
                },
            };
        }

        function initEditorSelects(s) {
            editorEl.querySelectorAll("select.hb-entity").forEach((elx) => {
                const key = elx.dataset.entity,
                    purpose = elx.dataset.purpose;
                const ts = new TomSelect(elx, remoteOpts(key, true));
                s.items
                    .filter((it) => it.itemable_type === key)
                    .forEach((it) => {
                        if (it.itemable_id)
                            ts.addOption({
                                value: String(it.itemable_id),
                                text: it.itemable_label || "#" + it.itemable_id,
                            });
                    });
                ts.setValue(
                    s.items
                        .filter((it) => it.itemable_type === key)
                        .map((it) => String(it.itemable_id)),
                    true,
                );
                ts.on("change", () => {
                    const vals = ts.getValue();
                    const arr = Array.isArray(vals) ? vals : vals ? [vals] : [];
                    s.items = arr.map((v) => ({
                        id: null,
                        purpose,
                        itemable_type: key,
                        itemable_id: +v,
                        itemable_label: (ts.options[v] || {}).text || null,
                        title: "",
                        link_url: "",
                        image: null,
                    }));
                    markDirty();
                    renderLivePreview();
                });
            });
            editorEl.querySelectorAll("select.hb-hero-item").forEach((elx) => {
                const j = +elx.closest("[data-j]").dataset.j;
                const it = s.items[j];
                const key = elx.dataset.entity;
                const ts = new TomSelect(elx, remoteOpts(key, false));
                if (it.itemable_id) {
                    ts.addOption({
                        value: String(it.itemable_id),
                        text: it.itemable_label || "#" + it.itemable_id,
                    });
                    ts.setValue(String(it.itemable_id), true);
                }
                ts.on("change", () => {
                    const v = ts.getValue();
                    it.itemable_id = v ? +v : null;
                    it.itemable_label = (ts.options[v] || {}).text || null;
                    markDirty();
                });
            });
            editorEl
                .querySelectorAll("select.hb-category-item")
                .forEach((elx) => {
                    const j = +elx.closest("[data-j]").dataset.j;
                    const it = s.items[j];
                    const key = "category";
                    const ts = new TomSelect(elx, remoteOpts(key, false));
                    if (it.itemable_id) {
                        ts.addOption({
                            value: String(it.itemable_id),
                            text: it.itemable_label || "#" + it.itemable_id,
                        });
                        ts.setValue(String(it.itemable_id), true);
                    }
                    ts.on("change", () => {
                        const v = ts.getValue();
                        it.itemable_type = key;
                        it.itemable_id = v ? +v : null;
                        it.itemable_label = (ts.options[v] || {}).text || null;
                        markDirty();
                        renderLivePreview();
                    });
                });
            editorEl.querySelectorAll("select.hb-brand-item").forEach((elx) => {
                const j = +elx.closest("[data-j]").dataset.j;
                const it = s.items[j];
                const key = "brand";
                const ts = new TomSelect(elx, remoteOpts(key, false));
                if (it.itemable_id) {
                    ts.addOption({
                        value: String(it.itemable_id),
                        text: it.itemable_label || "#" + it.itemable_id,
                    });
                    ts.setValue(String(it.itemable_id), true);
                }
                ts.on("change", () => {
                    const v = ts.getValue();
                    it.itemable_type = key;
                    it.itemable_id = v ? +v : null;
                    it.itemable_label = (ts.options[v] || {}).text || null;
                    markDirty();
                    renderLivePreview();
                });
            });
            editorEl
                .querySelectorAll("select.hb-banner-entity")
                .forEach((elx) => {
                    const j = +elx.closest("[data-j]").dataset.j;
                    const it = s.items[j];
                    const key = elx.dataset.entity;
                    const ts = new TomSelect(elx, remoteOpts(key, false));
                    if (it.itemable_id && it.itemable_type === key) {
                        ts.addOption({
                            value: String(it.itemable_id),
                            text: it.itemable_label || "#" + it.itemable_id,
                        });
                        ts.setValue(String(it.itemable_id), true);
                    }
                    ts.on("change", () => {
                        const v = ts.getValue();
                        it._link_type = key;
                        it.config = it.config || {};
                        it.config.link_type = key;
                        it.itemable_type = key;
                        it.itemable_id = v ? +v : null;
                        it.itemable_label = (ts.options[v] || {}).text || null;
                        it.link_url = "";
                        it._targets = it._targets || {};
                        it._targets[key] = {
                            itemable_type: key,
                            itemable_id: it.itemable_id,
                            itemable_label: it.itemable_label,
                            link_url: "",
                        };
                        markDirty();
                    });
                });
            editorEl.querySelectorAll("select.hb-markets").forEach((elx) => {
                const ts = new TomSelect(elx, {
                    valueField: "value",
                    labelField: "text",
                    searchField: "text",
                    placeholder: L("allMarkets", "All zones"),
                    load: function (query, cb) {
                        const self = this;
                        const u = new URL(
                            cfg.zoneSearch,
                            window.location.origin,
                        );
                        if (query) u.searchParams.set("search", query);
                        fetch(u.toString(), {
                            headers: {
                                "X-Requested-With": "XMLHttpRequest",
                                Accept: "application/json",
                            },
                        })
                            .then((r) => r.json())
                            .then((rows) => {
                                (rows || []).forEach((row) => {
                                    if (self.options[row.value])
                                        self.updateOption(row.value, row);
                                });
                                cb(rows || []);
                            })
                            .catch(() => cb());
                    },
                    onInitialize: function () {
                        this.load("");
                    },
                });
                (s.zone_ids || []).forEach((id) =>
                    ts.addOption({ value: String(id), text: "#" + id }),
                );
                ts.setValue((s.zone_ids || []).map(String), true);
                ts.on("change", () => {
                    const v = ts.getValue();
                    s.zone_ids = (Array.isArray(v) ? v : []).map(Number);
                    markDirty();
                });
            });
        }

        /* ---------- image modal trigger ---------- */
        function openImage(s, target) {
            if (!cfg.canEdit || !window.FilePond) return;
            const isLottie =
                target.kind === "hero_media" &&
                s.config &&
                s.config.media_type === "lottie";
            const existing =
                target.kind === "item"
                    ? itemPreview(s.items[target.j])
                    : sectionPreview(s, target.kind);
            ImageModal.open({ isLottie, existing }, (native) => {
                if (native) {
                    const url = URL.createObjectURL(native);
                    if (target.kind === "item") {
                        const it = s.items[target.j];
                        it._imageFile = native;
                        it._imagePreview = url;
                    } else {
                        s._mediaFiles = s._mediaFiles || {};
                        s._mediaPreview = s._mediaPreview || {};
                        s._mediaFiles[target.kind] = native;
                        s._mediaPreview[target.kind] = url;
                    }
                    markDirty();
                }
                renderEditor();
            });
        }

        /* ---------- save (multipart: structure + staged images) ---------- */
        function appendFormData(fd, data, parent) {
            if (data === null || data === undefined) return;
            if (Array.isArray(data)) {
                data.forEach((v, i) =>
                    appendFormData(fd, v, parent + "[" + i + "]"),
                );
            } else if (typeof data === "object") {
                Object.keys(data).forEach((k) =>
                    appendFormData(
                        fd,
                        data[k],
                        parent ? parent + "[" + k + "]" : k,
                    ),
                );
            } else {
                fd.append(
                    parent,
                    data === true ? "1" : data === false ? "0" : data,
                );
            }
        }

        function serialize() {
            const excludedGlobalIds = state.sections
                .filter(
                    (s) => s.is_global_tagged && s.is_active === false && s.id,
                )
                .map((s) => s.id);

            return {
                platform: state.platform,
                scope_type: state.scope_type,
                scope_id: state.scope_id,
                zone_id: state.zone_id || null,
                is_active: state.is_active,
                excluded_global_section_ids: excludedGlobalIds,
                sections: state.sections
                    .filter((s) => !s.is_global_tagged)
                    .map((s, i) => {
                        // Ensure grid_columns is included in payload when vertical layout is active and preselected
                        if (
                            state.platform === "app" &&
                            (s.type === "brands" || s.type === "categories") &&
                            s.config &&
                            s.config.rows_layout === "grid" &&
                            !s.config.grid_columns
                        ) {
                            s.config.grid_columns = "4";
                        }
                        return {
                            id: s.id,
                            type: s.type,
                            title: s.title || null,
                            style: s.style || null,
                            config: s.config || {},
                            sort_order: i,
                            is_active: s.is_active,
                            start_at: s.start_at || null,
                            end_at: s.end_at || null,
                            zone_ids: s.zone_ids || [],
                            items: (s.items || []).map((it, j) => ({
                                id: it.id,
                                purpose: it.purpose || "display",
                                itemable_type: it.itemable_type || null,
                                itemable_id: it.itemable_id || null,
                                title: it.title || null,
                                link_url: it.link_url || null,
                                image: it.image || null,
                                image_web_mobile: it.image_web_mobile || null,
                                config: it.config || {},
                                sort_order: j,
                            })),
                        };
                    }),
            };
        }

        function validateBeforeSave() {
            for (let i = 0; i < state.sections.length; i++) {
                const s = state.sections[i];
                const withTarget = (s.items || []).filter(
                    (it) => it.itemable_id,
                );

                if (
                    s.type === "hero" &&
                    s.config.layout === "items" &&
                    withTarget.length < 1
                ) {
                    return {
                        index: i,
                        message: L(
                            "heroItemsRequired",
                            "Select at least one item for the hero section.",
                        ),
                    };
                }
                if (s.type === "products" && withTarget.length < 1) {
                    return {
                        index: i,
                        message: L(
                            "productsTargetRequired",
                            "Select at least one category or product for the products section.",
                        ),
                    };
                }
                if (
                    (s.type === "categories" || s.type === "brands") &&
                    s.config.source === "custom" &&
                    withTarget.length < 1
                ) {
                    return {
                        index: i,
                        message: L(
                            "customItemsRequired",
                            "Select at least one item for a custom selection.",
                        ),
                    };
                }
                if (
                    s.type === "categories" &&
                    s.config.source === "custom" &&
                    s.style === "custom_image"
                ) {
                    const missingMediaOrTarget = (s.items || []).some(
                        (it) =>
                            !it.itemable_id ||
                            (!it._imageFile && !it.image && !it._imagePreview),
                    );
                    if (missingMediaOrTarget) {
                        return {
                            index: i,
                            message: L(
                                "categoryCustomImageRequired",
                                "Each custom image category item must have a category selected and an image uploaded.",
                            ),
                        };
                    }
                }
                if (
                    s.type === "brands" &&
                    s.config.source === "custom" &&
                    s.style === "custom_image"
                ) {
                    const missingMediaOrTarget = (s.items || []).some(
                        (it) =>
                            !it.itemable_id ||
                            (!it._imageFile && !it.image && !it._imagePreview),
                    );
                    if (missingMediaOrTarget) {
                        return {
                            index: i,
                            message: L(
                                "brandCustomImageRequired",
                                "Each custom image brand item must have a brand selected and an image uploaded.",
                            ),
                        };
                    }
                }
                if (s.type === "banners" && (s.items || []).length < 1) {
                    return {
                        index: i,
                        message: L(
                            "bannerItemsRequired",
                            "Add at least one banner item before saving.",
                        ),
                    };
                }
                if (s.type === "banners") {
                    const hasInvalidBannerTarget = (s.items || []).some(
                        (it) => {
                            const linkType = currentBannerLinkType(it);

                            return linkType === "custom"
                                ? !it.link_url
                                : !it.itemable_id;
                        },
                    );
                    if (hasInvalidBannerTarget) {
                        return {
                            index: i,
                            message: L(
                                "bannerLinkTargetRequired",
                                "Each banner item must have a selected target or custom URL.",
                            ),
                        };
                    }

                    const hasInvalidCustomBannerImage = (s.items || []).some(
                        (it) => {
                            const linkType = currentBannerLinkType(it);
                            return (
                                linkType === "custom" &&
                                !it._imageFile &&
                                !it.image &&
                                !it._imagePreview
                            );
                        },
                    );
                    if (hasInvalidCustomBannerImage) {
                        return {
                            index: i,
                            message: L(
                                "bannerCustomImageRequired",
                                "Each custom URL banner item must have an image uploaded.",
                            ),
                        };
                    }
                }
                if (s.type === "play_image") {
                    if (!(s.items || []).length) {
                        return {
                            index: i,
                            message:
                                "Add at least one Play Image item before saving.",
                        };
                    }

                    const hasMissingImage = (s.items || []).some(
                        (it) =>
                            !it._imageFile &&
                            !it._imagePreview &&
                            !it.image &&
                            !it._imageMobileFile &&
                            !it._imageMobilePreview &&
                            !it.image_web_mobile,
                    );
                    if (hasMissingImage) {
                        return {
                            index: i,
                            message:
                                "Each Play Image item must have an image uploaded.",
                        };
                    }

                    const hasInvalidTarget = (s.items || []).some((it) => {
                        const linkType = currentBannerLinkType(it);

                        if (linkType === "none") return false;
                        return linkType === "custom"
                            ? !it.link_url
                            : !it.itemable_id;
                    });
                    if (hasInvalidTarget) {
                        return {
                            index: i,
                            message:
                                "Each linked Play Image item must have a selected target or custom URL.",
                        };
                    }
                }
            }
            return null;
        }

        function save() {
            if (!isDirty) {
                Toast.fire({ icon: "info", title: "No changes to save" });
                return;
            }
            const problem = validateBeforeSave();
            if (problem) {
                sel = problem.index;
                renderSections();
                renderEditor();
                Toast.fire({ icon: "error", title: problem.message });
                return;
            }

            const saveBtnEl = root.querySelector('[data-role="save"]');
            let originalSaveBtnContent = "";
            if (saveBtnEl) {
                saveBtnEl.disabled = true;
                originalSaveBtnContent = saveBtnEl.innerHTML;
                saveBtnEl.innerHTML = `<div class="spinner-border spinner-border-sm text-white me-2" role="status"><span class="visually-hidden">Loading...</span></div> ${originalSaveBtnContent}`;
            }

            const fd = new FormData();
            appendFormData(fd, serialize(), "");
            state.sections.forEach((s, i) => {
                if (s._mediaFiles)
                    Object.keys(s._mediaFiles).forEach((kind) => {
                        if (s._mediaFiles[kind])
                            fd.append(
                                "media[" + i + "][" + kind + "]",
                                s._mediaFiles[kind],
                            );
                    });
                (s.items || []).forEach((it, j) => {
                    if (it._removeImage) {
                        fd.append(
                            "remove_item_media[" + i + "][" + j + "]",
                            "1",
                        );
                    }
                    if (it._imageFile)
                        fd.append(
                            "item_media[" + i + "][" + j + "]",
                            it._imageFile,
                        );
                    if (it._imageMobileFile)
                        fd.append(
                            "item_media_mobile[" + i + "][" + j + "]",
                            it._imageMobileFile,
                        );
                });
                if (s._rowBackgroundFiles) {
                    Object.keys(s._rowBackgroundFiles).forEach((rowIndex) => {
                        if (s._rowBackgroundFiles[rowIndex]) {
                            fd.append(
                                "row_media[" + i + "][" + rowIndex + "]",
                                s._rowBackgroundFiles[rowIndex],
                            );
                        }
                    });
                }
            });
            api("post", cfg.saveUrl, fd)
                .then((r) => {
                    if (saveBtnEl) {
                        saveBtnEl.disabled = false;
                        saveBtnEl.innerHTML = originalSaveBtnContent;
                    }
                    const d = r.data.data || {};
                    const curSel = sel;
                    state.sections = (d.sections || []).map(normalizeSection);
                    sel = Math.min(curSel, state.sections.length - 1);
                    updateDraftState(false);
                    pushHistory();
                    renderSections();
                    renderEditor();
                    Toast.fire({ icon: "success", title: r.data.message });
                })
                .catch((err) => {
                    if (saveBtnEl) {
                        saveBtnEl.disabled = false;
                        saveBtnEl.innerHTML = originalSaveBtnContent;
                    }
                    Toast.fire({
                        icon: "error",
                        title: err.response?.data?.message || "Save failed",
                    });
                });
        }

        /* ---------- Revisions History Modal ---------- */
        if (openRevisionsBtn) {
            openRevisionsBtn.addEventListener("click", () => {
                const u = new URL(
                    root.dataset.showUrl.replace("/layout", "/revisions"),
                    window.location.origin,
                );
                u.searchParams.set("platform", state.platform);
                u.searchParams.set("scope_type", state.scope_type);
                if (state.scope_id)
                    u.searchParams.set("scope_id", state.scope_id);

                api("get", u.toString())
                    .then((r) => {
                        const revisions = r.data.data || [];
                        const listContainer =
                            document.getElementById("hb-revisions-list");
                        if (!listContainer) return;

                        if (!revisions.length) {
                            listContainer.innerHTML =
                                '<div class="text-center text-muted py-4"><i class="ti ti-history-off fs-1 mb-2 d-block"></i>No layout revisions saved yet.</div>';
                        } else {
                            listContainer.innerHTML = revisions
                                .map((rev) => {
                                    return (
                                        '<div class="hb-revision-card">' +
                                        "<div>" +
                                        '<div class="fw-bold fs-3 text-dark">' +
                                        esc(rev.name) +
                                        "</div>" +
                                        '<div class="text-muted fs-5"><i class="ti ti-user me-1"></i>' +
                                        esc(rev.creator_name) +
                                        ' &bull; <i class="ti ti-clock me-1"></i>' +
                                        esc(rev.created_at_human) +
                                        " (" +
                                        esc(rev.created_at) +
                                        ")</div>" +
                                        "</div>" +
                                        '<button type="button" class="btn btn-outline-primary btn-sm" data-restore-rev="' +
                                        rev.id +
                                        '">' +
                                        '<i class="ti ti-restore me-1"></i>Restore Snapshot</button>' +
                                        "</div>"
                                    );
                                })
                                .join("");

                            listContainer
                                .querySelectorAll("[data-restore-rev]")
                                .forEach((btn) => {
                                    btn.addEventListener("click", () => {
                                        const revId = btn.dataset.restoreRev;
                                        const targetRev = revisions.find(
                                            (x) =>
                                                String(x.id) === String(revId),
                                        );
                                        if (!targetRev) return;

                                        ConfirmModal.open(
                                            "Restore layout snapshot from " +
                                                targetRev.created_at +
                                                "? Unsaved changes will be replaced.",
                                            () => {
                                                const d =
                                                    targetRev.payload || {};
                                                state.sections = (
                                                    d.sections || []
                                                ).map(normalizeSection);
                                                sel = Math.min(
                                                    0,
                                                    state.sections.length - 1,
                                                );
                                                markDirty();
                                                renderSections();
                                                renderEditor();
                                                if (window.jQuery)
                                                    window
                                                        .jQuery(
                                                            "#hbRevisionsModal",
                                                        )
                                                        .modal("hide");
                                                Toast.fire({
                                                    icon: "success",
                                                    title: "Layout snapshot restored",
                                                });
                                            },
                                        );
                                    });
                                });
                        }

                        showModal("#hbRevisionsModal");
                    })
                    .catch(() => {
                        Toast.fire({
                            icon: "error",
                            title: "Failed to load revisions history",
                        });
                    });
            });
        }

        /* ---------- type picker + scope + init ---------- */
        function renderTypePicker() {
            typePickerEl.innerHTML = TYPES.map(
                (t) =>
                    '<a href="#" class="dropdown-item py-2 px-2 d-flex align-items-center gap-2 rounded" data-add="' +
                    t[0] +
                    '"><i class="ti ' +
                    ICON[t[0]] +
                    ' text-primary fs-2 me-1"></i><span>' +
                    esc(t[1]) +
                    "</span></a>",
            ).join("");
            typePickerEl.querySelectorAll("[data-add]").forEach((b) =>
                b.addEventListener("click", (e) => {
                    e.preventDefault();
                    state.sections.push(defaultSection(b.dataset.add));
                    markDirty();
                    selectSection(state.sections.length - 1);
                }),
            );
        }

        function initScope() {
            scopeCatTs = new TomSelect(
                root.querySelector('[data-role="scope-category"]'),
                {
                    valueField: "id",
                    labelField: "title",
                    searchField: "title",
                    placeholder: L("selectCategory", "Select category"),
                    load: function (query, cb) {
                        const u = new URL(
                            cfg.categorySearch,
                            window.location.origin,
                        );
                        u.searchParams.set("type", "root");
                        u.searchParams.set("search", query);
                        fetch(u.toString(), {
                            headers: {
                                "X-Requested-With": "XMLHttpRequest",
                                Accept: "application/json",
                            },
                        })
                            .then((r) => r.json())
                            .then((rows) =>
                                cb(
                                    (rows || []).map((r) => ({
                                        id: r.id != null ? r.id : r.value,
                                        title: r.text || r.title || r.name,
                                    })),
                                ),
                            )
                            .catch(() => cb());
                    },
                },
            );
            scopeCatTs.on("change", (v) => {
                if (state.scope_type === "category" && v) {
                    state.scope_id = +v;
                    load();
                }
            });

            const scopeZoneEl = root.querySelector('[data-role="scope-zone"]');
            if (scopeZoneEl) {
                scopeZoneTs = new TomSelect(scopeZoneEl, {
                    valueField: "id",
                    labelField: "title",
                    searchField: "title",
                    placeholder: L("selectZone", "Select delivery zone"),
                    load: function (query, cb) {
                        const u = new URL(
                            cfg.zoneSearch,
                            window.location.origin,
                        );
                        if (query) u.searchParams.set("search", query);
                        fetch(u.toString(), {
                            headers: {
                                "X-Requested-With": "XMLHttpRequest",
                                Accept: "application/json",
                            },
                        })
                            .then((r) => r.json())
                            .then((rows) =>
                                cb(
                                    (rows || []).map((r) => ({
                                        id: r.value != null ? r.value : r.id,
                                        title: r.text || r.name || r.title,
                                    })),
                                ),
                            )
                            .catch(() => cb());
                    },
                    onInitialize: function () {
                        this.load("");
                    },
                });
                scopeZoneTs.on("change", (v) => {
                    if (state.scope_type === "zone" && v) {
                        state.zone_id = +v;
                        state.scope_id = null;
                        load();
                    }
                });
            }

            const catWrap = scopeCatTs ? scopeCatTs.wrapper : null;
            if (catWrap) {
                catWrap.classList.add("d-none");
                catWrap.style.width = "220px";
            }

            const zoneWrap = scopeZoneTs ? scopeZoneTs.wrapper : null;
            if (zoneWrap) {
                zoneWrap.classList.add("d-none");
                zoneWrap.style.width = "220px";
            }

            root.querySelectorAll(
                '[data-role="scope"] input.form-selectgroup-input',
            ).forEach((inp) =>
                inp.addEventListener("change", () => {
                    if (!inp.checked) return;
                    state.scope_type = inp.value;
                    if (catWrap)
                        catWrap.classList.toggle(
                            "d-none",
                            state.scope_type !== "category",
                        );
                    if (zoneWrap)
                        zoneWrap.classList.toggle(
                            "d-none",
                            state.scope_type !== "zone",
                        );

                    if (state.scope_type === "global") {
                        state.scope_id = null;
                        state.zone_id = null;
                        load();
                    } else if (
                        state.scope_type === "category" &&
                        scopeCatTs &&
                        scopeCatTs.getValue()
                    ) {
                        state.scope_id = +scopeCatTs.getValue();
                        state.zone_id = null;
                        load();
                    } else if (
                        state.scope_type === "zone" &&
                        scopeZoneTs &&
                        scopeZoneTs.getValue()
                    ) {
                        state.zone_id = +scopeZoneTs.getValue();
                        state.scope_id = null;
                        load();
                    }
                }),
            );

            // Category Preview Pill Clicks (Event Delegated)
            // Category Preview Pill Clicks (Event Delegated)
            root.addEventListener("click", (e) => {
                const pill = e.target.closest('[data-role="cat-pill"]');
                if (!pill) return;

                e.preventDefault();
                const catId = pill.dataset.catId;
                const catTitle = pill.dataset.catTitle;

                root.querySelectorAll('[data-role="cat-pill"]').forEach((p) =>
                    p.classList.remove("active"),
                );
                pill.classList.add("active");
                syncCatPillIcons();

                // Dynamically update header background styling
                const headerEl = root.querySelector(
                    ".hb-phone-header, .hb-web-header",
                );
                if (headerEl && typeof applyPreviewHeaderStyle === "function") {
                    const catObj = (window._hbCategories || []).find(
                        (c) => String(c.id) === String(catId),
                    ) || {
                        id: catId,
                        title: catTitle,
                        background_type: pill.dataset.catBgType,
                        background_color: pill.dataset.catBgColor,
                        background_color_web: pill.dataset.catBgColorWeb,
                        background_color_app: pill.dataset.catBgColorApp,
                        background_image: pill.dataset.catBgImage,
                        background_image_web: pill.dataset.catBgImageWeb,
                        background_image_web_mobile:
                            pill.dataset.catBgImageWebMobile,
                        background_image_app: pill.dataset.catBgImageApp,
                        background_animation: pill.dataset.catBgAnim,
                        background_animation_web: pill.dataset.catBgAnimWeb,
                        background_animation_app: pill.dataset.catBgAnimApp,
                        font_color: pill.dataset.catFontColor,
                    };
                    applyPreviewHeaderStyle(
                        headerEl,
                        PLATFORM,
                        catObj,
                        window._hbGlobalSettings,
                    );
                }

                if (catId === "all") {
                    state.scope_type = "global";
                    state.scope_id = null;
                    const globRadio = root.querySelector(
                        '[data-role="scope"] input[value="global"]',
                    );
                    if (globRadio) globRadio.checked = true;
                    if (catWrap) catWrap.classList.add("d-none");
                    load();
                } else if (catId) {
                    state.scope_type = "category";
                    state.scope_id = +catId;
                    const catRadio = root.querySelector(
                        '[data-role="scope"] input[value="category"]',
                    );
                    if (catRadio) catRadio.checked = true;
                    if (catWrap) catWrap.classList.remove("d-none");

                    if (scopeCatTs) {
                        scopeCatTs.addOption({
                            id: +catId,
                            title: catTitle || "Category #" + catId,
                        });
                        scopeCatTs.setValue(String(catId), true);
                    }
                    load();
                }
            });
        }

        /* ---------- Templates Modal & Preset Registry ---------- */
        const TEMPLATES = safeJson(root.dataset.templates, []);
        const THEMES = safeJson(root.dataset.themes, []);
        const openTemplatesBtns = root.querySelectorAll(
            '[data-role="open-templates"]',
        );

        let activeTmplCategory = "all";
        let activeTmplSearchQuery = "";
        let savedPresetsList = [];

        function fetchSavedPresets(cb) {
            api("get", "/admin/home-builder/presets")
                .then((r) => {
                    savedPresetsList = r.data && r.data.data ? r.data.data : [];
                    if (cb) cb();
                })
                .catch(() => {
                    savedPresetsList = [];
                    if (cb) cb();
                });
        }

        function openSavePresetModal(s) {
            const modalEl = document.getElementById("hbSavePresetModal");
            if (!modalEl) return;

            const nameInput = document.getElementById("hb-preset-name-input");
            const descInput = document.getElementById("hb-preset-desc-input");
            const confirmBtn = document.getElementById(
                "hb-save-preset-confirm-btn",
            );

            if (nameInput)
                nameInput.value = s.title || nameOf(s.type, TYPE) || "";
            if (descInput) descInput.value = "";

            const onConfirm = () => {
                const name = nameInput ? nameInput.value.trim() : "";
                if (!name) {
                    Toast.fire({
                        icon: "error",
                        title: "Preset name is required",
                    });
                    return;
                }

                const desc = descInput ? descInput.value.trim() : "";
                const cleanItems = (s.items || []).map((it) => ({
                    purpose: it.purpose || "display",
                    itemable_type: it.itemable_type || null,
                    itemable_id: it.itemable_id || null,
                    itemable_label: it.itemable_label || null,
                    title: it.title || "",
                    link_url: it.link_url || "",
                    image: it.image || null,
                    image_web_mobile: it.image_web_mobile || null,
                    config: it.config
                        ? JSON.parse(JSON.stringify(it.config))
                        : {},
                }));

                const payload = {
                    name,
                    description: desc,
                    type: s.type,
                    style: s.style || null,
                    config: s.config || {},
                    items: cleanItems,
                };

                api("post", "/admin/home-builder/presets", payload)
                    .then((r) => {
                        hideModal(modalEl);
                        Toast.fire({
                            icon: "success",
                            title:
                                r.data && r.data.message
                                    ? r.data.message
                                    : "Preset saved successfully!",
                        });
                        fetchSavedPresets();
                    })
                    .catch((err) => {
                        const msg =
                            err.response &&
                            err.response.data &&
                            err.response.data.message
                                ? err.response.data.message
                                : "Failed to save preset";
                        Toast.fire({ icon: "error", title: msg });
                    });
            };

            if (confirmBtn) {
                const newBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
                newBtn.addEventListener("click", onConfirm);
            }

            showModal(modalEl);
        }

        function getTemplatePreviewHtml(t, isTheme) {
            if (isTheme) {
                const sections = t.sections || [];
                const barsHtml = sections
                    .slice(0, 4)
                    .map((s) => {
                        const type = s.type || "hero";
                        const name =
                            s.title ||
                            type.charAt(0).toUpperCase() + type.slice(1);
                        return (
                            '<div class="hb-mini-theme-bar hb-mini-theme-bar-' +
                            esc(type) +
                            '">' +
                            '<i class="ti ti-layer-group me-1"></i>' +
                            esc(name) +
                            "</div>"
                        );
                    })
                    .join("");
                return (
                    '<div class="hb-mini-theme-stack">' + barsHtml + "</div>"
                );
            }

            const cat = t.category || t.type || "hero";
            if (cat === "hero") {
                const items = t.items || [];
                const chipsHtml = items
                    .slice(0, 2)
                    .map(
                        (it) =>
                            '<span class="hb-mini-chip">' +
                            esc(it.title || "Deal") +
                            "</span>",
                    )
                    .join("");
                return (
                    '<div class="hb-mini-hero">' +
                    '<div class="hb-mini-hero-banner">' +
                    '<div class="hb-mini-hero-icon"><i class="ti ' +
                    esc(t.icon || "ti-flame") +
                    '"></i></div>' +
                    '<div class="hb-mini-hero-text">' +
                    '<div class="hb-mini-line-lg"></div>' +
                    '<div class="hb-mini-line-sm"></div>' +
                    "</div></div>" +
                    '<div class="hb-mini-hero-chips">' +
                    chipsHtml +
                    "</div>" +
                    "</div>"
                );
            }

            if (cat === "products") {
                return (
                    '<div class="hb-mini-products">' +
                    '<div class="hb-mini-product-card"><div class="hb-mini-product-img"><i class="ti ti-photo"></i></div><div class="hb-mini-product-title"></div><div class="hb-mini-product-price"></div></div>' +
                    '<div class="hb-mini-product-card"><div class="hb-mini-product-img"><i class="ti ti-photo"></i></div><div class="hb-mini-product-title"></div><div class="hb-mini-product-price"></div></div>' +
                    '<div class="hb-mini-product-card"><div class="hb-mini-product-img"><i class="ti ti-photo"></i></div><div class="hb-mini-product-title"></div><div class="hb-mini-product-price"></div></div>' +
                    "</div>"
                );
            }

            if (cat === "categories") {
                return (
                    '<div class="hb-mini-categories">' +
                    '<div class="hb-mini-cat-item"><div class="hb-mini-cat-avatar"><i class="ti ti-shirt"></i></div><div class="hb-mini-cat-label"></div></div>' +
                    '<div class="hb-mini-cat-item"><div class="hb-mini-cat-avatar"><i class="ti ti-device-mobile"></i></div><div class="hb-mini-cat-label"></div></div>' +
                    '<div class="hb-mini-cat-item"><div class="hb-mini-cat-avatar"><i class="ti ti-home"></i></div><div class="hb-mini-cat-label"></div></div>' +
                    '<div class="hb-mini-cat-item"><div class="hb-mini-cat-avatar"><i class="ti ti-tag"></i></div><div class="hb-mini-cat-label"></div></div>' +
                    "</div>"
                );
            }

            if (cat === "brands") {
                return (
                    '<div class="hb-mini-brands">' +
                    '<div class="hb-mini-brand-card"><i class="ti ti-building-store"></i></div>' +
                    '<div class="hb-mini-brand-card"><i class="ti ti-award"></i></div>' +
                    '<div class="hb-mini-brand-card"><i class="ti ti-shield-check"></i></div>' +
                    "</div>"
                );
            }

            if (cat === "play_image") {
                return (
                    '<div class="hb-mini-banners">' +
                    '<div class="hb-mini-banner-main"><i class="ti ti-layout-grid-add"></i></div>' +
                    '<div class="hb-mini-banner-peek"></div>' +
                    "</div>"
                );
            }

            if (cat === "banners") {
                return (
                    '<div class="hb-mini-banners">' +
                    '<div class="hb-mini-banner-main"><i class="ti ti-layout-board"></i></div>' +
                    '<div class="hb-mini-banner-peek"></div>' +
                    "</div>"
                );
            }

            return (
                '<div class="text-muted"><i class="ti ' +
                esc(t.icon || "ti-layout-grid") +
                ' display-4 text-primary"></i></div>'
            );
        }

        function renderTemplateCards(category, query) {
            if (category !== undefined) activeTmplCategory = category;
            if (query !== undefined)
                activeTmplSearchQuery = query.trim().toLowerCase();

            const container = document.getElementById(
                "hb-template-cards-container",
            );
            if (!container) return;

            const curBuilder = window.activeHomeBuilder || {
                TEMPLATES: TEMPLATES,
                THEMES: THEMES,
                state: state,
            };
            const currentTemplates = curBuilder.TEMPLATES || TEMPLATES || [];
            const currentThemes = curBuilder.THEMES || THEMES || [];

            let html = "";
            let matchCount = 0;

            // Render Full Themes
            if (
                activeTmplCategory === "all" ||
                activeTmplCategory === "themes"
            ) {
                currentThemes.forEach((t) => {
                    const titleMatch =
                        !activeTmplSearchQuery ||
                        t.title.toLowerCase().includes(activeTmplSearchQuery);
                    const descMatch =
                        !activeTmplSearchQuery ||
                        (t.description || "")
                            .toLowerCase()
                            .includes(activeTmplSearchQuery);
                    const badgeMatch =
                        !activeTmplSearchQuery ||
                        (t.badge || "")
                            .toLowerCase()
                            .includes(activeTmplSearchQuery);

                    if (titleMatch || descMatch || badgeMatch) {
                        matchCount++;
                        const previewHtml = getTemplatePreviewHtml(t, true);
                        html +=
                            '<div class="col-12 col-md-6 col-lg-4 hb-tmpl-item" data-tmpl-cat="themes">' +
                            '<div class="hb-tmpl-card">' +
                            '<div class="hb-tmpl-preview">' +
                            '<span class="badge bg-primary hb-tmpl-badge">' +
                            esc(t.badge || "Theme") +
                            "</span>" +
                            previewHtml +
                            "</div>" +
                            '<div class="hb-tmpl-info">' +
                            '<div class="hb-tmpl-title">' +
                            esc(t.title) +
                            "</div>" +
                            '<div class="hb-tmpl-desc">' +
                            esc(t.description) +
                            "</div>" +
                            '<div class="hb-tmpl-footer">' +
                            '<span class="hb-tmpl-count-text"><i class="ti ti-layers-intersect me-1"></i>' +
                            t.sections.length +
                            " Sections</span>" +
                            '<button type="button" class="btn btn-sm btn-primary" data-apply-theme="' +
                            esc(t.id) +
                            '">' +
                            '<i class="ti ti-wand me-1"></i>Apply Theme</button>' +
                            "</div></div></div></div>";
                    }
                });
            }

            // Render Section Templates
            if (
                activeTmplCategory !== "themes" &&
                activeTmplCategory !== "saved"
            ) {
                const list =
                    activeTmplCategory === "all"
                        ? currentTemplates
                        : currentTemplates.filter(
                              (x) => x.category === activeTmplCategory,
                          );

                list.forEach((t) => {
                    const titleMatch =
                        !activeTmplSearchQuery ||
                        t.title.toLowerCase().includes(activeTmplSearchQuery);
                    const descMatch =
                        !activeTmplSearchQuery ||
                        (t.description || "")
                            .toLowerCase()
                            .includes(activeTmplSearchQuery);
                    const badgeMatch =
                        !activeTmplSearchQuery ||
                        (t.badge || "")
                            .toLowerCase()
                            .includes(activeTmplSearchQuery);

                    if (titleMatch || descMatch || badgeMatch) {
                        matchCount++;
                        const previewHtml = getTemplatePreviewHtml(t, false);
                        html +=
                            '<div class="col-12 col-md-6 col-lg-4 hb-tmpl-item" data-tmpl-cat="' +
                            esc(t.category) +
                            '">' +
                            '<div class="hb-tmpl-card">' +
                            '<div class="hb-tmpl-preview">' +
                            (t.badge
                                ? '<span class="badge bg-primary-lt text-primary hb-tmpl-badge">' +
                                  esc(t.badge) +
                                  "</span>"
                                : "") +
                            previewHtml +
                            "</div>" +
                            '<div class="hb-tmpl-info">' +
                            '<div class="hb-tmpl-title">' +
                            esc(t.title) +
                            "</div>" +
                            '<div class="hb-tmpl-desc">' +
                            esc(t.description) +
                            "</div>" +
                            '<div class="hb-tmpl-footer">' +
                            '<span class="hb-tmpl-count-text text-capitalize"><i class="ti ' +
                            esc(t.icon || "ti-layout-grid") +
                            ' me-1"></i>' +
                            esc(t.category) +
                            "</span>" +
                            '<button type="button" class="btn btn-sm btn-outline-primary" data-insert-template="' +
                            esc(t.id) +
                            '">' +
                            '<i class="ti ti-plus me-1"></i>Insert</button>' +
                            "</div></div></div></div>";
                    }
                });
            }

            // Render User-Saved Presets
            if (
                activeTmplCategory === "saved" ||
                activeTmplCategory === "all"
            ) {
                savedPresetsList.forEach((t) => {
                    const titleMatch =
                        !activeTmplSearchQuery ||
                        t.name.toLowerCase().includes(activeTmplSearchQuery);
                    const descMatch =
                        !activeTmplSearchQuery ||
                        (t.description || "")
                            .toLowerCase()
                            .includes(activeTmplSearchQuery);
                    const typeMatch =
                        !activeTmplSearchQuery ||
                        (t.type || "")
                            .toLowerCase()
                            .includes(activeTmplSearchQuery);

                    if (titleMatch || descMatch || typeMatch) {
                        matchCount++;
                        const previewHtml = getTemplatePreviewHtml(t, false);
                        html +=
                            '<div class="col-12 col-md-6 col-lg-4 hb-tmpl-item" data-tmpl-cat="saved">' +
                            '<div class="hb-tmpl-card">' +
                            '<div class="hb-tmpl-preview">' +
                            '<span class="badge bg-warning-lt text-warning hb-tmpl-badge"><i class="ti ti-bookmark me-1"></i>Saved</span>' +
                            previewHtml +
                            "</div>" +
                            '<div class="hb-tmpl-info">' +
                            '<div class="hb-tmpl-title">' +
                            esc(t.name) +
                            "</div>" +
                            '<div class="hb-tmpl-desc">' +
                            esc(
                                t.description ||
                                    "Custom " + esc(t.type) + " preset",
                            ) +
                            "</div>" +
                            '<div class="hb-tmpl-footer">' +
                            '<span class="hb-tmpl-count-text text-capitalize"><i class="ti ti-tag me-1"></i>' +
                            esc(t.type) +
                            "</span>" +
                            '<div class="btn-group btn-group-sm">' +
                            '<button type="button" class="btn btn-outline-primary" data-insert-saved-preset="' +
                            t.id +
                            '">' +
                            '<i class="ti ti-plus me-1"></i>Insert</button>' +
                            '<button type="button" class="btn btn-outline-danger" data-delete-saved-preset="' +
                            t.id +
                            '" title="Delete preset">' +
                            '<i class="ti ti-trash"></i></button>' +
                            "</div>" +
                            "</div></div></div></div>";
                    }
                });
            }

            if (!matchCount) {
                const queryText = activeTmplSearchQuery
                    ? ' matching "' + esc(activeTmplSearchQuery) + '"'
                    : "";
                const emptyTitle =
                    activeTmplCategory === "saved"
                        ? "No saved presets found"
                        : "No templates found";
                const emptySubtitle =
                    activeTmplCategory === "saved"
                        ? "You haven't saved any custom section presets yet. Customize any section in the editor and click 'Save as Preset' to store it here."
                        : "Try searching with a different term or choose another category tab.";
                html =
                    '<div class="col-12 py-5 text-center text-muted hb-tmpl-empty">' +
                    '<i class="ti ti-search-off display-4 mb-2 d-block text-secondary"></i>' +
                    '<h4 class="fw-semibold">' +
                    emptyTitle +
                    queryText +
                    "</h4>" +
                    '<p class="fs-4 text-secondary mb-0">' +
                    emptySubtitle +
                    "</p>" +
                    "</div>";
            }

            container.innerHTML = html;

            // Wire insert template buttons
            container
                .querySelectorAll("[data-insert-template]")
                .forEach((btn) => {
                    btn.addEventListener("click", () => {
                        const tmplId = btn.dataset.insertTemplate;
                        const cur = window.activeHomeBuilder || {
                            insertSectionTemplate: insertSectionTemplate,
                        };
                        if (cur && cur.insertSectionTemplate) {
                            cur.insertSectionTemplate(tmplId);
                        } else {
                            insertSectionTemplate(tmplId);
                        }
                        hideModal("#hbTemplatesModal");
                    });
                });

            // Wire insert saved preset buttons
            container
                .querySelectorAll("[data-insert-saved-preset]")
                .forEach((btn) => {
                    btn.addEventListener("click", () => {
                        const presetId = +btn.dataset.insertSavedPreset;
                        const cur = window.activeHomeBuilder || {
                            insertSavedPreset: insertSavedPreset,
                        };
                        if (cur && cur.insertSavedPreset) {
                            cur.insertSavedPreset(presetId);
                        } else {
                            insertSavedPreset(presetId);
                        }
                        hideModal("#hbTemplatesModal");
                    });
                });

            // Wire delete saved preset buttons
            container
                .querySelectorAll("[data-delete-saved-preset]")
                .forEach((btn) => {
                    btn.addEventListener("click", () => {
                        const presetId = +btn.dataset.deleteSavedPreset;
                        ConfirmModal.open(
                            "Delete this saved preset permanently?",
                            () => {
                                api(
                                    "delete",
                                    "/admin/home-builder/presets/" + presetId,
                                )
                                    .then((r) => {
                                        Toast.fire({
                                            icon: "success",
                                            title:
                                                r.data && r.data.message
                                                    ? r.data.message
                                                    : "Preset deleted",
                                        });
                                        fetchSavedPresets(() =>
                                            renderTemplateCards("saved"),
                                        );
                                    })
                                    .catch(() => {
                                        Toast.fire({
                                            icon: "error",
                                            title: "Failed to delete preset",
                                        });
                                    });
                            },
                        );
                    });
                });

            // Wire apply theme buttons
            container.querySelectorAll("[data-apply-theme]").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const themeId = btn.dataset.applyTheme;
                    hideModal("#hbTemplatesModal");
                    ConfirmModal.open(
                        "Apply this full theme preset? This will append all theme sections to your active layout.",
                        () => {
                            const cur = window.activeHomeBuilder || {
                                applyThemePreset: applyThemePreset,
                            };
                            if (cur && cur.applyThemePreset) {
                                cur.applyThemePreset(themeId);
                            } else {
                                applyThemePreset(themeId);
                            }
                        },
                    );
                });
            });
        }

        function insertSavedPreset(presetId) {
            const preset = savedPresetsList.find((x) => x.id === presetId);
            if (!preset) return;

            const sec = defaultSection(preset.type);
            sec.title = preset.name;
            if (preset.style) sec.style = preset.style;
            if (preset.config)
                sec.config = JSON.parse(JSON.stringify(preset.config));
            if (preset.items && preset.items.length) {
                sec.items = preset.items.map((it) => ({
                    id: null,
                    purpose: it.purpose || "display",
                    itemable_type: it.itemable_type || null,
                    itemable_id: it.itemable_id || null,
                    itemable_label: it.itemable_label || null,
                    title: it.title || "",
                    link_url: it.link_url || "",
                    _link_type: it.link_url
                        ? "custom"
                        : it.itemable_type || null,
                    image: it.image || null,
                    image_web_mobile: it.image_web_mobile || null,
                    config: it.config
                        ? JSON.parse(JSON.stringify(it.config))
                        : {},
                }));
            }

            const normalizedSec = normalizeSection(sec);
            state.sections.push(normalizedSec);
            markDirty();
            renderSections();
            selectSection(state.sections.length - 1);
            Toast.fire({
                icon: "success",
                title: "Saved preset inserted into layout",
            });
        }

        function insertSectionTemplate(tmplId) {
            const t = TEMPLATES.find((x) => x.id === tmplId);
            if (!t) return;

            const sec = defaultSection(t.type);
            sec.title = t.title_default || t.title;
            if (t.style) sec.style = t.style;
            if (t.config) sec.config = JSON.parse(JSON.stringify(t.config));
            if (t.items && t.items.length) {
                sec.items = t.items.map((it, idx) => ({
                    id: null,
                    purpose: it.purpose || "display",
                    itemable_type: it.itemable_type || null,
                    itemable_id: it.itemable_id || null,
                    itemable_label: it.itemable_label || null,
                    title:
                        it.title ||
                        (t.type === "play_image"
                            ? "Image item " + (idx + 1)
                            : ""),
                    link_url: it.link_url || "",
                    _link_type: it.link_url ? "custom" : null,
                    image: it.image || null,
                    image_web_mobile: it.image_web_mobile || null,
                    config: it.config
                        ? JSON.parse(JSON.stringify(it.config))
                        : {},
                }));
            }

            const normalizedSec = normalizeSection(sec);
            state.sections.push(normalizedSec);
            markDirty();
            renderSections();
            selectSection(state.sections.length - 1);
            Toast.fire({
                icon: "success",
                title: "Template inserted successfully",
            });
        }

        function applyThemePreset(themeId) {
            const theme = THEMES.find((x) => x.id === themeId);
            if (!theme) return;

            theme.sections.forEach((ts) => {
                const sec = defaultSection(ts.type);
                sec.title = ts.title || "";
                if (ts.style) sec.style = ts.style;
                if (ts.config)
                    sec.config = JSON.parse(JSON.stringify(ts.config));
                if (ts.items && ts.items.length) {
                    sec.items = ts.items.map((it, idx) => ({
                        id: null,
                        purpose: it.purpose || "display",
                        itemable_type: it.itemable_type || null,
                        itemable_id: it.itemable_id || null,
                        itemable_label: it.itemable_label || null,
                        title:
                            it.title ||
                            (ts.type === "play_image"
                                ? "Image item " + (idx + 1)
                                : ""),
                        link_url: it.link_url || "",
                        _link_type: it.link_url ? "custom" : null,
                        image: it.image || null,
                        image_web_mobile: it.image_web_mobile || null,
                        config: it.config
                            ? JSON.parse(JSON.stringify(it.config))
                            : {},
                    }));
                }
                const normalizedSec = normalizeSection(sec);
                state.sections.push(normalizedSec);
            });

            markDirty();
            renderSections();
            selectSection(state.sections.length - 1);
            Toast.fire({ icon: "success", title: "Full theme preset applied" });
        }

        const searchInput = document.getElementById("hb-template-search");
        const clearSearchBtn = document.getElementById(
            "hb-template-search-clear",
        );

        if (searchInput) {
            searchInput.addEventListener("input", () => {
                const val = searchInput.value;
                if (clearSearchBtn) {
                    clearSearchBtn.classList.toggle("d-none", !val.length);
                }
                renderTemplateCards(undefined, val);
            });
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener("click", () => {
                if (searchInput) {
                    searchInput.value = "";
                    clearSearchBtn.classList.add("d-none");
                    renderTemplateCards(undefined, "");
                }
            });
        }

        openTemplatesBtns.forEach((btn) =>
            btn.addEventListener("click", () => {
                window.activeHomeBuilder = {
                    TEMPLATES: TEMPLATES,
                    THEMES: THEMES,
                    state: state,
                    markDirty: markDirty,
                    renderSections: renderSections,
                    selectSection: selectSection,
                    insertSectionTemplate: insertSectionTemplate,
                    insertSavedPreset: insertSavedPreset,
                    applyThemePreset: applyThemePreset,
                };
                if (searchInput) searchInput.value = "";
                if (clearSearchBtn) clearSearchBtn.classList.add("d-none");

                // 1. Render immediately and open modal INSTANTLY (0ms UX)
                renderTemplateCards("all", "");
                const pills = document.querySelectorAll(
                    "#hb-template-tabs .nav-link",
                );
                pills.forEach((p) =>
                    p.classList.toggle("active", p.dataset.cat === "all"),
                );
                showModal("#hbTemplatesModal");

                // 2. Refresh saved presets in background
                fetchSavedPresets(() => {
                    if (
                        activeTmplCategory === "all" ||
                        activeTmplCategory === "saved"
                    ) {
                        renderTemplateCards();
                    }
                });
            }),
        );

        root._homeBuilderInstance = {
            TEMPLATES: TEMPLATES,
            THEMES: THEMES,
            state: state,
            markDirty: markDirty,
            renderSections: renderSections,
            selectSection: selectSection,
            insertSectionTemplate: insertSectionTemplate,
            insertSavedPreset: insertSavedPreset,
            applyThemePreset: applyThemePreset,
            fetchSavedPresets: fetchSavedPresets,
            renderTemplateCards: renderTemplateCards,
        };
        if (!window.homeBuilders) window.homeBuilders = {};
        window.homeBuilders[PLATFORM] = root._homeBuilderInstance;

        // Wire modal filter pills
        const modalTabPills = document.querySelectorAll(
            "#hb-template-tabs .nav-link",
        );
        modalTabPills.forEach((pill) =>
            pill.addEventListener("click", (e) => {
                e.preventDefault();
                modalTabPills.forEach((p) => p.classList.remove("active"));
                pill.classList.add("active");
                if (pill.dataset.cat === "saved" && !savedPresetsList.length) {
                    fetchSavedPresets(() => {
                        renderTemplateCards(pill.dataset.cat, undefined);
                    });
                } else {
                    renderTemplateCards(pill.dataset.cat, undefined);
                }
            }),
        );

        const addBtn = root.querySelector('[data-role="add-section"]');
        if (addBtn)
            addBtn.addEventListener("click", () => {
                renderTypePicker();
            });
        const saveBtn = root.querySelector('[data-role="save"]');
        if (saveBtn) saveBtn.addEventListener("click", save);

        initScope();
        load();
    }

    /* ---------- Live Preview Header Synchronization ---------- */
    function applyPreviewHeaderStyle(
        headerEl,
        platform,
        itemData,
        globalSettings,
    ) {
        if (!headerEl) return;

        const isGlobal = !itemData || itemData.id === "all";
        let bgType = "";
        let bgColor = "";
        let bgImage = "";
        let bgAnim = "";
        let fontColor = "";

        if (isGlobal) {
            bgType = globalSettings?.backgroundType || "";
            bgColor =
                platform === "web"
                    ? globalSettings?.backgroundColorWeb ||
                      globalSettings?.backgroundColor ||
                      "#ffffff"
                    : globalSettings?.backgroundColorApp ||
                      globalSettings?.backgroundColor ||
                      "#0066cc";
            const isMobileWeb =
                platform === "web" &&
                headerEl
                    .closest(".hb-web-frame")
                    ?.classList.contains("is-mobile");
            bgImage =
                platform === "web"
                    ? isMobileWeb && globalSettings?.backgroundImageWebMobile
                        ? globalSettings.backgroundImageWebMobile
                        : globalSettings?.backgroundImageWeb ||
                          globalSettings?.backgroundImage ||
                          ""
                    : globalSettings?.backgroundImageApp ||
                      globalSettings?.backgroundImage ||
                      "";
            bgAnim =
                platform === "web"
                    ? globalSettings?.backgroundAnimationWeb ||
                      globalSettings?.backgroundAnimation ||
                      ""
                    : globalSettings?.backgroundAnimationApp || "";
            fontColor = globalSettings?.fontColor || "";
        } else {
            bgType = itemData.background_type || itemData.bgType || "";
            bgColor =
                platform === "web"
                    ? itemData.background_color_web ||
                      itemData.bgColorWeb ||
                      itemData.background_color ||
                      itemData.bgColor ||
                      "#ffffff"
                    : itemData.background_color_app ||
                      itemData.bgColorApp ||
                      itemData.background_color ||
                      itemData.bgColor ||
                      "#0066cc";
            const isMobileWeb =
                platform === "web" &&
                headerEl
                    .closest(".hb-web-frame")
                    ?.classList.contains("is-mobile");
            bgImage =
                platform === "web"
                    ? isMobileWeb &&
                      (itemData.background_image_web_mobile ||
                          itemData.bgImageWebMobile)
                        ? itemData.background_image_web_mobile ||
                          itemData.bgImageWebMobile
                        : itemData.background_image_web ||
                          itemData.bgImageWeb ||
                          itemData.background_image ||
                          itemData.bgImage ||
                          ""
                    : itemData.background_image_app ||
                      itemData.bgImageApp ||
                      itemData.background_image ||
                      itemData.bgImage ||
                      "";
            bgAnim =
                platform === "web"
                    ? itemData.background_animation_web ||
                      itemData.bgAnimWeb ||
                      itemData.background_animation ||
                      itemData.bgAnim ||
                      ""
                    : itemData.background_animation_app ||
                      itemData.bgAnimApp ||
                      itemData.background_animation ||
                      itemData.bgAnim ||
                      "";
            fontColor = itemData.font_color || itemData.fontColor || "";

            // Fallback to global background if category has none
            if (!bgType || bgType === "none") {
                bgType = globalSettings?.backgroundType || "none";
                bgColor =
                    platform === "web"
                        ? globalSettings?.backgroundColorWeb ||
                          globalSettings?.backgroundColor ||
                          "#ffffff"
                        : globalSettings?.backgroundColorApp ||
                          globalSettings?.backgroundColor ||
                          "#0066cc";
                const isMobileWeb =
                    platform === "web" &&
                    headerEl
                        .closest(".hb-web-frame")
                        ?.classList.contains("is-mobile");
                bgImage =
                    platform === "web"
                        ? isMobileWeb &&
                          globalSettings?.backgroundImageWebMobile
                            ? globalSettings.backgroundImageWebMobile
                            : globalSettings?.backgroundImageWeb ||
                              globalSettings?.backgroundImage ||
                              ""
                        : globalSettings?.backgroundImageApp ||
                          globalSettings?.backgroundImage ||
                          "";
                bgAnim =
                    platform === "web"
                        ? globalSettings?.backgroundAnimationWeb ||
                          globalSettings?.backgroundAnimation ||
                          ""
                        : globalSettings?.backgroundAnimationApp || "";
                fontColor = fontColor || globalSettings?.fontColor || "";
            }
        }

        // Reset styles
        headerEl.style.background = "";
        headerEl.style.backgroundImage = "";
        headerEl.style.backgroundColor = "";
        headerEl.style.color = fontColor ? fontColor : "";

        let lottieEl = headerEl.querySelector(".hb-header-lottie-bg");
        if (bgType === "animation" && bgAnim) {
            if (!lottieEl) {
                lottieEl = document.createElement("lottie-player");
                lottieEl.className = "hb-header-lottie-bg";
                lottieEl.setAttribute("background", "transparent");
                lottieEl.setAttribute("speed", "1");
                lottieEl.setAttribute("loop", "");
                lottieEl.setAttribute("autoplay", "");
                lottieEl.style.cssText =
                    "position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; pointer-events: none; z-index: 0;";
                headerEl.prepend(lottieEl);
            }
            lottieEl.setAttribute("src", bgAnim);
            lottieEl.classList.remove("d-none");
        } else {
            if (lottieEl) lottieEl.classList.add("d-none");
            if (bgType === "image" && bgImage) {
                headerEl.style.backgroundImage = `url("${bgImage}")`;
                headerEl.style.backgroundSize = "cover";
                headerEl.style.backgroundPosition = "center";
                headerEl.style.backgroundRepeat = "no-repeat";
            } else if (bgType === "color" && bgColor) {
                headerEl.style.backgroundColor = bgColor;
            } else {
                headerEl.style.backgroundColor =
                    platform === "web" ? "#ffffff" : "#0066cc";
            }
        }
    }

    function refreshHomeBuilderPreviews() {
        fetch("/admin/home-builder/global-appearance", {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        })
            .then((r) => r.json())
            .then((res) => {
                const data = res.data || {};
                const globalSettings = data.settings || data;
                const categories = data.categories || [];
                const allTitle = globalSettings.title || "All Categories";
                const allIcon = globalSettings.icon || "";
                const allActiveIcon = globalSettings.activeIcon || allIcon;
                const searchLabels = globalSettings.searchLabels || [];
                const searchPlaceholder =
                    searchLabels.length > 0
                        ? `Search "${searchLabels[0].replace(/_/g, " ")}"`
                        : 'Search "tea"';

                // Update search placeholders in phone and web preview headers
                document
                    .querySelectorAll(
                        ".hb-phone-search-bar span, .hb-web-search-input",
                    )
                    .forEach((el) => {
                        if (el.tagName === "INPUT") {
                            el.placeholder = searchPlaceholder;
                        } else {
                            el.textContent = searchPlaceholder;
                        }
                    });

                // Update Phone Categories Bar
                document
                    .querySelectorAll(".hb-phone-categories-bar")
                    .forEach((bar) => {
                        const activeId =
                            bar.querySelector(".hb-phone-cat-item.active")
                                ?.dataset.catId || "all";
                        let html = `
                        <div class="hb-phone-cat-item ${activeId === "all" ? "active" : ""}" data-role="cat-pill" data-cat-id="all"
                            data-cat-icon="${allIcon}" data-cat-active-icon="${allActiveIcon}">
                            <div class="hb-phone-cat-icon">
                                ${allActiveIcon ? `<img src="${allActiveIcon}" alt="${allTitle}">` : allIcon ? `<img src="${allIcon}" alt="${allTitle}">` : `<i class="ti ti-apps"></i>`}
                            </div>
                            <span>${allTitle.length > 11 ? allTitle.substring(0, 11) + "..." : allTitle}</span>
                        </div>
                    `;
                        categories.slice(0, 10).forEach((cat) => {
                            const isActive =
                                String(cat.id) === String(activeId);
                            const icon = cat.icon || "";
                            const activeIcon = cat.active_icon || icon;
                            html += `
                            <div class="hb-phone-cat-item ${isActive ? "active" : ""}" data-role="cat-pill"
                                data-cat-id="${cat.id}" data-cat-title="${cat.title}"
                                data-cat-icon="${icon}" data-cat-active-icon="${activeIcon}"
                                data-cat-bg-type="${cat.background_type || ""}"
                                data-cat-bg-color="${cat.background_color || ""}"
                                data-cat-bg-color-web="${cat.background_color_web || ""}"
                                data-cat-bg-color-app="${cat.background_color_app || ""}"
                                data-cat-bg-image="${cat.background_image || ""}"
                                data-cat-bg-image-web="${cat.background_image_web || ""}"
                                data-cat-bg-image-app="${cat.background_image_app || ""}"
                                data-cat-bg-anim="${cat.background_animation || ""}"
                                data-cat-bg-anim-web="${cat.background_animation_web || ""}"
                                data-cat-bg-anim-app="${cat.background_animation_app || ""}"
                                data-cat-font-color="${cat.font_color || ""}">
                                <div class="hb-phone-cat-icon">
                                    ${icon ? `<img src="${icon}" alt="${cat.title}">` : `<i class="ti ti-category"></i>`}
                                </div>
                                <span>${cat.title.length > 11 ? cat.title.substring(0, 11) + "..." : cat.title}</span>
                            </div>
                        `;
                        });
                        bar.innerHTML = html;
                    });

                // Update Web Categories Bar
                document
                    .querySelectorAll(".hb-web-categories-bar")
                    .forEach((bar) => {
                        const activeId =
                            bar.querySelector(".hb-web-cat-pill.active")
                                ?.dataset.catId || "all";
                        let html = `
                        <div class="hb-web-cat-pill ${activeId === "all" ? "active" : ""}" data-role="cat-pill" data-cat-id="all"
                            data-cat-icon="${allIcon}" data-cat-active-icon="${allActiveIcon}">
                            <div class="hb-web-cat-pill-icon">
                                ${allActiveIcon ? `<img src="${allActiveIcon}" alt="${allTitle}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">` : allIcon ? `<img src="${allIcon}" alt="${allTitle}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">` : `<i class="ti ti-apps"></i>`}
                            </div>
                            <span>${allTitle.length > 12 ? allTitle.substring(0, 12) + "..." : allTitle}</span>
                        </div>
                    `;
                        categories.slice(0, 10).forEach((cat) => {
                            const isActive =
                                String(cat.id) === String(activeId);
                            const icon = cat.icon || "";
                            const activeIcon = cat.active_icon || icon;
                            html += `
                            <div class="hb-web-cat-pill ${isActive ? "active" : ""}" data-role="cat-pill" data-cat-id="${cat.id}"
                                data-cat-title="${cat.title}"
                                data-cat-icon="${icon}" data-cat-active-icon="${activeIcon}"
                                data-cat-bg-type="${cat.background_type || ""}"
                                data-cat-bg-color="${cat.background_color || ""}"
                                data-cat-bg-color-web="${cat.background_color_web || ""}"
                                data-cat-bg-color-app="${cat.background_color_app || ""}"
                                data-cat-bg-image="${cat.background_image || ""}"
                                data-cat-bg-image-web="${cat.background_image_web || ""}"
                                data-cat-bg-image-app="${cat.background_image_app || ""}"
                                data-cat-bg-anim="${cat.background_animation || ""}"
                                data-cat-bg-anim-web="${cat.background_animation_web || ""}"
                                data-cat-bg-anim-app="${cat.background_animation_app || ""}"
                                data-cat-font-color="${cat.font_color || ""}">
                                <div class="hb-web-cat-pill-icon">
                                    ${icon ? `<img src="${icon}" alt="${cat.title}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">` : `<i class="ti ti-category"></i>`}
                                </div>
                                <span>${cat.title.length > 12 ? cat.title.substring(0, 12) + "..." : cat.title}</span>
                            </div>
                        `;
                        });
                        bar.innerHTML = html;
                    });

                // Update header background styles for both phone and web preview drawers
                document
                    .querySelectorAll(".hb-phone-header")
                    .forEach((header) => {
                        const activePill = header.querySelector(
                            ".hb-phone-cat-item.active",
                        );
                        const catId = activePill?.dataset.catId;
                        const catObj = categories.find(
                            (c) => String(c.id) === String(catId),
                        );
                        applyPreviewHeaderStyle(
                            header,
                            "app",
                            catObj,
                            globalSettings,
                        );
                    });

                document
                    .querySelectorAll(".hb-web-header")
                    .forEach((header) => {
                        const activePill = header.querySelector(
                            ".hb-web-cat-pill.active",
                        );
                        const catId = activePill?.dataset.catId;
                        const catObj = categories.find(
                            (c) => String(c.id) === String(catId),
                        );
                        applyPreviewHeaderStyle(
                            header,
                            "web",
                            catObj,
                            globalSettings,
                        );
                    });

                // Store active settings and categories on window for live interactions
                window._hbGlobalSettings = globalSettings;
                window._hbCategories = categories;
            })
            .catch((err) => {
                console.warn("Failed to refresh home builder previews:", err);
            });
    }
    window.refreshHomeBuilderPreviews = refreshHomeBuilderPreviews;
    window.applyPreviewHeaderStyle = applyPreviewHeaderStyle;

    /* ---------- tab switcher (Zones table / Navbar & Header / App layout / Web layout) ---------- */
    const tabs = document.getElementById("hb-tabs");
    if (tabs) {
        const panes = ["zones", "navbar-header", "app", "web"];
        tabs.querySelectorAll("[data-pane]").forEach((a) =>
            a.addEventListener("click", (e) => {
                e.preventDefault();
                tabs.querySelectorAll(".nav-link").forEach((x) =>
                    x.classList.remove("active"),
                );
                a.classList.add("active");
                panes.forEach((p) => {
                    const el = document.getElementById("hb-pane-" + p);
                    if (el) el.classList.toggle("d-none", a.dataset.pane !== p);
                });

                if (a.dataset.pane === "app" || a.dataset.pane === "web") {
                    refreshHomeBuilderPreviews();
                }
            }),
        );
    }

    /* ---------- Legacy Migration Handler ---------- */
    const legacyMigrationCard = document.getElementById(
        "hb-legacy-migration-card",
    );
    if (legacyMigrationCard) {
        const statusUrl = legacyMigrationCard.dataset.statusUrl;
        const runUrl = legacyMigrationCard.dataset.runUrl;
        const homeBannerStatusUrl =
            legacyMigrationCard.dataset.homeBannerStatusUrl;
        const homeBannerRunUrl = legacyMigrationCard.dataset.homeBannerRunUrl;
        const legacyRow = legacyMigrationCard.querySelector(
            '[data-role="legacy-migration-row"]',
        );
        const summaryEl = legacyMigrationCard.querySelector(
            '[data-role="legacy-migration-summary"]',
        );
        const runBtn = legacyMigrationCard.querySelector(
            '[data-role="run-legacy-migration"]',
        );
        const homeBannerRow = legacyMigrationCard.querySelector(
            '[data-role="home-banner-migration-row"]',
        );
        const homeBannerSummaryEl = legacyMigrationCard.querySelector(
            '[data-role="home-banner-migration-summary"]',
        );
        const homeBannerRunBtn = legacyMigrationCard.querySelector(
            '[data-role="run-home-banner-migration"]',
        );

        function legacyStatsText(stats) {
            const featured = stats?.featured_created || 0;
            const bannerSections = stats?.banner_sections_created || 0;
            const bannerItems = stats?.banner_items_created || 0;

            return `${featured} featured sections, ${bannerSections} banner sections, and ${bannerItems} banner items are ready to migrate.`;
        }

        function homeBannerStatsText(stats) {
            const converted = stats?.banner_sections_converted || 0;

            return `${converted} existing Home Builder banner sections are ready to convert to Play Image.`;
        }

        if (statusUrl) {
            api("get", statusUrl)
                .then((response) => {
                    const data = response.data?.data || {};
                    if (!data.has_pending && !homeBannerRow) return;

                    if (data.has_pending && summaryEl) {
                        summaryEl.textContent = legacyStatsText(data.stats);
                    }
                    if (data.has_pending) {
                        legacyRow?.classList.remove("d-none");
                        legacyMigrationCard.classList.remove("d-none");
                    }
                })
                .catch(() => {
                    /* keep the optional migration prompt hidden */
                });
        }

        if (homeBannerStatusUrl && homeBannerRow) {
            api("get", homeBannerStatusUrl)
                .then((response) => {
                    const data = response.data?.data || {};
                    if (!data.has_pending) return;

                    if (homeBannerSummaryEl) {
                        homeBannerSummaryEl.textContent = homeBannerStatsText(
                            data.stats,
                        );
                    }
                    homeBannerRow.classList.remove("d-none");
                    legacyMigrationCard.classList.remove("d-none");
                })
                .catch(() => {
                    /* keep the optional Home Builder banner prompt hidden */
                });
        }

        if (runBtn && runUrl) {
            runBtn.addEventListener("click", (e) => {
                e.preventDefault();
                ConfirmModal.open(
                    "Migrate legacy featured sections and banners into Home Builder? Existing migrated content will be skipped.",
                    () => {
                        runBtn.disabled = true;
                        api("post", runUrl)
                            .then((response) => {
                                const stats = response.data?.data || {};
                                if (window.Toast) {
                                    Toast.fire({
                                        icon: "success",
                                        title: legacyStatsText(stats),
                                    });
                                }
                                setTimeout(() => window.location.reload(), 800);
                            })
                            .catch((err) => {
                                runBtn.disabled = false;
                                if (window.Toast) {
                                    Toast.fire({
                                        icon: "error",
                                        title:
                                            err.response?.data?.message ||
                                            "Failed to migrate legacy content",
                                    });
                                }
                            });
                    },
                );
            });
        }

        if (homeBannerRunBtn && homeBannerRunUrl) {
            homeBannerRunBtn.addEventListener("click", (e) => {
                e.preventDefault();
                ConfirmModal.open(
                    "Convert only existing Home Builder banner sections to Play Image? Featured sections and legacy banner table data will not be migrated.",
                    () => {
                        homeBannerRunBtn.disabled = true;
                        api("post", homeBannerRunUrl)
                            .then((response) => {
                                const stats = response.data?.data || {};
                                if (window.Toast) {
                                    Toast.fire({
                                        icon: "success",
                                        title: homeBannerStatsText(stats),
                                    });
                                }
                                setTimeout(() => window.location.reload(), 800);
                            })
                            .catch((err) => {
                                homeBannerRunBtn.disabled = false;
                                if (window.Toast) {
                                    Toast.fire({
                                        icon: "error",
                                        title:
                                            err.response?.data?.message ||
                                            "Failed to convert Home Builder banners",
                                    });
                                }
                            });
                    },
                );
            });
        }
    }

    /* ---------- Zone Table Actions Handler ---------- */
    const zonesTableCard = document.getElementById("hb-zones-table-card");
    if (zonesTableCard) {
        zonesTableCard.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-action]");
            if (!btn) return;

            const action = btn.dataset.action;
            const zoneId = +btn.dataset.zoneId;
            const zoneName = btn.dataset.zoneName;

            if (action === "edit-zone-app" || action === "edit-zone-web") {
                e.preventDefault();
                const platform = action === "edit-zone-app" ? "app" : "web";

                // Switch tab to builder pane
                const tabLink = tabs.querySelector(`[data-pane="${platform}"]`);
                if (tabLink) tabLink.click();

                // Select Zone scope radio & set zone dropdown
                const rootEl = document.getElementById(
                    `home-builder-${platform}`,
                );
                if (rootEl) {
                    const zoneRadio = rootEl.querySelector(
                        '[data-role="scope"] input[value="zone"]',
                    );
                    if (zoneRadio) {
                        zoneRadio.checked = true;
                        zoneRadio.dispatchEvent(new Event("change"));
                    }
                    const scopeZoneSelect = rootEl.querySelector(
                        '[data-role="scope-zone"]',
                    );
                    if (scopeZoneSelect && scopeZoneSelect.tomselect) {
                        scopeZoneSelect.tomselect.addOption({
                            id: zoneId,
                            title: zoneName || "Zone #" + zoneId,
                        });
                        scopeZoneSelect.tomselect.setValue(String(zoneId));
                    }
                }
            } else if (
                action === "copy-global-app" ||
                action === "copy-global-web"
            ) {
                e.preventDefault();
                const platform = action === "copy-global-app" ? "app" : "web";
                ConfirmModal.open(
                    `Copy Global ${platform.toUpperCase()} Layout to Zone #${zoneId}? Existing layout sections will be overwritten.`,
                    () => {
                        api("post", "/admin/home-builder/copy-global", {
                            zone_id: zoneId,
                            platform: platform,
                        })
                            .then(() => {
                                if (window.Toast)
                                    Toast.fire({
                                        icon: "success",
                                        title: `Global layout copied to zone successfully!`,
                                    });
                                setTimeout(() => window.location.reload(), 600);
                            })
                            .catch((err) => {
                                if (window.Toast)
                                    Toast.fire({
                                        icon: "error",
                                        title:
                                            err.response?.data?.message ||
                                            "Failed to copy layout",
                                    });
                            });
                    },
                );
            } else if (action === "reset-zone") {
                e.preventDefault();
                ConfirmModal.open(
                    `Reset layout for ${zoneName || "Zone #" + zoneId}? It will revert to the System Global Layout.`,
                    () => {
                        api("post", "/admin/home-builder/reset-zone", {
                            zone_id: zoneId,
                            platform: "app",
                        })
                            .then(() => {
                                if (window.Toast)
                                    Toast.fire({
                                        icon: "success",
                                        title: `Zone layout reset successfully`,
                                    });
                                setTimeout(() => window.location.reload(), 600);
                            })
                            .catch((err) => {
                                if (window.Toast)
                                    Toast.fire({
                                        icon: "error",
                                        title: "Failed to reset layout",
                                    });
                            });
                    },
                );
            }
        });
    }

    const refreshZonesBtn = document.getElementById("refresh-zones-table");
    if (refreshZonesBtn) {
        refreshZonesBtn.addEventListener("click", () => {
            if (window.jQuery && window.jQuery("#zones-layout-table").length) {
                window
                    .jQuery("#zones-layout-table")
                    .DataTable()
                    .ajax.reload(null, false);
            }
        });
    }

    /* ---------- Global Fullscreen Mode Handler ---------- */
    const hbContainerEl = document.getElementById("home-builder-container");
    function updateGlobalFullscreenUI(isFullscreen) {
        if (hbContainerEl) {
            hbContainerEl.classList.toggle("hb-is-fullscreen", isFullscreen);
        }
        document
            .querySelectorAll('[data-role="fs-icon-enter"]')
            .forEach((el) => {
                el.classList.toggle("d-none", isFullscreen);
            });
        document
            .querySelectorAll('[data-role="fs-icon-exit"]')
            .forEach((el) => {
                el.classList.toggle("d-none", !isFullscreen);
            });
        document
            .querySelectorAll('[data-role="toggle-fullscreen"]')
            .forEach((btn) => {
                btn.classList.toggle("active", isFullscreen);
            });
    }

    document.addEventListener("click", (e) => {
        const btn = e.target.closest('[data-role="toggle-fullscreen"]');
        if (!btn) return;
        e.preventDefault();

        const targetEl = hbContainerEl || document.documentElement;
        const isCurrentlyFs =
            Boolean(document.fullscreenElement) ||
            (hbContainerEl &&
                hbContainerEl.classList.contains("hb-is-fullscreen"));

        if (!isCurrentlyFs) {
            if (targetEl.requestFullscreen) {
                targetEl.requestFullscreen().catch(() => {
                    updateGlobalFullscreenUI(true);
                });
            } else {
                updateGlobalFullscreenUI(true);
            }
        } else {
            if (document.exitFullscreen && document.fullscreenElement) {
                document.exitFullscreen().catch(() => {
                    updateGlobalFullscreenUI(false);
                });
            } else {
                updateGlobalFullscreenUI(false);
            }
        }
    });

    document.addEventListener("fullscreenchange", () => {
        const isFs = Boolean(document.fullscreenElement);
        updateGlobalFullscreenUI(isFs);
    });

    /* ---------- Global Templates Modal Open Handler ---------- */
    document.addEventListener("click", (e) => {
        const btn = e.target.closest('[data-role="open-templates"]');
        if (!btn) return;
        e.preventDefault();

        const rootEl =
            btn.closest(".hb-root") ||
            document.querySelector(".hb-root:not(.d-none)") ||
            document.getElementById("home-builder-app");
        if (rootEl && rootEl._homeBuilderInstance) {
            window.activeHomeBuilder = rootEl._homeBuilderInstance;
        }

        const searchInput = document.getElementById("hb-template-search");
        const clearSearchBtn = document.getElementById(
            "hb-template-search-clear",
        );
        if (searchInput) searchInput.value = "";
        if (clearSearchBtn) clearSearchBtn.classList.add("d-none");

        const cur = window.activeHomeBuilder;
        if (cur && cur.renderTemplateCards) {
            cur.renderTemplateCards("all", "");
        }
        const pills = document.querySelectorAll("#hb-template-tabs .nav-link");
        pills.forEach((p) =>
            p.classList.toggle("active", p.dataset.cat === "all"),
        );
        showModal("#hbTemplatesModal");

        if (cur && cur.fetchSavedPresets) {
            cur.fetchSavedPresets(() => {
                if (cur && cur.renderTemplateCards) {
                    cur.renderTemplateCards();
                }
            });
        }
    });

    /* ---------- boot one builder per root ---------- */
    roots.forEach(initHomeBuilder);
})();
