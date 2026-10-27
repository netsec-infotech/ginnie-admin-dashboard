(function () {
    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || "";
    }

    function notify(icon, title) {
        if (typeof Toast !== "undefined" && Toast?.fire) {
            Toast.fire({ icon, title });
            return;
        }

        if (typeof Swal !== "undefined" && Swal?.fire) {
            Swal.fire({ icon, title, toast: true, position: "top-end", showConfirmButton: false, timer: 3000 });
        }
    }

    function firstError(json, fallback) {
        const errors = json?.data || {};
        const validation = Object.values(errors).flat().find(Boolean);

        return validation || json?.message || fallback;
    }

    function mimeTypeFromSource(source) {
        const text = String(source?.name || source || "").split("?")[0].toLowerCase();
        const extension = text.split(".").pop();
        const types = {
            gif: "image/gif",
            jpeg: "image/jpeg",
            jpg: "image/jpeg",
            json: "application/json",
            mp4: "video/mp4",
            png: "image/png",
            svg: "image/svg+xml",
            txt: "text/plain",
            webm: "video/webm",
            webp: "image/webp",
        };

        return types[extension] || "";
    }

    function loadRemoteFile(source, load, error, progress, abort) {
        const controller = new AbortController();

        fetch(source, { credentials: "same-origin", signal: controller.signal })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Unable to load splash media.");
                }

                const contentLength = response.headers.get("content-length");
                if (contentLength && typeof progress === "function") {
                    progress(true, Number(contentLength), Number(contentLength));
                }

                return response.blob();
            })
            .then(load)
            .catch(() => error("Unable to load splash media."));

        return {
            abort: () => {
                controller.abort();
                if (typeof abort === "function") {
                    abort();
                }
            },
        };
    }

    function field(form, name) {
        return form.querySelector(`[name="${name}"]`);
    }

    function fieldPanel(form, name) {
        return form.querySelector(`[data-splash-field="${name}"]`);
    }

    function previewStage(form) {
        return form.querySelector(".splash-builder-preview .splash-phone-stage");
    }

    function clearPreviewState(state) {
        if (state?.url) {
            URL.revokeObjectURL(state.url);
        }
    }

    function setPanelVisible(panel, visible) {
        panel?.classList.toggle("d-none", !visible);
    }

    function adminRoot() {
        return document.querySelector("[data-splash-admin]");
    }

    function setFieldValue(input, value) {
        if (!input) {
            return;
        }

        if (input.type === "checkbox") {
            input.checked = value === true || value === "1" || value === 1;
            return;
        }

        input.value = value;
    }

    function setPresetState(form, preset, custom = false) {
        form._splashActivePreset = custom ? "custom" : preset;
        form.querySelectorAll("[data-splash-layout-preset]").forEach((button) => {
            button.classList.toggle("active", !custom && button.dataset.splashLayoutPreset === preset);
        });
        form.querySelector("[data-splash-custom-preset]")?.classList.toggle("d-none", !custom);
    }

    function resolvePresetBackgroundType(form, presetType) {
        if (presetType !== "media") {
            return presetType;
        }

        const currentType = field(form, "bg_type")?.value;
        if (currentType === "image" || currentType === "video") {
            return currentType;
        }

        return form.dataset.splashSavedBgVideoUrl ? "video" : "image";
    }

    function applyLayoutPreset(form, button) {
        form._splashApplyingPreset = true;
        setFieldValue(field(form, "bg_type"), resolvePresetBackgroundType(form, button.dataset.bgType));
        setFieldValue(form.querySelector("[data-splash-overlay-enabled]"), button.dataset.overlayEnabled);
        setFieldValue(field(form, "overlay_x_percent"), button.dataset.overlayX);
        setFieldValue(field(form, "overlay_y_percent"), button.dataset.overlayY);
        setFieldValue(field(form, "overlay_width_percent"), button.dataset.overlayWidth);
        setPresetState(form, button.dataset.splashLayoutPreset);
        syncSplashForm(form);
        form._splashApplyingPreset = false;
    }

    function inferLayoutPreset(form) {
        const bgType = field(form, "bg_type")?.value || "color";
        const overlayEnabled = form.querySelector("[data-splash-overlay-enabled]")?.checked || false;
        const overlayX = Number(field(form, "overlay_x_percent")?.value || 50);
        const overlayY = Number(field(form, "overlay_y_percent")?.value || 45);
        const overlayWidth = Number(field(form, "overlay_width_percent")?.value || 60);

        if (bgType === "color" && overlayEnabled && overlayX === 50 && overlayY === 45 && overlayWidth === 55) {
            return "centered";
        }

        if (bgType === "image" && overlayEnabled && overlayX === 50 && overlayY === 28 && overlayWidth === 45) {
            return "top";
        }

        if (bgType === "image" && overlayEnabled && overlayX === 50 && overlayY === 72 && overlayWidth === 45) {
            return "bottom";
        }

        if ((bgType === "image" || bgType === "video") && !overlayEnabled) {
            return "full";
        }

        if (bgType === "color" && !overlayEnabled) {
            return "minimal";
        }

        return "custom";
    }

    function markPresetCustom(form) {
        if (!form._splashApplyingPreset) {
            const preset = inferLayoutPreset(form);
            setPresetState(form, preset, preset === "custom");
        }
    }

    function initLayoutPresets(form) {
        form.querySelectorAll("[data-splash-layout-preset]").forEach((button) => {
            button.addEventListener("click", () => applyLayoutPreset(form, button));
        });

        if (form.dataset.splashHasScreen !== "1") {
            const centeredPreset = form.querySelector('[data-splash-layout-preset="centered"]');
            if (centeredPreset) {
                applyLayoutPreset(form, centeredPreset);
                return;
            }
        }

        const initialPreset = inferLayoutPreset(form);
        setPresetState(form, initialPreset, initialPreset === "custom");
    }

    function previewKind(input, file) {
        if (input.name === "bg_video") {
            return "video";
        }

        if (input.name === "overlay_lottie") {
            return "lottie";
        }

        const name = (file.name || "").toLowerCase();
        const type = file.type || "";

        if (type.startsWith("video/")) {
            return "video";
        }

        if (type.includes("json") || type === "text/plain" || name.endsWith(".json")) {
            return "lottie";
        }

        return "image";
    }

    function createPreviewState(input, file) {
        const nativeFile = nativePreviewFile(file);
        if (!nativeFile) {
            return null;
        }

        return {
            field: input.name,
            kind: previewKind(input, nativeFile),
            file: nativeFile,
            name: nativeFile.name || "Splash preview",
            url: URL.createObjectURL(nativeFile),
        };
    }

    function savedMediaUrlForInput(input) {
        const form = input?.closest(".splash-form");
        if (!form) {
            return "";
        }

        if (input.name === "bg_image") {
            return form.dataset.splashSavedBgImageUrl || "";
        }

        if (input.name === "bg_video") {
            return form.dataset.splashSavedBgVideoUrl || "";
        }

        if (input.name === "overlay_image" || input.name === "overlay_lottie") {
            return form.dataset.splashSavedOverlayUrl || "";
        }

        return "";
    }

    function nativePreviewFile(file) {
        if (!file) {
            return null;
        }

        if (file instanceof Blob) {
            return file;
        }

        if (file.file instanceof Blob) {
            return file.file;
        }

        return null;
    }

    function savedPreviewState(form, kind, url, name) {
        if (!url) {
            return null;
        }

        return {
            field: "saved",
            kind,
            file: null,
            name,
            url,
        };
    }

    function createPreviewElement(state, layer) {
        if (!state?.url) {
            return null;
        }

        const element = state.kind === "video"
            ? document.createElement("video")
            : state.kind === "lottie"
                ? document.createElement("lottie-player")
                : document.createElement("img");

        if (state.kind === "video") {
            element.src = state.url;
            element.muted = true;
            element.loop = true;
            element.controls = true;
            element.playsInline = true;
        } else if (state.kind === "lottie") {
            element.setAttribute("src", state.url);
            element.setAttribute("background", "transparent");
            element.setAttribute("speed", "1");
            element.setAttribute("loop", "");
            element.setAttribute("autoplay", "");
        } else {
            element.src = state.url;
            element.alt = state.name;
        }

        if (layer === "background") {
            element.className = "splash-phone-background";
        }

        return element;
    }

    function paintSelectedMediaImmediately(form, state, target) {
        const stage = previewStage(form);
        if (!stage || !state?.url) {
            return;
        }

        if (target === "background") {
            stage.innerHTML = "";
            stage.style.backgroundColor = "";
            stage.appendChild(createPreviewElement(state, "background"));
            return;
        }

        if (target === "overlay") {
            const overlayType = field(form, "overlay_type")?.value || state.kind;
            if (state.kind !== overlayType) {
                return;
            }

            const overlay = document.createElement("div");
            overlay.className = "splash-phone-overlay";
            overlay.style.left = `${field(form, "overlay_x_percent")?.value || 50}%`;
            overlay.style.top = `${field(form, "overlay_y_percent")?.value || 45}%`;
            overlay.style.width = `${field(form, "overlay_width_percent")?.value || 60}%`;
            overlay.appendChild(createPreviewElement(state, "overlay"));
            stage.querySelector(".splash-phone-overlay")?.remove();
            stage.appendChild(overlay);
        }
    }

    function updateRangeLabels(form) {
        ["overlay_x_percent", "overlay_y_percent", "overlay_width_percent"].forEach((name) => {
            const input = field(form, name);
            const label = form.querySelector(`[data-splash-range-value="${name}"]`);
            if (input && label) {
                label.textContent = `${input.value}%`;
            }
        });
    }

    function renderPreview(form) {
        const stage = previewStage(form);

        if (!stage) {
            return;
        }

        const backgroundPreview = form._splashBackgroundPreview || null;
        const overlayPreview = form._splashOverlayPreview || null;
        const bgType = field(form, "bg_type")?.value || "color";
        const bgColor = field(form, "bg_color")?.value || "#1B5E20";
        const overlayEnabled = form.querySelector("[data-splash-overlay-enabled]")?.checked || false;
        const overlayType = field(form, "overlay_type")?.value || "image";
        const overlayX = field(form, "overlay_x_percent")?.value || 50;
        const overlayY = field(form, "overlay_y_percent")?.value || 45;
        const overlayWidth = field(form, "overlay_width_percent")?.value || 60;
        const savedBackground = bgType === "video"
            ? savedPreviewState(form, "video", form.dataset.splashSavedBgVideoUrl, "Saved background video")
            : savedPreviewState(form, "image", form.dataset.splashSavedBgImageUrl, "Saved background image");
        const savedOverlay = savedPreviewState(form, form.dataset.splashSavedOverlayType || overlayType, form.dataset.splashSavedOverlayUrl, "Saved overlay");
        const activeBackground = backgroundPreview || savedBackground;
        const activeOverlay = overlayPreview || savedOverlay;

        stage.innerHTML = "";
        stage.style.backgroundColor = bgType === "color" ? bgColor : "";

        if (bgType === "color") {
            const label = document.createElement("span");
            label.className = "splash-phone-color-label";
            label.textContent = bgColor;
            stage.appendChild(label);
        } else if ((bgType === "image" && activeBackground?.kind === "image") || (bgType === "video" && activeBackground?.kind === "video")) {
            stage.appendChild(createPreviewElement(activeBackground, "background"));
        } else {
            const empty = document.createElement("div");
            empty.className = "splash-media-empty";
            empty.textContent = "Select background media.";
            stage.appendChild(empty);
        }

        if (overlayEnabled && activeOverlay && activeOverlay.kind === overlayType) {
            const overlayElement = createPreviewElement(activeOverlay, "overlay");
            if (!overlayElement) {
                return;
            }

            const overlay = document.createElement("div");
            overlay.className = "splash-phone-overlay";
            overlay.style.left = `${overlayX}%`;
            overlay.style.top = `${overlayY}%`;
            overlay.style.width = `${overlayWidth}%`;
            overlay.appendChild(overlayElement);
            stage.appendChild(overlay);
        }
    }

    function bindPondPreview(input, pond) {
        if (!pond || input.dataset.splashPreviewBound === "1") {
            return;
        }

        input.dataset.splashPreviewBound = "1";

        pond.on("addfile", (error, item) => {
            if (error) {
                return;
            }

            syncPondPreviewFromInput(input, item?.file);
            scheduleSplashMediaSync(input);
        });

        pond.on("updatefiles", (items) => {
            syncPondPreviewFromInput(input, items?.[0]?.file);
            scheduleSplashMediaSync(input);
        });

        input.addEventListener("change", () => {
            syncPondPreviewFromInput(input, input.files?.[0]);
            scheduleSplashMediaSync(input);
        });

        pond.on("removefile", () => {
            const form = input.closest(".splash-form");
            const target = input.dataset.splashPreview;
            if (!form) {
                return;
            }

            if (target === "background") {
                clearPreviewState(form._splashBackgroundPreview);
                form._splashBackgroundPreview = null;
            }

            if (target === "overlay") {
                clearPreviewState(form._splashOverlayPreview);
                form._splashOverlayPreview = null;
            }

            renderPreview(form);
            scheduleSplashMediaSync(input);
        });
    }

    function syncPondPreview(input, file) {
        const form = input?.closest(".splash-form");
        const nativeFile = nativePreviewFile(file);
        if (!form || !nativeFile) {
            return;
        }

        if (input.name === "bg_image") {
            setFieldValue(field(form, "bg_type"), "image");
        }

        if (input.name === "bg_video") {
            setFieldValue(field(form, "bg_type"), "video");
        }

        if (input.name === "overlay_image") {
            setFieldValue(form.querySelector("[data-splash-overlay-enabled]"), true);
            setFieldValue(field(form, "overlay_type"), "image");
        }

        if (input.name === "overlay_lottie") {
            setFieldValue(form.querySelector("[data-splash-overlay-enabled]"), true);
            setFieldValue(field(form, "overlay_type"), "lottie");
        }

        const target = input.dataset.splashPreview;
        if (target === "background") {
            clearPreviewState(form._splashBackgroundPreview);
            form._splashBackgroundPreview = createPreviewState(input, nativeFile);
            paintSelectedMediaImmediately(form, form._splashBackgroundPreview, target);
        }

        if (target === "overlay") {
            clearPreviewState(form._splashOverlayPreview);
            form._splashOverlayPreview = createPreviewState(input, nativeFile);
            paintSelectedMediaImmediately(form, form._splashOverlayPreview, target);
        }

        syncSplashForm(form);
        renderPreview(form);
    }

    function selectedFileFromInput(input) {
        if (!input) {
            return null;
        }

        const pond = typeof FilePond !== "undefined" ? FilePond.find(input) : null;
        const pondItem = pond?.getFiles?.()[0] || null;
        const pondFile = nativePreviewFile(pondItem?.file || pondItem);

        return pondFile || nativePreviewFile(input.files?.[0]);
    }

    function resolveSplashUploadInput(target) {
        if (!target) {
            return null;
        }

        if (target.matches?.("[data-splash-upload]")) {
            return target;
        }

        const nestedInput = target.querySelector?.("[data-splash-upload]");
        if (nestedInput) {
            return nestedInput;
        }

        const panelInput = target.closest?.("[data-splash-field]")?.querySelector?.("[data-splash-upload]");
        if (panelInput) {
            return panelInput;
        }

        const filePondRoot = target.closest?.(".filepond--root");
        const form = target.closest?.(".splash-form");
        if (!form || typeof FilePond === "undefined") {
            return null;
        }

        return Array.from(form.querySelectorAll("[data-splash-upload]")).find((input) => {
            const pond = FilePond.find(input);
            const root = pond?.root || input.closest?.(".filepond--root") || pond?.element?.closest?.(".filepond--root");

            return input === target
                || input.contains?.(target)
                || pond?.element === target
                || pond?.element?.contains?.(target)
                || root === target
                || root === filePondRoot
                || root?.contains?.(target);
        }) || null;
    }

    function resolveSplashUploadInputFromPond(pond) {
        if (!pond || typeof FilePond === "undefined") {
            return null;
        }

        return Array.from(document.querySelectorAll("[data-splash-upload]")).find((input) => FilePond.find(input) === pond) || null;
    }

    function syncFormPondPreviews(form) {
        if (!form) {
            return;
        }

        form.querySelectorAll("[data-splash-upload]").forEach((input) => {
            const selectedFile = selectedFileFromInput(input);
            if (selectedFile) {
                syncPondPreview(input, selectedFile);
            }
        });

        syncSplashForm(form);
    }

    function scheduleSplashMediaSync(target) {
        const input = resolveSplashUploadInput(target) || target?.closest?.("[data-splash-upload]") || target;
        const form = input?.closest?.(".splash-form") || target?.closest?.(".splash-form");

        // FilePond commits browser files asynchronously, so the live phone preview resyncs across a few short ticks.
        [0, 50, 150, 350, 700].forEach((delay) => window.setTimeout(() => syncFormPondPreviews(form), delay));
    }

    function syncPondPreviewFromInput(input, file = null) {
        if (!input) {
            return;
        }

        const selectedFile = nativePreviewFile(file) || selectedFileFromInput(input);
        if (selectedFile) {
            syncPondPreview(input, selectedFile);
            return;
        }

        [0, 50, 200, 500].forEach((delay) => window.setTimeout(() => {
            const delayedFile = selectedFileFromInput(input);
            if (delayedFile) {
                syncPondPreview(input, delayedFile);
            }
        }, delay));
    }

    function removePondFiles(input) {
        if (typeof FilePond === "undefined" || !input) {
            return;
        }

        FilePond.find(input)?.removeFiles();
    }

    function clearFormPreviewFiles(form) {
        ["bg_image", "bg_video", "overlay_image", "overlay_lottie"].forEach((name) => removePondFiles(field(form, name)));
        clearPreviewState(form._splashBackgroundPreview);
        clearPreviewState(form._splashOverlayPreview);
        form._splashBackgroundPreview = null;
        form._splashOverlayPreview = null;
    }

    function resetFormToDefaults(form) {
        form.dataset.splashHasScreen = "0";
        form.dataset.splashSavedBgImageUrl = "";
        form.dataset.splashSavedBgVideoUrl = "";
        form.dataset.splashSavedOverlayUrl = "";
        form.dataset.splashSavedOverlayType = "";
        setFieldValue(field(form, "bg_type"), "color");
        setFieldValue(field(form, "bg_color"), "#1B5E20");
        setFieldValue(form.querySelector("[data-splash-overlay-enabled]"), true);
        setFieldValue(field(form, "overlay_type"), "image");
        setFieldValue(field(form, "overlay_x_percent"), 50);
        setFieldValue(field(form, "overlay_y_percent"), 45);
        setFieldValue(field(form, "overlay_width_percent"), 55);
        setFieldValue(field(form, "min_display_seconds"), 2);
        setFieldValue(field(form, "activate_at"), "");
        setPresetState(form, "centered");
    }

    function applyScreenToForm(form, screen) {
        clearFormPreviewFiles(form);

        if (!screen) {
            resetFormToDefaults(form);
            syncSplashForm(form);
            return;
        }

        form.dataset.splashHasScreen = "1";
        form.dataset.splashSavedBgImageUrl = screen.background_image_url || "";
        form.dataset.splashSavedBgVideoUrl = screen.background_video_url || "";
        form.dataset.splashSavedOverlayUrl = screen.overlay_url || "";
        form.dataset.splashSavedOverlayType = screen.overlay_type || "";
        setFieldValue(field(form, "bg_type"), screen.bg_type || "color");
        setFieldValue(field(form, "bg_color"), screen.bg_color || "#1B5E20");
        setFieldValue(form.querySelector("[data-splash-overlay-enabled]"), !!screen.overlay_enabled);
        setFieldValue(field(form, "overlay_type"), screen.overlay_type || "image");
        setFieldValue(field(form, "overlay_x_percent"), screen.overlay_x_percent ?? 50);
        setFieldValue(field(form, "overlay_y_percent"), screen.overlay_y_percent ?? 45);
        setFieldValue(field(form, "overlay_width_percent"), screen.overlay_width_percent ?? 60);
        setFieldValue(field(form, "min_display_seconds"), screen.min_display_seconds || 2);
        setFieldValue(field(form, "activate_at"), screen.activate_at || "");

        const preset = inferLayoutPreset(form);
        setPresetState(form, preset, preset === "custom");
        syncSplashForm(form);
    }

    function syncPreviewStateFromUploads(form) {
        const bgType = field(form, "bg_type")?.value || "color";
        const overlayEnabled = form.querySelector("[data-splash-overlay-enabled]")?.checked || false;
        const overlayType = field(form, "overlay_type")?.value || "image";
        const backgroundInput = bgType === "video" ? field(form, "bg_video") : field(form, "bg_image");
        const overlayInput = overlayType === "lottie" ? field(form, "overlay_lottie") : field(form, "overlay_image");
        const backgroundFile = bgType === "color" ? null : selectedFileFromInput(backgroundInput);
        const overlayFile = overlayEnabled ? selectedFileFromInput(overlayInput) : null;

        if (backgroundFile && form._splashBackgroundPreview?.file !== backgroundFile) {
            clearPreviewState(form._splashBackgroundPreview);
            form._splashBackgroundPreview = createPreviewState(backgroundInput, backgroundFile);
        }

        if (overlayFile && form._splashOverlayPreview?.file !== overlayFile) {
            clearPreviewState(form._splashOverlayPreview);
            form._splashOverlayPreview = createPreviewState(overlayInput, overlayFile);
        }
    }

    function syncSplashForm(form) {
        const bgType = field(form, "bg_type")?.value || "color";
        const overlayEnabled = form.querySelector("[data-splash-overlay-enabled]")?.checked || false;
        const overlayType = field(form, "overlay_type")?.value || "image";

        setPanelVisible(fieldPanel(form, "bg_color"), bgType === "color");
        setPanelVisible(fieldPanel(form, "bg_image"), bgType === "image");
        setPanelVisible(fieldPanel(form, "bg_video"), bgType === "video");
        setPanelVisible(form.querySelector("[data-splash-overlay-fields]"), overlayEnabled);
        setPanelVisible(fieldPanel(form, "overlay_image"), overlayEnabled && overlayType === "image");
        setPanelVisible(fieldPanel(form, "overlay_lottie"), overlayEnabled && overlayType === "lottie");
        updateRangeLabels(form);
        syncPreviewStateFromUploads(form);
        renderPreview(form);
    }

    function resetOverlayPosition(form) {
        const defaults = {
            overlay_x_percent: 50,
            overlay_y_percent: 45,
            overlay_width_percent: 60,
        };

        Object.entries(defaults).forEach(([name, value]) => {
            const input = field(form, name);
            if (input) {
                input.value = value;
            }
        });

        syncSplashForm(form);
        markPresetCustom(form);
    }

    function clearOverlay(form) {
        const enabled = form.querySelector("[data-splash-overlay-enabled]");
        if (enabled) {
            enabled.checked = false;
        }

        removePondFiles(field(form, "overlay_image"));
        removePondFiles(field(form, "overlay_lottie"));
        clearPreviewState(form._splashOverlayPreview);
        form._splashOverlayPreview = null;
        resetOverlayPosition(form);
        markPresetCustom(form);
    }

    function initSplashFilePond() {
        if (typeof FilePond === "undefined") {
            return false;
        }

        [
            window.FilePondPluginImagePreview,
            window.FilePondPluginFileValidateType,
            window.FilePondPluginFileValidateSize,
        ].filter(Boolean).forEach((plugin) => {
            try {
                FilePond.registerPlugin(plugin);
            } catch (error) {
                // The shared admin FilePond bootstrap may already register these plugins.
            }
        });

        document.querySelectorAll("[data-splash-upload]").forEach((input) => {
            const existingPond = FilePond.find(input);
            if (existingPond) {
                bindPondPreview(input, existingPond);
                return;
            }

            const acceptedFileTypes = (input.dataset.acceptedFileTypes || "")
                .split(",")
                .map((type) => type.trim())
                .filter(Boolean);
            const savedMediaUrl = savedMediaUrlForInput(input);
            const savedMediaMimeType = mimeTypeFromSource(savedMediaUrl);

            // Splash uploads use FilePond only for local selection and preview; the form submit still sends the real file to Laravel.
            const pond = FilePond.create(input, {
                credits: false,
                storeAsFile: true,
                allowMultiple: false,
                maxFiles: 1,
                allowImagePreview: true,
                acceptedFileTypes,
                maxFileSize: input.dataset.maxFileSize || "6MB",
                labelIdle: 'Drag & Drop your splash media or <span class="filepond--label-action">Browse</span>',
                fileValidateTypeDetectType: (source, type) => new Promise((resolve) => {
                    resolve(type || mimeTypeFromSource(source) || savedMediaMimeType);
                }),
                files: savedMediaUrl ? [{
                    source: savedMediaUrl,
                    options: { type: "remote" },
                }] : [],
                server: {
                    fetch: loadRemoteFile,
                    load: loadRemoteFile,
                },
                onaddfile: (error, item) => {
                    if (!error) {
                        syncPondPreviewFromInput(input, item?.file);
                        scheduleSplashMediaSync(input);
                    }
                },
                onupdatefiles: (items) => {
                    syncPondPreviewFromInput(input, items?.[0]?.file);
                    scheduleSplashMediaSync(input);
                },
            });

            bindPondPreview(input, pond);

            return pond;
        });

        return true;
    }

    function waitForSplashFilePond(attempt = 0) {
        if (initSplashFilePond()) {
            return;
        }

        if (attempt < 20) {
            window.setTimeout(() => waitForSplashFilePond(attempt + 1), 100);
        }
    }

    function initSlotPreviewTabs() {
        const selectedApp = document.querySelector("[data-splash-admin]")?.dataset.splashSelectedApp || "customer";
        const storageKey = `splash:${selectedApp}:selected-slot`;

        document.querySelectorAll("[data-splash-slot-tab]").forEach((button) => {
            button.addEventListener("click", () => {
                const slot = button.dataset.splashSlotTab;
                const card = button.closest(".splash-slot-card");

                card?.querySelectorAll("[data-splash-slot-tab]").forEach((tab) => {
                    tab.classList.toggle("active", tab === button);
                });

                card?.querySelectorAll("[data-splash-slot-pane]").forEach((pane) => {
                    pane.classList.toggle("active", pane.dataset.splashSlotPane === slot);
                });

                window.localStorage?.setItem(storageKey, slot);
            });
        });

        const savedSlot = window.localStorage?.getItem(storageKey);
        if (savedSlot) {
            document.querySelector(`[data-splash-slot-tab="${savedSlot}"]`)?.click();
        }
    }

    function initGlobalMediaPreviewListeners() {
        document.addEventListener("change", (event) => {
            const input = resolveSplashUploadInput(event.target);
            syncPondPreviewFromInput(input, input?.files?.[0]);
            scheduleSplashMediaSync(event.target);
        });

        ["FilePond:addfile", "FilePond:updatefiles", "FilePond:initfile", "FilePond:activatefile", "FilePond:processfile"].forEach((eventName) => {
            document.addEventListener(eventName, (event) => {
                const input = resolveSplashUploadInput(event.target) || resolveSplashUploadInputFromPond(event.detail?.pond);
                const file = event.detail?.file?.file || event.detail?.file || event.detail?.items?.[0]?.file;

                syncPondPreviewFromInput(input, file);
                scheduleSplashMediaSync(input || event.target);
            });
        });

        document.addEventListener("drop", (event) => scheduleSplashMediaSync(event.target), true);
    }

    async function submitForm(form) {
        const formData = new FormData(form);

        const response = await fetch(form.action, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
            },
            body: formData,
        });
        const json = await response.json();

        if (!response.ok || json.success === false) {
            throw new Error(firstError(json, "Unable to save splash screen."));
        }

        return json;
    }

    async function postAction(url, method = "POST") {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
            },
        });
        const json = await response.json();

        if (!response.ok || json.success === false) {
            throw new Error(firstError(json, "Action failed."));
        }

        return json;
    }

    async function fetchAppState() {
        const root = adminRoot();
        if (!root?.dataset.splashAppStateUrl) {
            return null;
        }

        const url = new URL(root.dataset.splashAppStateUrl, window.location.origin);
        url.searchParams.set("app_type", root.dataset.splashSelectedApp || "");

        const response = await fetch(url.toString(), {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        const json = await response.json();

        if (!response.ok || json.success === false) {
            throw new Error(firstError(json, "Unable to refresh splash state."));
        }

        return json.data || {};
    }

    function setActionButton(selector, url) {
        const button = document.querySelector(selector);
        if (!button) {
            return;
        }

        if (url) {
            if (button.hasAttribute("data-splash-promote")) {
                button.dataset.splashPromote = url;
            }
            if (button.hasAttribute("data-splash-delete")) {
                button.dataset.splashDelete = url;
            }
            button.classList.remove("d-none");
            return;
        }

        if (button.hasAttribute("data-splash-promote")) {
            button.dataset.splashPromote = "";
        }
        if (button.hasAttribute("data-splash-delete")) {
            button.dataset.splashDelete = "";
        }
        button.classList.add("d-none");
    }

    async function refreshSplashState() {
        const state = await fetchAppState();
        const currentForm = document.querySelector('[data-splash-slot-pane="current"]');
        const stagedForm = document.querySelector('[data-splash-slot-pane="staged"]');

        if (currentForm) {
            applyScreenToForm(currentForm, state.current || null);
        }

        if (stagedForm) {
            applyScreenToForm(stagedForm, state.staged_next || null);
        }

        setActionButton('[data-splash-action-button="current-delete"]', state.current?.delete_url || "");
        setActionButton('[data-splash-action-button="staged-promote"]', state.staged_next?.promote_url || "");
        setActionButton('[data-splash-action-button="staged-delete"]', state.staged_next?.delete_url || "");
    }

    document.addEventListener("DOMContentLoaded", () => {
        waitForSplashFilePond();
        initSlotPreviewTabs();
        initGlobalMediaPreviewListeners();

        document.querySelectorAll(".splash-form").forEach((form) => {
            initLayoutPresets(form);
            syncSplashForm(form);
            window.setTimeout(() => syncSplashForm(form), 250);
            window.setTimeout(() => syncSplashForm(form), 800);
            form.querySelectorAll("select, input").forEach((input) => {
                input.addEventListener("input", () => {
                    if (["bg_type", "overlay_enabled", "overlay_x_percent", "overlay_y_percent", "overlay_width_percent"].includes(input.name)) {
                        markPresetCustom(form);
                    }
                    syncSplashForm(form);
                });
                input.addEventListener("change", () => {
                    if (["bg_type", "overlay_enabled", "overlay_x_percent", "overlay_y_percent", "overlay_width_percent"].includes(input.name)) {
                        markPresetCustom(form);
                    }
                    syncSplashForm(form);
                });
            });
            form.querySelector("[data-splash-reset-overlay]")?.addEventListener("click", () => resetOverlayPosition(form));
            form.querySelector("[data-splash-clear-overlay]")?.addEventListener("click", () => clearOverlay(form));

            form.addEventListener("submit", async (event) => {
                event.preventDefault();
                try {
                    await submitForm(form);
                    await refreshSplashState();
                    notify("success", "Splash screen saved.");
                } catch (error) {
                    notify("error", error.message || "Unable to save splash screen.");
                }
            });
        });

        document.addEventListener("click", async (event) => {
            const promoteButton = event.target.closest("[data-splash-promote]");
            const deleteButton = event.target.closest("[data-splash-delete]");

            if (!promoteButton && !deleteButton) {
                return;
            }

            event.preventDefault();

            try {
                if (promoteButton?.dataset.splashPromote) {
                    await postAction(promoteButton.dataset.splashPromote);
                    await refreshSplashState();
                    notify("success", "Staged splash promoted.");
                }

                if (deleteButton?.dataset.splashDelete) {
                    await postAction(deleteButton.dataset.splashDelete, "DELETE");
                    await refreshSplashState();
                    notify("success", "Splash screen deleted.");
                }
            } catch (error) {
                notify("error", error.message || "Action failed.");
            }
        });
    });
})();
