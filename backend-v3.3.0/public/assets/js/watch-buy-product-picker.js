'use strict';

(function () {
    const extractOptions = (payload, limit) => {
        const rows = Array.isArray(payload) ? payload : (Array.isArray(payload?.data) ? payload.data : []);

        return rows.slice(0, limit);
    };

    const renderProduct = (product, escape) => {
        const imageUrl = product.image_url || product.image;
        const image = imageUrl
            ? `<img src="${escape(imageUrl)}" class="avatar avatar-sm rounded object-contain me-2" alt="">`
            : '';

        return `<div class="d-flex align-items-center">${image}<div>${escape(product.text || '')}</div></div>`;
    };

    const initializeProductPicker = (element) => {
        if (element.tomselect || typeof window.TomSelect === 'undefined') {
            return;
        }

        const endpoint = element.dataset.endpoint;
        const preloadLimit = Number.parseInt(element.dataset.preloadLimit || '15', 10);
        const maxItems = Number.parseInt(element.dataset.maxItems || '0', 10) || null;
        const sourceElement = element.dataset.sourceSelector
            ? document.querySelector(element.dataset.sourceSelector)
            : null;
        const profileElement = element.dataset.profileSelector
            ? document.querySelector(element.dataset.profileSelector)
            : null;
        const primaryElement = element.dataset.primarySelect
            ? document.querySelector(element.dataset.primarySelect)
            : null;
        let selected = [];

        try {
            selected = JSON.parse(element.dataset.selected || '[]').map(String);
        } catch (error) {
            selected = [];
        }

        if (!endpoint) {
            return;
        }

        const requestUrl = (query, selectedValues = []) => {
            const url = new URL(endpoint, window.location.origin);

            url.searchParams.set('search', query || '');
            url.searchParams.set('limit', String(preloadLimit));

            if (sourceElement) {
                url.searchParams.set('source', sourceElement.value);
            }

            if (profileElement) {
                url.searchParams.set('profile_id', profileElement.value);
            }

            selectedValues.forEach((value) => url.searchParams.append('find_in[]', value));

            return url;
        };

        const picker = new window.TomSelect(element, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            placeholder: element.dataset.placeholder || 'Search eligible products',
            preload: 'focus',
            loadThrottle: 300,
            maxItems,
            render: {
                item: renderProduct,
                option: renderProduct,
            },
            load(query, callback) {
                fetch(requestUrl(query), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(response))
                    .then((payload) => callback(extractOptions(payload, preloadLimit)))
                    .catch(() => callback());
            },
        });

        const synchronizePrimaryProduct = () => {
            if (!primaryElement) {
                return;
            }

            const values = Array.isArray(picker.getValue())
                ? picker.getValue()
                : [picker.getValue()].filter(Boolean);
            const selectedPrimary = primaryElement.value || primaryElement.dataset.selected || '';

            primaryElement.replaceChildren(new Option(primaryElement.dataset.placeholder || '', ''));

            values.forEach((value) => {
                const product = picker.options[value];
                primaryElement.add(new Option(product?.text || value, value));
            });

            if (values.includes(String(selectedPrimary))) {
                primaryElement.value = String(selectedPrimary);
            } else if (values.length === 1) {
                primaryElement.value = String(values[0]);
            }

            primaryElement.dataset.selected = primaryElement.value;
        };

        picker.on('item_add', synchronizePrimaryProduct);
        picker.on('item_remove', synchronizePrimaryProduct);

        const updateSellerProfileVisibility = () => {
            const profileContainer = document.getElementById('watch-buy-seller-profile-container');
            const profileSelect = document.getElementById('watch-buy-seller-profile-select');
            if (profileContainer && sourceElement) {
                const isSellerBehalf = sourceElement.value === 'seller_behalf';
                profileContainer.style.display = isSellerBehalf ? '' : 'none';
                if (profileSelect) {
                    profileSelect.required = isSellerBehalf;
                    if (!isSellerBehalf) {
                        profileSelect.tomselect ? profileSelect.tomselect.clear(true) : profileSelect.value = '';
                    }
                }
            }
        };

        if (sourceElement) {
            updateSellerProfileVisibility();
        }

        [sourceElement, profileElement].filter(Boolean).forEach((contextElement) => {
            contextElement.addEventListener('change', () => {
                if (contextElement === sourceElement) {
                    updateSellerProfileVisibility();
                }
                picker.clear(true);
                picker.clearOptions();
                picker.clearCache();
                synchronizePrimaryProduct();
                picker.load('');
            });
        });

        if (selected.length > 0) {
            fetch(requestUrl('', selected), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(response))
                .then((payload) => {
                    extractOptions(payload, selected.length).forEach((option) => picker.addOption(option));
                    picker.setValue(selected, true);
                    synchronizePrimaryProduct();
                })
                .catch(() => synchronizePrimaryProduct());
        } else {
            synchronizePrimaryProduct();
        }
    };

    const initializeProfileSelect = (element) => {
        if (element.tomselect || typeof window.TomSelect === 'undefined') {
            return;
        }

        new window.TomSelect(element, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            valueField: 'id',
            labelField: 'text',
            searchField: 'text',
            placeholder: element.dataset.placeholder || '',
            load(query, callback) {
                if (!query.length) return callback();
                const url = new URL(element.dataset.endpoint, window.location.origin);
                url.searchParams.set('search', query);
                fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((response) => response.ok ? response.json() : Promise.reject(response))
                    .then((payload) => callback(extractOptions(payload, 30)))
                    .catch(() => callback());
            },
        });
    };

    const initialize = () => {
        if (typeof window.TomSelect === 'undefined') {
            window.setTimeout(initialize, 50);

            return;
        }

        document.querySelectorAll('.watch-buy-reel-profile-select').forEach(initializeProfileSelect);
        document.querySelectorAll('.watch-buy-reel-products').forEach(initializeProductPicker);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
