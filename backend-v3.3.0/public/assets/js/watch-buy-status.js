document.addEventListener('DOMContentLoaded', () => {
    const formatCountdown = (expiresAt, label) => {
        const remaining = Math.max(0, new Date(expiresAt).getTime() - Date.now());
        const hours = Math.floor(remaining / 3600000);
        const minutes = Math.floor((remaining % 3600000) / 60000);
        return label.replace(':hours', hours).replace(':minutes', minutes);
    };

    const updateCountdowns = () => {
        document.querySelectorAll('.watch-buy-status-countdown').forEach((element) => {
            element.textContent = formatCountdown(element.dataset.expiresAt, element.dataset.expiresLabel);
        });
    };

    document.querySelectorAll('.watch-buy-status-profile-search').forEach((select) => {
        if (typeof TomSelect === 'undefined' || select.tomselect) {
            return;
        }

        new TomSelect(select, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            placeholder: select.dataset.placeholder,
            load(query, callback) {
                if (!query.length) return callback();
                const url = new URL(select.dataset.endpoint, window.location.origin);
                url.searchParams.set('search', query);
                fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((data) => callback(data))
                    .catch(() => callback());
            },
            onChange() {
                select.closest('form')?.submit();
            },
        });
    });

    document.querySelectorAll('.watch-buy-status-media').forEach((input) => {
        if (typeof FilePond === 'undefined' || FilePond.find(input)) {
            return;
        }

        FilePond.create(input, {
            allowImagePreview: true,
            credits: false,
            storeAsFile: true,
            acceptedFileTypes: ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'],
            maxFileSize: input.dataset.maxFileSize || null,
            labelIdle: input.dataset.idleLabel || undefined,
        });
    });

    document.querySelectorAll('.watch-buy-status-form').forEach((form) => {
        const source = form.querySelector('.watch-buy-status-source');
        const profileField = form.querySelector('.watch-buy-status-profile-field');
        const profile = profileField?.querySelector('select');
        const products = form.querySelector('.watch-buy-status-products');

        if (profile && typeof TomSelect !== 'undefined' && !profile.tomselect) {
            new TomSelect(profile, {
                allowEmptyOption: true,
                create: false,
                placeholder: profile.dataset.placeholder,
            });
        }

        if (products && typeof TomSelect !== 'undefined' && !products.tomselect) {
            new TomSelect(products, {
                valueField: 'id',
                labelField: 'text',
                searchField: 'text',
                maxItems: Number(products.dataset.maxItems),
                preload: 'focus',
                loadThrottle: 300,
                plugins: ['remove_button'],
                placeholder: products.dataset.placeholder,
                load(query, callback) {
                    const url = new URL(products.dataset.endpoint, window.location.origin);
                    const sourceInput = form.querySelector(products.dataset.sourceSelector);
                    const profileInput = form.querySelector(products.dataset.profileSelector);
                    url.searchParams.set('search', query);
                    if (sourceInput) {
                        url.searchParams.set('source', sourceInput.value);
                    }
                    if (profileInput) {
                        url.searchParams.set('profile_id', profileInput.value);
                    }
                    fetch(url, {headers: {'Accept': 'application/json'}})
                        .then((response) => response.ok ? response.json() : Promise.reject())
                        .then(callback)
                        .catch(() => callback());
                },
            });
        }

        const resetProducts = () => {
            products?.tomselect?.clear(true);
            products?.tomselect?.clearOptions();
        };
        const toggleProfile = () => {
            if (!source || !profileField || !profile) {
                return;
            }
            const sellerBehalf = source.value === 'seller_behalf';
            profileField.classList.toggle('d-none', !sellerBehalf);
            profile.required = sellerBehalf;
            if (!sellerBehalf) {
                profile.tomselect ? profile.tomselect.clear(true) : profile.value = '';
            }
            resetProducts();
        };

        source?.addEventListener('change', toggleProfile);
        profile?.addEventListener('change', resetProducts);
        toggleProfile();

        const modalElement = form.closest('.modal');
        if (modalElement) {
            modalElement.addEventListener('hidden.bs.modal', () => {
                form.reset();
                if (source) {
                    source.value = 'admin_direct';
                }
                if (profile?.tomselect) {
                    profile.tomselect.clear(true);
                }
                if (products?.tomselect) {
                    products.tomselect.clear(true);
                    products.tomselect.clearOptions();
                }
                if (typeof FilePond !== 'undefined') {
                    form.querySelectorAll('.watch-buy-status-media').forEach((input) => {
                        FilePond.find(input)?.removeFiles();
                    });
                }
                toggleProfile();
            });

            modalElement.addEventListener('show.bs.modal', () => {
                toggleProfile();
            });
        }
    });

    const previewModal = document.getElementById('watch-buy-status-preview-modal');
    let resetPreview = () => {};
    if (previewModal) {
        const profile = previewModal.querySelector('.watch-buy-status-preview-profile');
        const type = previewModal.querySelector('.watch-buy-status-preview-type');
        const image = previewModal.querySelector('.watch-buy-status-preview-image');
        const video = previewModal.querySelector('.watch-buy-status-preview-video');
        const textOnly = previewModal.querySelector('.watch-buy-status-preview-text-only');
        const unavailable = previewModal.querySelector('.watch-buy-status-preview-unavailable');
        const copy = previewModal.querySelector('.watch-buy-status-preview-copy');
        const expiry = previewModal.querySelector('.watch-buy-status-preview-expiry');
        const creator = previewModal.querySelector('.watch-buy-status-preview-creator');
        const products = previewModal.querySelector('.watch-buy-status-preview-products');
        const openMedia = previewModal.querySelector('.watch-buy-status-preview-open');
        const deleteButton = previewModal.querySelector('.watch-buy-status-preview-delete');

        resetPreview = () => {
            image.classList.add('d-none');
            video.classList.add('d-none');
            textOnly.classList.add('d-none');
            unavailable.classList.add('d-none');
            image.removeAttribute('src');
            video.pause();
            video.removeAttribute('src');
            video.load();
        };

        previewModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger?.classList.contains('watch-buy-status-preview-trigger')) {
                return;
            }

            resetPreview();
            const contentType = trigger.dataset.contentType;
            const mediaUrl = trigger.dataset.mediaUrl;
            const statusText = trigger.dataset.text?.trim() || '';
            profile.textContent = trigger.dataset.profile;
            type.textContent = trigger.dataset.contentTypeLabel;
            expiry.textContent = trigger.dataset.demoMode === '1'
                ? trigger.dataset.neverExpiresLabel
                : formatCountdown(trigger.dataset.expiresAt, trigger.dataset.expiresLabel);
            creator.textContent = trigger.dataset.createdBy;
            products.textContent = trigger.dataset.productsLabel;
            copy.textContent = statusText;
            copy.classList.toggle('d-none', !statusText || contentType === 'text');

            if (contentType === 'image' && mediaUrl) {
                image.src = mediaUrl;
                image.classList.remove('d-none');
            } else if (contentType === 'video' && mediaUrl) {
                video.src = mediaUrl;
                video.classList.remove('d-none');
                video.load();
            } else if (contentType === 'text') {
                textOnly.textContent = statusText;
                textOnly.classList.remove('d-none');
            } else {
                unavailable.classList.remove('d-none');
            }

            openMedia.classList.toggle('d-none', !mediaUrl);
            if (mediaUrl) {
                openMedia.href = mediaUrl;
            }

            deleteButton.classList.toggle('d-none', !trigger.dataset.deleteUrl);
            deleteButton.dataset.statusId = trigger.dataset.statusId;
            deleteButton.dataset.url = trigger.dataset.deleteUrl || '';
            deleteButton.dataset.title = trigger.dataset.deleteTitle || '';
            deleteButton.dataset.text = trigger.dataset.deleteText || '';
            deleteButton.dataset.confirmText = trigger.dataset.deleteConfirmText || '';
            deleteButton.dataset.cancelText = trigger.dataset.deleteCancelText || '';
            deleteButton.disabled = false;
        });

        previewModal.addEventListener('hidden.bs.modal', resetPreview);
    }

    const closePreviewModal = () => {
        if (!previewModal) {
            return;
        }

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(previewModal).hide();
        }

        if (window.jQuery?.fn?.modal) {
            window.jQuery(previewModal).modal('hide');
        }

        window.setTimeout(() => {
            if (!previewModal.classList.contains('show')) {
                return;
            }

            previewModal.classList.remove('show');
            previewModal.style.display = 'none';
            previewModal.setAttribute('aria-hidden', 'true');
            previewModal.removeAttribute('aria-modal');
            previewModal.removeAttribute('role');
            resetPreview();

            if (!document.querySelector('.modal.show')) {
                document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }
        }, 150);
    };

    const removeStatus = (statusId) => {
        document.querySelectorAll(`.watch-buy-status-card[data-status-id="${statusId}"]`).forEach((card) => {
            const container = card.closest('.watch-buy-status-profile-group, .seller-watch-buy-statuses');
            card.remove();
            const remaining = container?.querySelectorAll('.watch-buy-status-card').length || 0;
            const count = container?.querySelector('.watch-buy-status-group-count');
            if (count) {
                const label = remaining === 1 ? count.dataset.singularLabel : count.dataset.pluralLabel;
                count.textContent = label.replace(':count', remaining);
            }
            if (remaining === 0 && container) {
                container.querySelector('.watch-buy-status-gallery')?.classList.add('d-none');
                container.querySelector('.watch-buy-status-inline-empty')?.classList.remove('d-none');
                container.querySelector('.watch-buy-status-pagination')?.classList.add('d-none');
                container.querySelector('.watch-buy-status-section-empty')?.classList.remove('d-none');
            }
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.watch-buy-status-delete');
        if (!button || !button.dataset.url) {
            return;
        }

        Swal.fire({
            title: button.dataset.title,
            text: button.dataset.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: button.dataset.confirmText,
            cancelButtonText: button.dataset.cancelText,
            confirmButtonColor: '#d63939',
            target: 'body',
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }
            button.disabled = true;
            axios.delete(button.dataset.url, {
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json'},
            }).then((response) => {
                Toast.fire({icon: 'success', title: response.data.message});
                removeStatus(button.dataset.statusId);
                closePreviewModal();
                refreshStatusGallery();
            }).catch((error) => {
                Toast.fire({icon: 'error', title: error.response?.data?.message || 'Something went wrong.'});
                button.disabled = false;
            });
        });
    });

    const setStatusGalleryLoading = (loading, label = 'Loading...') => {
        [
            document.getElementById('watch-buy-status-gallery-container'),
            document.querySelector('.seller-watch-buy-statuses .card-body'),
        ].filter(Boolean).forEach((container) => {
            const existingIndicator = container.querySelector('.watch-buy-status-loading');

            if (!loading) {
                if (existingIndicator) {
                    existingIndicator.classList.remove('is-visible');
                    existingIndicator.classList.add('is-hiding');
                    window.setTimeout(() => existingIndicator.remove(), 220);
                }
                container.classList.remove('is-loading');
                return;
            }

            container.classList.add('is-loading');
            existingIndicator?.remove();

            const indicator = document.createElement('div');
            indicator.className = 'watch-buy-status-loading';
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            const text = document.createElement('span');
            text.textContent = label;
            indicator.append(spinner, text);
            container.appendChild(indicator);
            window.requestAnimationFrame(() => indicator.classList.add('is-visible'));
        });
    };

    const refreshStatusGallery = async (url = window.location.href, showLoading = false, loadingLabel = 'Loading...') => {
        const loadingStartedAt = Date.now();
        if (showLoading) {
            setStatusGalleryLoading(true, loadingLabel);
        }

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok) {
                return false;
            }
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            let refreshed = false;

            const adminContainer = document.getElementById('watch-buy-status-gallery-container');
            const newAdminContainer = doc.getElementById('watch-buy-status-gallery-container');
            if (adminContainer && newAdminContainer) {
                adminContainer.innerHTML = newAdminContainer.innerHTML;
                refreshed = true;
            }

            const sellerContainer = document.querySelector('.seller-watch-buy-statuses .card-body');
            const newSellerContainer = doc.querySelector('.seller-watch-buy-statuses .card-body');
            if (sellerContainer && newSellerContainer) {
                sellerContainer.innerHTML = newSellerContainer.innerHTML;
                refreshed = true;
            }

            updateCountdowns();
            return refreshed;
        } catch (e) {
            console.error('Failed to refresh status gallery:', e);
            return false;
        } finally {
            if (showLoading) {
                const remainingLoadingTime = Math.max(0, 450 - (Date.now() - loadingStartedAt));
                if (remainingLoadingTime > 0) {
                    await new Promise((resolve) => window.setTimeout(resolve, remainingLoadingTime));
                }
                setStatusGalleryLoading(false);
            }
        }
    };

    window.refreshStatusGallery = refreshStatusGallery;

    document.querySelectorAll('.watch-buy-status-refresh').forEach((button) => {
        button.addEventListener('click', async () => {
            const refreshUrl = new URL(button.dataset.url || window.location.pathname, window.location.origin).toString();
            button.disabled = true;
            document.querySelectorAll('.watch-buy-status-profile-search').forEach((select) => {
                if (select.tomselect) {
                    select.tomselect.clear(true);
                    select.tomselect.setTextboxValue('');
                    select.tomselect.refreshState();
                } else {
                    select.value = '';
                }
            });
            const refreshed = await refreshStatusGallery(refreshUrl, true, button.dataset.loadingText || 'Loading...');
            button.disabled = false;
            if (refreshed) {
                window.history.replaceState({}, '', refreshUrl);
                return;
            }

            Toast.fire({icon: 'error', title: 'Unable to refresh stories.'});
        });
    });

    // Reset status forms when modals close
    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('hidden.bs.modal', () => {
            const form = modal.querySelector('.watch-buy-status-form');
            if (form) {
                form.reset();
                const mediaInput = form.querySelector('.watch-buy-status-media');
                if (mediaInput && typeof FilePond !== 'undefined') {
                    const pond = FilePond.find(mediaInput);
                    if (pond) pond.removeFiles();
                }
                const products = form.querySelector('.watch-buy-status-products');
                if (products?.tomselect) {
                    products.tomselect.clear(true);
                    products.tomselect.clearOptions();
                }
            }
        });
    });

    document.querySelectorAll('.watch-buy-status-form').forEach((form) => {
        form.addEventListener('form-submit:success', () => {
            window.setTimeout(refreshStatusGallery, 300);
        });
    });

    updateCountdowns();
    window.setInterval(updateCountdowns, 60000);
});
