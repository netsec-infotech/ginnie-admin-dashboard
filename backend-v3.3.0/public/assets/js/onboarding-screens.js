(function () {
    const labels = window.onboardingScreenLabels || {};
    const fallback = window.onboardingPreviewFallback || {};
    const designDefaults = {
        background: { type: "solid", color: "#ffffff", gradient_start_color: "#0d6efd", gradient_end_color: "#20c997", gradient_angle: 180, image: "", image_fit: "cover", image_position: "center" },
        overlay: { enabled: false, type: "color", color: "#000000", opacity: 0, image: "", image_fit: "cover", image_position: "center", image_size: 100 },
        text: { title_color: "#111827", subtitle_color: "#6b7280", alignment: "center" },
        media: { fit: "contain" },
        button: { background_color: "#0d6efd", foreground_color: "#ffffff" },
        indicator: { active_color: "#0d6efd", inactive_color: "#dbe2ea" },
    };
    const presets = {
        clean_white: designDefaults,
        brand_gradient: { background: { type: "gradient", color: "#ffffff", gradient_start_color: "#0d6efd", gradient_end_color: "#20c997", gradient_angle: 145, image: "", image_fit: "cover", image_position: "center" }, overlay: { enabled: false, type: "color", color: "#000000", opacity: 0, image: "", image_fit: "cover", image_position: "center", image_size: 100 }, text: { title_color: "#071437", subtitle_color: "#3b4b66", alignment: "center" }, media: { fit: "contain" }, button: { background_color: "#0d6efd", foreground_color: "#ffffff" }, indicator: { active_color: "#0d6efd", inactive_color: "#dbe2ea" } },
        full_image: { background: { type: "image", color: "#ffffff", gradient_start_color: "#0d6efd", gradient_end_color: "#20c997", gradient_angle: 180, image: "", image_fit: "cover", image_position: "center" }, overlay: { enabled: true, type: "color", color: "#000000", opacity: 0.2, image: "", image_fit: "cover", image_position: "center", image_size: 100 }, text: { title_color: "#ffffff", subtitle_color: "#f1f5f9", alignment: "center" }, media: { fit: "cover" }, button: { background_color: "#ffffff", foreground_color: "#0d6efd" }, indicator: { active_color: "#ffffff", inactive_color: "#dbeafe" } },
        dark_overlay: { background: { type: "gradient", color: "#111827", gradient_start_color: "#111827", gradient_end_color: "#334155", gradient_angle: 180, image: "", image_fit: "cover", image_position: "center" }, overlay: { enabled: true, type: "color", color: "#000000", opacity: 0.35, image: "", image_fit: "cover", image_position: "center", image_size: 100 }, text: { title_color: "#ffffff", subtitle_color: "#dbeafe", alignment: "center" }, media: { fit: "contain" }, button: { background_color: "#0d6efd", foreground_color: "#ffffff" }, indicator: { active_color: "#60a5fa", inactive_color: "#dbeafe" } },
        minimal_text: { background: { type: "solid", color: "#f8fafc", gradient_start_color: "#0d6efd", gradient_end_color: "#20c997", gradient_angle: 180, image: "", image_fit: "contain", image_position: "center" }, overlay: { enabled: false, type: "color", color: "#000000", opacity: 0, image: "", image_fit: "cover", image_position: "center", image_size: 100 }, text: { title_color: "#111827", subtitle_color: "#64748b", alignment: "left" }, media: { fit: "contain" }, button: { background_color: "#111827", foreground_color: "#ffffff" }, indicator: { active_color: "#111827", inactive_color: "#dbe2ea" } },
    };
    let selectedApp = "customer";
    let currentPreviewImage = "";
    let currentPreviewMediaType = "image";
    let currentPreviewObjectUrl = "";
    let currentBackgroundImage = "";
    let currentBackgroundObjectUrl = "";
    let currentOverlayImage = "";
    let currentOverlayObjectUrl = "";
    let previewScreens = [];
    let previewIndex = 0;
    let previewMode = "app";
    let filePondSession = 0;
    const builderRows = new Map();
    let previewDevice = localStorage.getItem("onboarding_preview_device") || "ios";

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    }

    function currentTable() {
        const table = document.getElementById("onboarding-screens-table");

        return table && $.fn.DataTable.isDataTable(table) ? $(table).DataTable() : null;
    }

    function bootstrapModal(id) {
        const element = document.getElementById(id);

        if (!element) {
            return null;
        }

        if (window.bootstrap?.Modal) {
            return window.bootstrap.Modal.getOrCreateInstance(element);
        }

        if (window.jQuery?.fn?.modal) {
            return {
                show: () => window.jQuery(element).modal("show"),
                hide: () => window.jQuery(element).modal("hide"),
            };
        }

        return null;
    }

    function formElement() {
        return document.querySelector("[data-onboarding-form]");
    }

    function editorCard() {
        return document.querySelector("[data-onboarding-editor-card]");
    }

    function showInlineEditor(anchor = null) {
        const card = editorCard();
        const target = anchor?.closest?.("[data-onboarding-builder-card], [data-onboarding-create]");
        if (card && target) {
            target.insertAdjacentElement("afterend", card);
        }

        card?.classList.remove("d-none");
        formElement()?.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    function hideInlineEditor() {
        editorCard()?.classList.add("d-none");
    }

    function parkInlineEditor() {
        const card = editorCard();
        const tableCard = document.querySelector(".onboarding-table-card");

        if (!card || !tableCard) {
            return;
        }

        card.classList.add("d-none");
        tableCard.insertAdjacentElement("beforebegin", card);
    }

    function mountInlineEditor() {
        const form = formElement();
        const target = document.querySelector("[data-onboarding-inline-editor]");

        if (!form || !target || target.contains(form)) {
            return;
        }

        form.classList.remove("modal-content");
        form.classList.add("onboarding-inline-form");
        target.appendChild(form);
        document.getElementById("onboarding-screen-form-modal")?.classList.add("d-none");
    }

    function updateCharacterCounter(field) {
        const max = Number(field.dataset.onboardingCharacterMax || field.getAttribute("maxlength") || 0);
        if (!max) {
            return;
        }

        const wrapper = field.closest(".mb-3");
        const counter = wrapper?.querySelector("[data-onboarding-character-counter]");
        const message = wrapper?.querySelector("[data-onboarding-character-message]");
        const length = field.value.length;
        const isAtLimit = length >= max;

        if (counter) {
            counter.textContent = `${length}/${max}`;
            counter.classList.toggle("text-danger", isAtLimit);
            counter.classList.toggle("text-secondary", !isAtLimit);
        }

        if (message) {
            message.textContent = isAtLimit ? (labels.characterLimitMessage || "Maximum :count characters allowed.").replace(":count", max) : "";
            message.classList.toggle("d-none", !isAtLimit);
        }
    }

    function updateCharacterCounters(scope = document) {
        scope.querySelectorAll("[data-onboarding-character-field]").forEach(updateCharacterCounter);
    }

    function bindCharacterCounters() {
        document.querySelectorAll("[data-onboarding-character-field]").forEach((field) => {
            ["input", "change"].forEach((eventName) => {
                field.addEventListener(eventName, () => updateCharacterCounter(field));
            });
            updateCharacterCounter(field);
        });
    }

    function fileInput() {
        return document.querySelector('[data-onboarding-form-field="image"]');
    }

    function backgroundFileInput() {
        return document.querySelector('[data-onboarding-design-field="background_image"]');
    }

    function overlayFileInput() {
        return document.querySelector('[data-onboarding-design-field="overlay_image"]');
    }

    function nextFilePondSession() {
        filePondSession += 1;

        return filePondSession;
    }

    function mimeTypeFromSource(source) {
        const text = String(source?.name || source || "").split("?")[0].toLowerCase();
        const extension = text.split(".").pop();
        const types = { gif: "image/gif", jpeg: "image/jpeg", jpg: "image/jpeg", json: "application/json", mov: "video/quicktime", mp4: "video/mp4", png: "image/png", svg: "image/svg+xml", txt: "text/plain", webm: "video/webm", webp: "image/webp" };

        return types[extension] || "";
    }

    function loadRemoteFile(source, load, error, progress, abort) {
        const controller = new AbortController();

        fetch(source, { credentials: "same-origin", signal: controller.signal })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Unable to load media.");
                }

                const contentLength = response.headers.get("content-length");
                if (contentLength && typeof progress === "function") {
                    progress(true, Number(contentLength), Number(contentLength));
                }

                return response.blob();
            })
            .then(load)
            .catch(() => error("Unable to load media."));

        return {
            abort: () => {
                controller.abort();
                if (typeof abort === "function") {
                    abort();
                }
            },
        };
    }

    function applySelectedUploadToPreview(input, file) {
        if (!input || !(file instanceof Blob)) {
            return;
        }

        if (input.matches('[data-onboarding-form-field="image"]')) {
            if (currentPreviewObjectUrl) {
                URL.revokeObjectURL(currentPreviewObjectUrl);
            }
            currentPreviewObjectUrl = URL.createObjectURL(file);
            currentPreviewImage = currentPreviewObjectUrl;
            currentPreviewMediaType = mediaTypeFromMime(file.type || file.name);
            updatePreview(screenFromForm());
            return;
        }

        if (input.matches('[data-onboarding-design-field="background_image"]')) {
            if (currentBackgroundObjectUrl) {
                URL.revokeObjectURL(currentBackgroundObjectUrl);
            }
            currentBackgroundObjectUrl = URL.createObjectURL(file);
            currentBackgroundImage = currentBackgroundObjectUrl;
            setFieldValue("background_type", "image");
            toggleDesignFieldVisibility();
            updatePreview(screenFromForm());
            return;
        }

        if (input.matches('[data-onboarding-design-field="overlay_image"]')) {
            if (currentOverlayObjectUrl) {
                URL.revokeObjectURL(currentOverlayObjectUrl);
            }
            currentOverlayObjectUrl = URL.createObjectURL(file);
            currentOverlayImage = currentOverlayObjectUrl;
            setFieldValue("overlay_enabled", true);
            setFieldValue("overlay_type", "image");
            if (Number(formElement()?.querySelector('[data-onboarding-design-field="overlay_opacity"]')?.value || 0) === 0) {
                setFieldValue("overlay_opacity", 1);
            }
            toggleDesignFieldVisibility();
            updatePreview(screenFromForm());
        }
    }

    function filePondOptions(input) {
        const acceptedFileTypes = input.getAttribute("data-accepted-file-types")
            ? input.getAttribute("data-accepted-file-types").split(",").map((type) => type.trim()).filter(Boolean)
            : ["image/*"];
        const imageUrl = input.getAttribute("data-image-url") || "";
        const existingMimeType = input.getAttribute("data-media-mime-type") || "";

        return {
            allowImagePreview: true,
            credits: false,
            storeAsFile: true,
            maxFileSize: input.getAttribute("data-max-file-size") || null,
            acceptedFileTypes,
            fileValidateTypeDetectType: (source, type) => new Promise((resolve) => {
                resolve(existingMimeType || type || mimeTypeFromSource(source));
            }),
            files: imageUrl ? [{ source: imageUrl, options: { type: "remote" } }] : [],
            onaddfile: (error, fileItem) => {
                if (!error && !imageUrl) {
                    applySelectedUploadToPreview(input, fileItem?.file);
                }
            },
            server: { fetch: loadRemoteFile, load: loadRemoteFile },
        };
    }

    function findPond(input, key) {
        if (typeof FilePond === "undefined" || !input) {
            return null;
        }

        return FilePond.find(input) || input._pond || window[key] || null;
    }

    function eventField(event) {
        const target = event.target;

        if (target?.matches?.("[data-onboarding-form-field], [data-onboarding-design-field]")) {
            return target;
        }

        return target?.querySelector?.("[data-onboarding-form-field], [data-onboarding-design-field]")
            || target?.closest?.(".filepond--root")?.querySelector?.("[data-onboarding-form-field], [data-onboarding-design-field]")
            || null;
    }

    function nativeFileFromPondEvent(event) {
        const file = event.detail?.file;

        return file?.file instanceof File ? file.file : file instanceof File ? file : null;
    }

    function createFileInput(imageUrl = "", mediaMimeType = "") {
        const input = document.createElement("input");
        input.type = "file";
        input.className = "filepond";
        input.name = "image";
        input.accept = "image/*,.json,application/json,video/mp4,video/webm,video/quicktime";
        input.dataset.maxFiles = "1";
        input.dataset.maxFileSize = "10MB";
        input.dataset.acceptedFileTypes = "image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/json,text/plain,application/octet-stream,video/mp4,video/webm,video/quicktime,.json";
        input.dataset.onboardingFormField = "image";

        if (imageUrl) {
            input.dataset.imageUrl = imageUrl;
        }
        if (mediaMimeType) {
            input.dataset.mediaMimeType = mediaMimeType;
        }

        return input;
    }

    function createBackgroundFileInput(imageUrl = "") {
        const input = document.createElement("input");
        input.type = "file";
        input.className = "filepond";
        input.name = "background_image";
        input.accept = "image/*";
        input.dataset.maxFiles = "1";
        input.dataset.maxFileSize = "10MB";
        input.dataset.acceptedFileTypes = "image/jpeg,image/png,image/webp,image/gif,image/svg+xml";
        input.dataset.onboardingDesignField = "background_image";

        if (imageUrl) {
            input.dataset.imageUrl = imageUrl;
            input.dataset.mediaMimeType = mimeTypeFromSource(imageUrl);
        }

        return input;
    }

    function createOverlayFileInput(imageUrl = "") {
        const input = createBackgroundFileInput(imageUrl);
        input.name = "overlay_image";
        input.dataset.onboardingDesignField = "overlay_image";

        return input;
    }

    function replacePondInput(slotSelector, currentInput, cleanInput, windowKey) {
        const slot = document.querySelector(slotSelector);
        if (!slot) {
            return null;
        }

        const pond = findPond(currentInput, windowKey);
        if (pond) {
            pond.removeFiles?.({ revert: false });
            pond.destroy?.();
        }

        slot.innerHTML = "";
        slot.appendChild(cleanInput);
        window[windowKey] = null;

        if (typeof FilePond !== "undefined") {
            const nextPond = FilePond.create(cleanInput, filePondOptions(cleanInput));
            cleanInput._pond = nextPond;
            window[windowKey] = nextPond;
        }

        return cleanInput;
    }

    function prepareFileInput(imageUrl = "", mediaMimeType = "") {
        return replacePondInput("[data-onboarding-filepond-slot]", fileInput(), createFileInput(imageUrl, mediaMimeType), "onboardingScreenImagePond");
    }

    function prepareBackgroundFileInput(imageUrl = "") {
        return replacePondInput("[data-onboarding-background-filepond-slot]", backgroundFileInput(), createBackgroundFileInput(imageUrl), "onboardingScreenBackgroundPond");
    }

    function prepareOverlayFileInput(imageUrl = "") {
        return replacePondInput("[data-onboarding-overlay-filepond-slot]", overlayFileInput(), createOverlayFileInput(imageUrl), "onboardingScreenOverlayPond");
    }

    function revokePreviewObjectUrl() {
        if (currentPreviewObjectUrl) {
            URL.revokeObjectURL(currentPreviewObjectUrl);
            currentPreviewObjectUrl = "";
        }
        if (currentBackgroundObjectUrl) {
            URL.revokeObjectURL(currentBackgroundObjectUrl);
            currentBackgroundObjectUrl = "";
        }
        if (currentOverlayObjectUrl) {
            URL.revokeObjectURL(currentOverlayObjectUrl);
            currentOverlayObjectUrl = "";
        }
    }

    function resetFormMediaState(session = nextFilePondSession()) {
        revokePreviewObjectUrl();
        currentPreviewImage = "";
        currentPreviewMediaType = "image";
        currentBackgroundImage = "";
        currentOverlayImage = "";
        if (session === filePondSession) {
            prepareFileInput();
            prepareBackgroundFileInput();
            prepareOverlayFileInput();
        }
    }

    function loadEditMediaIntoFilePond(rowData, session) {
        if (session !== filePondSession || typeof FilePond === "undefined") {
            return;
        }

        const pond = findPond(fileInput(), "onboardingScreenImagePond");
        if (pond && rowData?.image_url) {
            pond.addFile(rowData.image_url).catch(() => {});
        }

        const bgPond = findPond(backgroundFileInput(), "onboardingScreenBackgroundPond");
        if (bgPond && rowData?.background_image_url) {
            bgPond.addFile(rowData.background_image_url).catch(() => {});
        }
        const overlayPond = findPond(overlayFileInput(), "onboardingScreenOverlayPond");
        if (overlayPond && rowData?.overlay_image_url) {
            overlayPond.addFile(rowData.overlay_image_url).catch(() => {});
        }
    }

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function normalizeDesign(design = {}) {
        return {
            background: { ...clone(designDefaults.background), ...(design.background || {}) },
            overlay: { ...clone(designDefaults.overlay), ...(design.overlay || {}) },
            text: { ...clone(designDefaults.text), ...(design.text || {}) },
            media: { ...clone(designDefaults.media), ...(design.media || {}) },
            button: { ...clone(designDefaults.button), ...(design.button || {}) },
            indicator: { ...clone(designDefaults.indicator), ...(design.indicator || {}) },
        };
    }

    function setFieldValue(name, value) {
        const field = formElement()?.querySelector(`[data-onboarding-design-field="${name}"]`);
        if (!field) {
            return;
        }

        if (field.type === "checkbox") {
            field.checked = Boolean(value);
            return;
        }

        field.value = value;
        updateRangeValue(field);
    }

    function updateRangeValue(field) {
        const key = field?.dataset?.onboardingRange;
        if (!key) {
            return;
        }

        const suffix = key === "overlay_image_size" ? "%" : "";
        document.querySelectorAll(`[data-onboarding-range-value="${key}"]`).forEach((element) => {
            element.textContent = `${field.value}${suffix}`;
        });
    }

    function toggleDesignFieldVisibility() {
        const backgroundType = formElement()?.querySelector('[data-onboarding-design-field="background_type"]')?.value || "solid";
        document.querySelectorAll("[data-onboarding-background-solid]").forEach((element) => element.classList.toggle("d-none", backgroundType !== "solid"));
        document.querySelectorAll("[data-onboarding-background-gradient]").forEach((element) => element.classList.toggle("d-none", backgroundType !== "gradient"));
        document.querySelectorAll("[data-onboarding-background-image-wrap], [data-onboarding-background-image-options]").forEach((element) => element.classList.toggle("d-none", backgroundType !== "image"));
        const overlayEnabled = formElement()?.querySelector('[data-onboarding-design-field="overlay_enabled"]')?.checked || false;
        const overlayType = formElement()?.querySelector('[data-onboarding-design-field="overlay_type"]')?.value || "color";
        document.querySelectorAll("[data-onboarding-overlay-fields]").forEach((element) => element.classList.toggle("d-none", !overlayEnabled));
        document.querySelectorAll("[data-onboarding-overlay-color-options]").forEach((element) => element.classList.toggle("d-none", !overlayEnabled || overlayType !== "color"));
        document.querySelectorAll("[data-onboarding-overlay-image-wrap], [data-onboarding-overlay-image-options]").forEach((element) => element.classList.toggle("d-none", !overlayEnabled || overlayType !== "image"));
    }

    function applyDesignToForm(design) {
        const normalized = normalizeDesign(design);
        setFieldValue("background_type", normalized.background.type);
        setFieldValue("background_color", normalized.background.color);
        setFieldValue("gradient_start_color", normalized.background.gradient_start_color);
        setFieldValue("gradient_end_color", normalized.background.gradient_end_color);
        setFieldValue("gradient_angle", normalized.background.gradient_angle);
        setFieldValue("background_image_fit", normalized.background.image_fit);
        setFieldValue("background_image_position", normalized.background.image_position);
        setFieldValue("overlay_enabled", normalized.overlay.enabled);
        setFieldValue("overlay_type", normalized.overlay.type);
        setFieldValue("overlay_color", normalized.overlay.color);
        setFieldValue("overlay_opacity", normalized.overlay.opacity);
        setFieldValue("overlay_image_fit", normalized.overlay.image_fit);
        setFieldValue("overlay_image_position", normalized.overlay.image_position);
        setFieldValue("overlay_image_size", normalized.overlay.image_size);
        setFieldValue("title_color", normalized.text.title_color);
        setFieldValue("subtitle_color", normalized.text.subtitle_color);
        setFieldValue("content_alignment", normalized.text.alignment);
        setFieldValue("button_background_color", normalized.button.background_color);
        setFieldValue("button_foreground_color", normalized.button.foreground_color);
        setFieldValue("indicator_active_color", normalized.indicator.active_color);
        setFieldValue("indicator_inactive_color", normalized.indicator.inactive_color);
        toggleDesignFieldVisibility();
    }

    function designFromForm() {
        const form = formElement();
        if (!form) {
            return normalizeDesign();
        }

        return normalizeDesign({
            background: {
                type: form.querySelector('[data-onboarding-design-field="background_type"]')?.value,
                color: form.querySelector('[data-onboarding-design-field="background_color"]')?.value,
                gradient_start_color: form.querySelector('[data-onboarding-design-field="gradient_start_color"]')?.value,
                gradient_end_color: form.querySelector('[data-onboarding-design-field="gradient_end_color"]')?.value,
                gradient_angle: Number(form.querySelector('[data-onboarding-design-field="gradient_angle"]')?.value || 180),
                image: currentBackgroundImage,
                image_fit: form.querySelector('[data-onboarding-design-field="background_image_fit"]')?.value,
                image_position: form.querySelector('[data-onboarding-design-field="background_image_position"]')?.value,
            },
            overlay: {
                enabled: form.querySelector('[data-onboarding-design-field="overlay_enabled"]')?.checked || false,
                type: form.querySelector('[data-onboarding-design-field="overlay_type"]')?.value,
                color: form.querySelector('[data-onboarding-design-field="overlay_color"]')?.value,
                opacity: Number(form.querySelector('[data-onboarding-design-field="overlay_opacity"]')?.value || 0),
                image: currentOverlayImage,
                image_fit: form.querySelector('[data-onboarding-design-field="overlay_image_fit"]')?.value,
                image_position: form.querySelector('[data-onboarding-design-field="overlay_image_position"]')?.value,
                image_size: Number(form.querySelector('[data-onboarding-design-field="overlay_image_size"]')?.value || 100),
            },
            text: {
                title_color: form.querySelector('[data-onboarding-design-field="title_color"]')?.value,
                subtitle_color: form.querySelector('[data-onboarding-design-field="subtitle_color"]')?.value,
                alignment: form.querySelector('[data-onboarding-design-field="content_alignment"]')?.value,
            },
            media: { fit: designDefaults.media.fit },
            button: {
                background_color: form.querySelector('[data-onboarding-design-field="button_background_color"]')?.value,
                foreground_color: form.querySelector('[data-onboarding-design-field="button_foreground_color"]')?.value,
            },
            indicator: {
                active_color: form.querySelector('[data-onboarding-design-field="indicator_active_color"]')?.value,
                inactive_color: form.querySelector('[data-onboarding-design-field="indicator_inactive_color"]')?.value,
            },
        });
    }

    function resetOnboardingForm() {
        const form = formElement();
        if (!form) {
            return;
        }

        form.reset();
        form.action = form.dataset.storeUrl;
        form.querySelector("[data-onboarding-form-app]").value = selectedApp;
        form.querySelector("[data-onboarding-form-title]").textContent = labels.createTitle || "Create Onboarding Screen";
        form.querySelector("[data-onboarding-form-submit]").textContent = labels.createButton || "Create";
        applyDesignToForm(designDefaults);
        updateCharacterCounters(form);
    }

    function setCreateButtonState(root, canCreate) {
        root.querySelectorAll("[data-onboarding-create]").forEach((createButton) => {
            createButton.disabled = !canCreate;
            createButton.title = canCreate ? "" : (labels.limitReached || "Screen limit reached.");
        });
    }

    function setActiveApp(root, appType, reloadTable = true) {
        selectedApp = appType;
        root.dataset.selectedApp = appType;
        const selectedButton = root.querySelector(`[data-onboarding-app-tab="${appType}"]`);
        const selectedLabel = selectedButton?.dataset.onboardingAppLabel || appType;

        root.querySelectorAll("[data-onboarding-app-tab]").forEach((button) => {
            button.classList.toggle("active", button.dataset.onboardingAppTab === appType);
        });

        root.querySelectorAll("[data-onboarding-toggle-wrap]").forEach((wrap) => {
            wrap.classList.toggle("d-none", wrap.dataset.onboardingToggleWrap !== appType);
        });

        const tableTitle = root.querySelector("[data-onboarding-table-title]");
        const tableSubtitle = root.querySelector("[data-onboarding-table-subtitle]");
        if (tableTitle) {
            tableTitle.textContent = `${selectedLabel} Onboarding Screens`;
        }
        if (tableSubtitle) {
            tableSubtitle.textContent = `Manage and organize onboarding screens for ${selectedLabel.toLowerCase()}.`;
        }

        const screenCount = root.querySelector("[data-onboarding-screen-count]");
        if (screenCount) {
            screenCount.textContent = "0";
        }
        const builderList = root.querySelector("[data-onboarding-builder-list]");
        if (builderList) {
            builderList.innerHTML = `<div class="onboarding-builder-empty"><i class="ti ti-loader-2"></i><span>Loading ${selectedLabel} screens...</span></div>`;
        }

        const appInput = document.querySelector("[data-onboarding-form-app]");
        if (appInput) {
            appInput.value = appType;
        }

        if (reloadTable) {
            currentTable()?.ajax.url(`${root.dataset.datatableUrl}?app_type=${encodeURIComponent(appType)}`).load();
        }
    }

    function screenFromForm() {
        const form = formElement();

        return {
            app_type: selectedApp,
            title: form?.querySelector('[data-onboarding-form-field="title"]')?.value?.trim() || "",
            subtitle: form?.querySelector('[data-onboarding-form-field="subtitle"]')?.value?.trim() || "",
            image: currentPreviewImage,
            media_type: currentPreviewMediaType,
            design: designFromForm(),
        };
    }

    function mediaTypeFromMime(mimeType) {
        if (mimeType?.startsWith("video/")) {
            return "video";
        }

        if (mimeType?.includes("json") || mimeType === "text/plain" || mimeType === "application/octet-stream") {
            return "lottie";
        }

        return "image";
    }

    function colorToRgba(color, alpha) {
        const normalized = String(color || "#000000").replace("#", "");
        const fullHex = normalized.length === 3
            ? normalized.split("").map((part) => part + part).join("")
            : normalized.padEnd(6, "0").slice(0, 6);
        const red = parseInt(fullHex.slice(0, 2), 16);
        const green = parseInt(fullHex.slice(2, 4), 16);
        const blue = parseInt(fullHex.slice(4, 6), 16);

        return `rgba(${red}, ${green}, ${blue}, ${Math.max(0, Math.min(Number(alpha) || 0, 1))})`;
    }

    function applyPreviewDesign(phone, design) {
        const normalized = normalizeDesign(design);
        const screen = phone.querySelector(".onboarding-device-screen");
        const overlay = phone.querySelector("[data-onboarding-preview-overlay]");
        const copy = phone.querySelector(".onboarding-preview-copy");
        const media = phone.querySelector(".onboarding-preview-media");
        const title = phone.querySelector("[data-onboarding-preview-title-text]");
        const subtitle = phone.querySelector("[data-onboarding-preview-subtitle-text]");
        const button = phone.querySelector("[data-onboarding-preview-next]");
        const skip = phone.querySelector(".onboarding-preview-skip");

        screen.style.backgroundColor = normalized.background.color;
        screen.style.backgroundImage = "";
        screen.style.backgroundSize = "";
        screen.style.backgroundPosition = "";
        screen.style.backgroundRepeat = "";

        if (normalized.background.type === "gradient") {
            screen.style.backgroundImage = `linear-gradient(${normalized.background.gradient_angle}deg, ${normalized.background.gradient_start_color}, ${normalized.background.gradient_end_color})`;
        }

        if (normalized.background.type === "image" && normalized.background.image) {
            screen.style.backgroundImage = `url("${normalized.background.image}")`;
            screen.style.backgroundSize = normalized.background.image_fit === "fill" ? "100% 100%" : normalized.background.image_fit;
            screen.style.backgroundPosition = normalized.background.image_position;
            screen.style.backgroundRepeat = "no-repeat";
        }

        if (overlay) {
            overlay.style.background = "";
            overlay.style.backgroundSize = "";
            overlay.style.backgroundPosition = "";
            overlay.style.backgroundRepeat = "";
            overlay.style.opacity = 0;

            if (normalized.overlay.enabled) {
                const overlayOpacity = Number(normalized.overlay.opacity) || 0;
                const colorOverlayOpacity = Math.min(overlayOpacity, 0.45);
                overlay.style.background = `linear-gradient(180deg, ${colorToRgba(normalized.overlay.color, colorOverlayOpacity * 0.18)} 0%, ${colorToRgba(normalized.overlay.color, colorOverlayOpacity * 0.34)} 48%, ${colorToRgba(normalized.overlay.color, colorOverlayOpacity)} 100%)`;
                if (normalized.overlay.type === "image" && normalized.overlay.image) {
                    const overlaySize = Math.max(20, Math.min(Number(normalized.overlay.image_size) || 100, 100));
                    overlay.style.background = `url("${normalized.overlay.image}")`;
                    overlay.style.backgroundSize = normalized.overlay.image_fit === "fill" ? `${overlaySize}% ${overlaySize}%` : `${overlaySize}% auto`;
                    overlay.style.backgroundPosition = normalized.overlay.image_position;
                    overlay.style.backgroundRepeat = "no-repeat";
                    overlay.style.opacity = Math.min(Math.max(overlayOpacity || 1, 0), 1);
                } else {
                    overlay.style.opacity = 1;
                }
            }
        }
        if (copy) {
            copy.style.textAlign = normalized.text.alignment;
        }
        if (title) {
            title.style.color = normalized.text.title_color;
        }
        if (subtitle) {
            subtitle.style.color = normalized.text.subtitle_color;
        }
        if (button) {
            button.style.backgroundColor = normalized.button.background_color;
            button.style.borderColor = normalized.button.background_color;
            button.style.color = normalized.button.foreground_color;
        }
        if (skip) {
            skip.style.color = normalized.text.subtitle_color;
        }
        media?.querySelectorAll("img, video, lottie-player").forEach((element) => {
            element.style.objectFit = normalized.media.fit;
        });
    }

    function updatePreview(screen, index = 0, total = 1) {
        const previewPhones = document.querySelectorAll(".onboarding-device-preview");
        const previewCount = document.querySelector("[data-onboarding-preview-count]");

        if (previewCount) {
            previewCount.textContent = `${Math.min(index + 1, total)} / ${Math.max(total, 1)}`;
        }

        previewPhones.forEach((phone) => {
            const titleText = phone.querySelector("[data-onboarding-preview-title-text]");
            const subtitleText = phone.querySelector("[data-onboarding-preview-subtitle-text]");
            const copy = phone.querySelector(".onboarding-preview-copy");
            const media = phone.querySelector(".onboarding-preview-media");
            const appLabel = phone.querySelector("[data-onboarding-preview-app-label]");
            const previewImg = phone.querySelector("[data-onboarding-preview-img]");
            const previewLottie = phone.querySelector("[data-onboarding-preview-lottie]");
            const previewVideo = phone.querySelector("[data-onboarding-preview-video]");
            const placeholder = phone.querySelector("[data-onboarding-preview-placeholder]");
            const dots = phone.querySelector("[data-onboarding-preview-dots]");

            if (!titleText || !subtitleText || !appLabel || !previewImg || !previewLottie || !previewVideo) {
                return;
            }

            titleText.textContent = screen.title || "";
            subtitleText.textContent = screen.subtitle || "";
            titleText.classList.toggle("d-none", !screen.title);
            subtitleText.classList.toggle("d-none", !screen.subtitle);
            copy?.classList.toggle("d-none", !screen.title && !screen.subtitle);
            appLabel.textContent = `${(screen.app_type || selectedApp).replace("_", " ")} app`;
            previewImg.classList.add("d-none");
            previewLottie.classList.add("d-none");
            previewVideo.classList.add("d-none");
            previewImg.removeAttribute("src");
            previewLottie.removeAttribute("src");
            previewVideo.removeAttribute("src");
            applyPreviewDesign(phone, screen.design);

            if (screen.image) {
                media?.classList.remove("d-none");
                if (screen.media_type === "lottie") {
                    if (typeof previewLottie.load === "function") {
                        previewLottie.load(screen.image);
                    } else {
                        previewLottie.setAttribute("src", screen.image);
                    }
                    previewLottie.classList.remove("d-none");
                } else if (screen.media_type === "video") {
                    previewVideo.src = screen.image;
                    previewVideo.classList.remove("d-none");
                    previewVideo.load();
                } else {
                    previewImg.src = screen.image;
                    previewImg.classList.remove("d-none");
                }
                placeholder?.classList.add("d-none");
            } else {
                media?.classList.add("d-none");
                placeholder?.classList.add("d-none");
            }

            if (dots) {
                dots.innerHTML = "";
                const normalizedDesign = normalizeDesign(screen.design);
                for (let dotIndex = 0; dotIndex < Math.max(total, 1); dotIndex += 1) {
                    const dot = document.createElement("span");
                    dot.classList.toggle("active", dotIndex === index);
                    dot.style.backgroundColor = dotIndex === index
                        ? normalizedDesign.indicator.active_color
                        : normalizedDesign.indicator.inactive_color;
                    dots.appendChild(dot);
                }
            }
        });
    }

    function openPreview(screen) {
        previewMode = "single";
        previewIndex = 0;
        updatePreview(screen);
        bootstrapModal("onboarding-screen-preview-modal")?.show();
    }

    function currentPreviewScreen() {
        return previewScreens[previewIndex] || { app_type: selectedApp, title: "", subtitle: "", image: "", media_type: "image", design: designDefaults };
    }

    function renderPreviewSequence() {
        updatePreview(currentPreviewScreen(), previewIndex, previewScreens.length || 1);
        document.querySelectorAll("[data-onboarding-builder-card]").forEach((card) => {
            card.classList.toggle("active", Number(card.dataset.onboardingBuilderIndex) === previewIndex);
        });
    }

    function applyPreviewDevice(device) {
        previewDevice = device === "android" ? "android" : "ios";
        localStorage.setItem("onboarding_preview_device", previewDevice);

        document.querySelectorAll(".onboarding-device-preview").forEach((phone) => {
            phone.dataset.device = previewDevice;
            phone.classList.remove("device-iphone-14-pro", "device-google-pixel-6-pro");
            phone.classList.add(previewDevice === "android" ? "device-google-pixel-6-pro" : "device-iphone-14-pro");
        });

        document.querySelectorAll("[data-onboarding-device]").forEach((button) => {
            button.classList.toggle("active", button.dataset.onboardingDevice === previewDevice);
        });
    }

    function actionAllowed(row, className) {
        return String(row.action || "").includes(className);
    }

    function renderBuilderList(root, json) {
        const list = root.querySelector("[data-onboarding-builder-list]");
        const count = root.querySelector("[data-onboarding-screen-count]");
        const rows = Array.isArray(json?.data) ? json.data : [];
        const maxScreens = Number(json?.max_screens || root.dataset.maxScreens || 5);

        builderRows.clear();
        rows.forEach((row) => builderRows.set(String(row.id), row));

        if (count) {
            count.textContent = String(rows.length);
        }

        if (!list) {
            return;
        }

        parkInlineEditor();
        list.innerHTML = "";

        if (rows.length === 0) {
            const empty = document.createElement("div");
            empty.className = "onboarding-builder-empty";
            empty.innerHTML = `<i class="ti ti-device-mobile-plus"></i><span>Add up to ${maxScreens} onboarding screens for this app.</span>`;
            list.appendChild(empty);
            return;
        }

        rows.forEach((row, index) => {
            const card = document.createElement("div");
            card.className = "onboarding-builder-screen";
            card.dataset.onboardingBuilderCard = String(row.id);
            card.dataset.onboardingBuilderIndex = String(index);

            const media = document.createElement("div");
            media.className = "onboarding-builder-screen-media";
            media.innerHTML = row.image || '<span class="text-secondary">-</span>';

            const body = document.createElement("button");
            body.type = "button";
            body.className = "onboarding-builder-screen-body";
            body.dataset.onboardingPreviewIndex = String(index);

            const eyebrow = document.createElement("span");
            eyebrow.className = "onboarding-builder-screen-eyebrow";
            eyebrow.textContent = `Screen ${index + 1}`;

            const title = document.createElement("strong");
            title.textContent = row.title_raw || "Untitled screen";

            const subtitle = document.createElement("small");
            subtitle.textContent = row.subtitle_raw || "No subtitle";

            body.append(eyebrow, title, subtitle);

            const actions = document.createElement("div");
            actions.className = "onboarding-builder-screen-actions";

            if (actionAllowed(row, "edit-onboarding-screen")) {
                const editButton = document.createElement("button");
                editButton.type = "button";
                editButton.className = "btn btn-outline-blue p-1 edit-onboarding-screen";
                editButton.dataset.onboardingRowId = String(row.id);
                editButton.title = labels.editTitle || "Edit Onboarding Screen";
                editButton.innerHTML = '<i class="ti ti-edit"></i>';
                actions.appendChild(editButton);
            }

            if (actionAllowed(row, "delete-onboarding-screen")) {
                const deleteButton = document.createElement("button");
                deleteButton.type = "button";
                deleteButton.className = "btn btn-outline-danger p-1 delete-onboarding-screen";
                deleteButton.dataset.url = row.delete_url || "";
                deleteButton.dataset.title = row.title_raw || "";
                deleteButton.dataset.onboardingRowId = String(row.id);
                deleteButton.title = "Delete";
                deleteButton.innerHTML = '<i class="ti ti-trash"></i>';
                actions.appendChild(deleteButton);
            }

            card.append(media, body, actions);
            list.appendChild(card);
        });

        for (let index = rows.length; index < maxScreens; index += 1) {
            const slot = document.createElement("button");
            slot.type = "button";
            slot.className = "onboarding-builder-slot";
            slot.dataset.onboardingCreate = "";
            slot.innerHTML = `<i class="ti ti-plus"></i><span>Screen ${index + 1}</span>`;
            slot.disabled = json?.can_create === false;
            list.appendChild(slot);
        }

        renderPreviewSequence();
    }

    function openPreviewSequence() {
        previewMode = "app";
        previewIndex = 0;
        renderPreviewSequence();
        bootstrapModal("onboarding-screen-preview-modal")?.show();
    }

    function openCreateForm(anchor = null) {
        const form = formElement();
        if (!form) {
            return;
        }

        const session = nextFilePondSession();
        resetOnboardingForm();
        showInlineEditor(anchor);
        setTimeout(() => {
            if (session === filePondSession) {
                resetFormMediaState(session);
                applyDesignToForm(designDefaults);
            }
        }, 100);
    }

    function openEditForm(rowData, anchor = null) {
        const form = formElement();
        if (!form || !rowData) {
            return;
        }

        const session = nextFilePondSession();
        form.reset();
        form.action = rowData.update_url;
        form.querySelector("[data-onboarding-form-app]").value = rowData.app_type;
        form.querySelector("[data-onboarding-form-title]").textContent = labels.editTitle || "Edit Onboarding Screen";
        form.querySelector("[data-onboarding-form-submit]").textContent = labels.updateButton || "Update";
        form.querySelector('[data-onboarding-form-field="title"]').value = rowData.title_raw || "";
        form.querySelector('[data-onboarding-form-field="subtitle"]').value = rowData.subtitle_raw || "";
        applyDesignToForm(rowData.design || designDefaults);
        updateCharacterCounters(form);
        revokePreviewObjectUrl();
        currentPreviewImage = rowData.image_url || "";
        currentPreviewMediaType = rowData.media_type || "image";
        currentBackgroundImage = rowData.background_image_url || rowData.design?.background?.image || "";
        currentOverlayImage = rowData.overlay_image_url || rowData.design?.overlay?.image || "";
        showInlineEditor(anchor);
        setTimeout(() => {
            if (session !== filePondSession) {
                return;
            }
            prepareFileInput(rowData.image_url || "", rowData.media_mime_type || mimeTypeFromSource(rowData.image_url));
            prepareBackgroundFileInput(currentBackgroundImage);
            prepareOverlayFileInput(currentOverlayImage);
            loadEditMediaIntoFilePond(rowData, session);
        }, 100);
    }

    function rowDataFromButton(button) {
        if (button.dataset.onboardingRowId && builderRows.has(button.dataset.onboardingRowId)) {
            return builderRows.get(button.dataset.onboardingRowId);
        }

        const table = currentTable();
        if (!table) {
            return null;
        }

        let row = $(button).closest("tr");
        if (row.hasClass("child")) {
            row = row.prev();
        }

        return table.row(row).data();
    }

    function bindIndex(root) {
        root.querySelectorAll("[data-onboarding-app-tab]").forEach((button) => {
            button.addEventListener("click", () => {
                hideInlineEditor();
                setActiveApp(root, button.dataset.onboardingAppTab);
            });
        });
        root.addEventListener("click", (event) => {
            const createButton = event.target.closest("[data-onboarding-create]");
            if (createButton && root.contains(createButton)) {
                openCreateForm(createButton);
                return;
            }

            const previewButton = event.target.closest("[data-onboarding-preview-index]");
            if (previewButton && root.contains(previewButton)) {
                previewMode = "app";
                previewIndex = Number(previewButton.dataset.onboardingPreviewIndex || 0);
                renderPreviewSequence();
            }
        });
        root.querySelector("[data-onboarding-preview-app]")?.addEventListener("click", openPreviewSequence);
        root.querySelectorAll("[data-onboarding-toggle]").forEach((toggle) => {
            toggle.addEventListener("change", async () => {
                const appType = toggle.dataset.onboardingToggle;
                const enabled = toggle.checked;
                const url = root.dataset.toggleUrlTemplate.replace("__APP__", appType);
                toggle.disabled = true;

                try {
                    const response = await fetch(url, { method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": csrf() }, body: JSON.stringify({ is_enabled: enabled }) });
                    const data = await response.json();
                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || "Unable to update onboarding setting.");
                    }
                    Toast.fire({ icon: "success", title: data.message });
                } catch (error) {
                    toggle.checked = !enabled;
                    Toast.fire({ icon: "error", title: error.message });
                } finally {
                    toggle.disabled = false;
                }
            });
        });
        root.querySelectorAll("[data-onboarding-device]").forEach((button) => {
            button.addEventListener("click", () => applyPreviewDevice(button.dataset.onboardingDevice));
        });
        $("#onboarding-screens-table").on("xhr.dt", (event, settings, json) => {
            previewScreens = Array.isArray(json?.preview) ? json.preview : [];
            previewIndex = 0;
            setCreateButtonState(root, json?.can_create !== false);
            renderBuilderList(root, json);
            try {
                renderPreviewSequence();
            } catch (error) {
                console.error("Unable to render onboarding preview.", error);
            }
        });
        $("#onboarding-screens-table").on("error.dt", (event, settings, techNote, message) => {
            $("#datatable-loading").addClass("d-none");
            console.error("Onboarding screens table error:", message);
            if (window.Toast) {
                Toast.fire({ icon: "error", title: message || "Unable to load onboarding screens." });
            }
        });
        formElement()?.addEventListener("form-submit:success", () => {
            currentTable()?.ajax.reload(null, false);
            hideInlineEditor();
            resetOnboardingForm();
            resetFormMediaState();
            setTimeout(() => {
                resetOnboardingForm();
                resetFormMediaState();
            }, 150);
        });
        document.querySelectorAll("[data-onboarding-editor-cancel]").forEach((button) => {
            button.addEventListener("click", () => {
                hideInlineEditor();
                resetOnboardingForm();
                resetFormMediaState();
            });
        });
        document.getElementById("onboarding-screen-form-modal")?.addEventListener("hidden.bs.modal", () => {
            resetOnboardingForm();
            resetFormMediaState();
            setTimeout(() => {
                resetOnboardingForm();
                resetFormMediaState();
            }, 150);
        });
        selectedApp = root.dataset.selectedApp || "customer";
        root.dataset.selectedApp = selectedApp;
        applyPreviewDevice(previewDevice);
        const appInput = document.querySelector("[data-onboarding-form-app]");
        if (appInput) {
            appInput.value = selectedApp;
        }
        renderPreviewSequence();
    }

    function bindRowActions() {
        document.addEventListener("click", (event) => {
            const editButton = event.target.closest(".edit-onboarding-screen");
            if (editButton) {
                const rowData = rowDataFromButton(editButton);
                if (!rowData) {
                    console.error("Unable to read onboarding row data for edit action.");
                    if (window.Toast) {
                        Toast.fire({ icon: "error", title: "Unable to open edit form. Please refresh the table." });
                    }
                    return;
                }
                openEditForm(rowData, editButton);
                return;
            }

            const deleteButton = event.target.closest(".delete-onboarding-screen");
            if (!deleteButton) {
                return;
            }

            Swal.fire({ title: "Are you sure?", html: labels.deleteConfirmation || "You are about to delete this onboarding screen.", icon: "warning", showCancelButton: true, confirmButtonColor: primaryColor, cancelButtonColor: "#d33", confirmButtonText: "Yes, delete it!" }).then(async (result) => {
                if (!result.isConfirmed) {
                    return;
                }

                try {
                    const response = await fetch(deleteButton.dataset.url, { method: "DELETE", headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf() } });
                    const data = await response.json();
                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || "Unable to delete onboarding screen.");
                    }
                    currentTable()?.ajax.reload(null, false);
                    Swal.fire("Deleted!", data.message, "success");
                } catch (error) {
                    Swal.fire("Error!", error.message, "error");
                }
            });
        });
    }

    function bindDesignControls() {
        document.querySelectorAll("[data-onboarding-design-field]").forEach((field) => {
            ["input", "change"].forEach((eventName) => {
                field.addEventListener(eventName, () => {
                    updateRangeValue(field);
                    toggleDesignFieldVisibility();
                    updatePreview(screenFromForm());
                });
            });
        });
        document.querySelectorAll("[data-onboarding-preset]").forEach((button) => {
            button.addEventListener("click", () => {
                const preset = normalizeDesign(presets[button.dataset.onboardingPreset] || designDefaults);
                preset.background.image = currentBackgroundImage;
                preset.overlay.image = currentOverlayImage;
                applyDesignToForm(preset);
                updatePreview(screenFromForm());
            });
        });
    }

    function bindPreviewFromForm() {
        document.querySelector("[data-onboarding-preview-current]")?.addEventListener("click", () => openPreview(screenFromForm()));
        document.querySelector("[data-onboarding-preview-next]")?.addEventListener("click", () => {
            if (previewMode !== "app" || previewScreens.length <= 1) {
                return;
            }
            previewIndex = (previewIndex + 1) % previewScreens.length;
            renderPreviewSequence();
        });

        function setPreviewFile(file) {
            if (!file) {
                return;
            }

            if (currentPreviewObjectUrl) {
                URL.revokeObjectURL(currentPreviewObjectUrl);
            }
            currentPreviewObjectUrl = URL.createObjectURL(file);
            currentPreviewImage = currentPreviewObjectUrl;
            currentPreviewMediaType = mediaTypeFromMime(file.type || file.name);
            updatePreview(screenFromForm());
        }

        function setBackgroundFile(file) {
            if (!file) {
                return;
            }

            if (currentBackgroundObjectUrl) {
                URL.revokeObjectURL(currentBackgroundObjectUrl);
            }
            currentBackgroundObjectUrl = URL.createObjectURL(file);
            currentBackgroundImage = currentBackgroundObjectUrl;
            setFieldValue("background_type", "image");
            toggleDesignFieldVisibility();
            updatePreview(screenFromForm());
        }

        function setOverlayFile(file) {
            if (!file) {
                return;
            }

            if (currentOverlayObjectUrl) {
                URL.revokeObjectURL(currentOverlayObjectUrl);
            }
            currentOverlayObjectUrl = URL.createObjectURL(file);
            currentOverlayImage = currentOverlayObjectUrl;
            setFieldValue("overlay_enabled", true);
            setFieldValue("overlay_type", "image");
            if (Number(formElement()?.querySelector('[data-onboarding-design-field="overlay_opacity"]')?.value || 0) === 0) {
                setFieldValue("overlay_opacity", 1);
            }
            toggleDesignFieldVisibility();
            updatePreview(screenFromForm());
        }

        document.addEventListener("change", (event) => {
            const field = eventField(event);
            if (field?.matches('[data-onboarding-form-field="image"]')) {
                setPreviewFile(field.files?.[0]);
            }
            if (field?.matches('[data-onboarding-design-field="background_image"]')) {
                setBackgroundFile(field.files?.[0]);
            }
            if (field?.matches('[data-onboarding-design-field="overlay_image"]')) {
                setOverlayFile(field.files?.[0]);
            }
        });
        document.addEventListener("FilePond:addfile", (event) => {
            const field = eventField(event);
            const file = nativeFileFromPondEvent(event);
            if (field?.matches('[data-onboarding-form-field="image"]') && file) {
                setPreviewFile(file);
            }
            if (field?.matches('[data-onboarding-design-field="background_image"]') && file) {
                setBackgroundFile(file);
            }
            if (field?.matches('[data-onboarding-design-field="overlay_image"]') && file) {
                setOverlayFile(file);
            }
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        mountInlineEditor();
        document.querySelectorAll("[data-onboarding-admin]").forEach(bindIndex);
        bindRowActions();
        bindPreviewFromForm();
        bindCharacterCounters();
        bindDesignControls();
        toggleDesignFieldVisibility();
    });
})();
