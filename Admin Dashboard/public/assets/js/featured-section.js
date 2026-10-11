// Featured Section Management - Refactored for better maintainability
class FeaturedSectionManager {
    constructor() {
        this.customProductsSectionType = 'custom_products';
        this.initializeSortable();
        this.initializeEventListeners();
    }

    // Initialize sortable functionality
    initializeSortable() {
        try {
            // Initialize sortable for each sortable container
            $('.sortable-container').each(function() {
                const container = $(this);
                const group = container.data('group');

                container.sortable({
                    group: group === 'global' ? 'global-list' : `category-${container.data('category-id')}-list`,
                    animation: 200,
                    ghostClass: 'ghost',
                    onSort: () => console.log(`The sort order has changed for ${group} group`),
                });
            });
        } catch (e) {
            console.error('Error initializing sortable:', e);
        }
    }

    // Initialize all event listeners
    initializeEventListeners() {
        $('#get-section-order').on('click', () => this.handleSortOrder());
        $('#background_type').on('change', (e) => this.handleBackgroundTypeChange(e.target.value));
        $('#featuredAllZones').on('change', () => this.toggleZonesField());
        $('#section_type').on('change', (e) => this.toggleSelectionFields(e.target.value));
        $('#scopeType, #select-root-category').on('change', () => this.clearSelectedProducts());
        document.addEventListener('show.bs.modal', (e) => this.handleModalShow(e));
        document.addEventListener('click', (e) => this.handleDelete(e));
        this.initializeCollapseHandlers();
    }

    toggleSelectionFields(sectionType) {
        const categoriesField = document.getElementById('categoriesField');
        const productsField = document.getElementById('productsField');
        const sortOrderField = document.getElementById('sortOrderField');
        const isCustomProducts = sectionType === this.customProductsSectionType;

        if (categoriesField) {
            categoriesField.style.display = isCustomProducts ? 'none' : '';
        }

        if (productsField) {
            productsField.style.display = isCustomProducts ? '' : 'none';
        }

        if (sortOrderField) {
            sortOrderField.style.display = isCustomProducts ? 'none' : '';
        }
    }

    clearSelectedProducts() {
        const productSelect = document.getElementById('select-product');
        if (!productSelect?.tomselect) return;

        productSelect.tomselect.clear();
        productSelect.tomselect.clearOptions();
    }

    // Lazily build TomSelect on the zones multi-select (the modal element exists from page load).
    ensureZonesTomSelect() {
        const zonesSelect = document.getElementById('featuredZones');
        if (!zonesSelect || !window.TomSelect) return null;
        if (zonesSelect.tomselect) return zonesSelect.tomselect;
        return new TomSelect(zonesSelect, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            placeholder: 'Select zones',
        });
    }

    // "Available in all zones" toggle hides/disables the zones multi-select.
    toggleZonesField() {
        const allZonesEl = document.getElementById('featuredAllZones');
        const zonesField = document.getElementById('featuredZonesField');
        const zonesSelect = document.getElementById('featuredZones');
        if (!allZonesEl || !zonesField || !zonesSelect) return;

        const ts = this.ensureZonesTomSelect();

        if (allZonesEl.checked) {
            zonesField.style.display = 'none';
            if (ts) {
                ts.clear();
                ts.disable();
            }
        } else {
            zonesField.style.display = '';
            if (ts) ts.enable();
        }
    }

    // Handle sort order submission
    async handleSortOrder() {
        try {
            const sortData = {};

            // Collect global sections
            const globalContainer = $('#global-sortable-list');
            if (globalContainer.length) {
                const globalSections = globalContainer.sortable('toArray');
                if (globalSections.length > 0) {
                    sortData.global_sections = globalSections;
                }
            }

            // Collect category sections
            const categorySections = {};
            $('.sortable-container[data-group="category"]').each(function() {
                const container = $(this);
                const categoryId = container.data('category-id');
                const sectionIds = container.sortable('toArray');

                if (sectionIds.length > 0) {
                    categorySections[categoryId] = sectionIds;
                }
            });

            if (Object.keys(categorySections).length > 0) {
                sortData.category_sections = categorySections;
            }

            // Check if we have any data to send
            if (!sortData.global_sections && !sortData.category_sections) {
                Toast.fire({
                    icon: "warning",
                    title: "No sections to sort"
                });
                return;
            }

            const response = await axios.post(`${base_url}/admin/featured-sections/sort`, sortData, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const { data } = response;
            Toast.fire({
                icon: data.success === false ? "error" : "success",
                title: data.message
            });
        } catch (error) {
            const message = error.response?.data?.message || "An error occurred while submitting the form.";
            Toast.fire({
                icon: "error",
                title: message
            });
            console.error('Sort order error:', error);
        }
    }

    // Initialize collapse/expand handlers
    initializeCollapseHandlers() {
        // Handle collapse events for all collapsible sections
        $('[data-bs-toggle="collapse"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('data-bs-target');
            const collapseElement = $(target);
            const icon = $(this).find('.collapse-icon');

            // Toggle the collapse
            collapseElement.collapse('toggle');

            // Handle icon rotation
            collapseElement.on('show.bs.collapse', function() {
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                $(this).closest('.section-group-header').attr('aria-expanded', 'true');
            });

            collapseElement.on('hide.bs.collapse', function() {
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                $(this).closest('.section-group-header').attr('aria-expanded', 'false');
            });
        });

        // Store collapse state in localStorage (optional)
        $('[data-bs-toggle="collapse"]').on('click', function() {
            const target = $(this).attr('data-bs-target');
            const isExpanded = $(this).attr('aria-expanded') === 'true';
            localStorage.setItem(`collapse-state-${target}`, !isExpanded);
        });

        // Restore collapse state from localStorage
        this.restoreCollapseState();
    }

    // Restore collapse state from localStorage
    restoreCollapseState() {
        $('[data-bs-toggle="collapse"]').each(function() {
            const target = $(this).attr('data-bs-target');
            const savedState = localStorage.getItem(`collapse-state-${target}`);

            if (savedState === 'false') {
                const collapseElement = $(target);
                const icon = $(this).find('.collapse-icon');

                collapseElement.removeClass('show');
                icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                $(this).attr('aria-expanded', 'false');
            }
        });
    }

    // Handle background type change
    handleBackgroundTypeChange(backgroundType) {
        const colorField = $('#background-color-field');
        const imageField = $('#background-image-field');

        colorField.hide();
        imageField.hide();

        if (backgroundType === 'color') {
            colorField.show();
        } else if (backgroundType === 'image') {
            imageField.css('display', 'flex');
        }
    }

    // Handle modal show event
    handleModalShow(event) {
        if (event.target.id !== 'featured-section-modal') return;

        const triggerButton = event.relatedTarget;
        const featuredId = triggerButton?.getAttribute('data-id');

        if (featuredId) {
            this.setupEditMode(featuredId);
        } else {
            this.setupCreateMode();
        }
    }

    // Setup modal for editing existing featured section
    async setupEditMode(featuredId) {
        try {
            const url = `${base_url}/${panel}/featured-sections/${featuredId}`;
            const response = await fetch(url, { method: 'GET' });
            const responseData = await response.json();

            this.populateFormWithData(responseData.data, featuredId);
            this.loadVisibilityInspector(featuredId);
        } catch (error) {
            console.error('Error fetching featured section data:', error);
            Toast.fire({
                icon: "error",
                title: "Error loading featured section data"
            });
        }
    }

    // Pull the visibility check for this section into the modal panel.
    loadVisibilityInspector(featuredId) {
        const el = document.querySelector('#featured-section-modal [data-visibility-inspector]');
        if (!el || typeof window.loadVisibilityInspector !== 'function') return;
        el.dataset.endpoint = `${base_url}/${panel}/featured-sections/${featuredId}/visibility`;
        window.loadVisibilityInspector(el);
    }

    // Setup modal for creating new featured section
    setupCreateMode() {
        const elements = this.getModalElements();

        if (elements.form) elements.form.reset();
        toggleScopeFields();
        this.toggleSelectionFields('');

        // Hide inspector — there's nothing to inspect until the section is saved.
        const inspector = document.querySelector('#featured-section-modal [data-visibility-inspector]');
        if (inspector) inspector.hidden = true;

        this.removeMethodInput(elements.form);
        this.clearTomSelects(elements);
        this.hideBackgroundFields(elements);
        this.clearFilePond(elements.imageUpload4k);
        this.clearFilePond(elements.imageUploadFhd);
        this.clearFilePond(elements.imageUploadMobile);
        this.clearFilePond(elements.imageUploadTablet);
        this.resetZonesField(elements);

        elements.form.setAttribute('action', `${base_url}/${panel}/featured-sections`);
        elements.modalTitle.textContent = 'Add Featured Section';
        elements.submitButton.textContent = 'Add';
    }

    // Reset zones to the "all zones" default (for create mode).
    resetZonesField(elements) {
        if (elements.allZonesCheckbox) elements.allZonesCheckbox.checked = true;
        const ts = this.ensureZonesTomSelect();
        if (ts) ts.clear();
        this.toggleZonesField();
    }

    // Populate form with fetched data
    populateFormWithData(data, featuredId) {
        const elements = this.getModalElements();

        this.clearFilePond(elements.imageUpload4k);
        this.clearFilePond(elements.imageUploadFhd);
        this.clearFilePond(elements.imageUploadMobile);
        this.clearFilePond(elements.imageUploadTablet);
        this.fillBasicFormFields(elements.form, data);
        this.handleBackgroundType(elements, data);
        this.setupTomSelects(elements, data);
        this.setupFilePond(elements, data);
        this.setupZones(elements, data);
        elements.form.setAttribute('action', `${base_url}/${panel}/featured-sections/${featuredId}`);
        elements.modalTitle.textContent = 'Edit Featured Section';
        elements.submitButton.textContent = 'Update';
    }

    // Get all modal elements
    getModalElements() {
        return {
            form: document.querySelector('.form-submit'),
            modalTitle: document.querySelector('#featured-section-modal .modal-title'),
            submitButton: document.querySelector('#featured-section-modal button[type="submit"]'),
            categorySelectElement: document.getElementById('select-category'),
            productSelectElement: document.getElementById('select-product'),
            selectScopeElement: document.getElementById('select-root-category'),
            colorField: document.getElementById('background-color-field'),
            imageField: document.getElementById('background-image-field'),
            imageUpload4k: document.querySelector('#desktop_4k_background_image'),
            imageUploadFhd: document.querySelector('#desktop_fdh_background_image'),
            imageUploadTablet: document.querySelector('#tablet_background_image'),
            imageUploadMobile: document.querySelector('#mobile_background_image'),
            allZonesCheckbox: document.getElementById('featuredAllZones'),
            zonesSelect: document.getElementById('featuredZones'),
        };
    }

    // Fill basic form fields
    fillBasicFormFields(form, data) {
        const fields = [
            { name: 'title', value: data.title || '' },
            { name: 'short_description', value: data.short_description || '' },
            { name: 'section_type', value: data.section_type || '' },
            { name: 'style', value: data.style || '' },
            { name: 'background_type', value: data.background_type || '' },
            { name: 'text_color', value: data.text_color || '' },
            { name: 'scope_type', value: data.scope_type || '' }
        ];

        fields.forEach(field => {
            const element = form.querySelector(`[name="${field.name}"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = field.value === 'active';
                } else {
                    element.value = field.value;
                }
            }
        });

        const statusField = form.querySelector('input[name="status"]');
        if (statusField) statusField.checked = data.status === 'active';

        toggleScopeFields();
        this.toggleSelectionFields(data.section_type || '');
    }

    // Handle background type specific logic
    handleBackgroundType(elements, data) {
        const backgroundType = data.background_type;

        if (backgroundType === 'color') {
            elements.colorField.style.display = 'block';
            elements.imageField.style.display = 'none';
            const colorInput = elements.form.querySelector('input[name="background_color"]');
            if (colorInput) colorInput.value = data.background_color || '';
        } else if (backgroundType === 'image') {
            elements.colorField.style.display = 'none';
            elements.imageField.style.display = 'flex';
        } else {
            elements.colorField.style.display = 'none';
            elements.imageField.style.display = 'none';
        }
    }

    // Setup TomSelect dropdowns
    setupTomSelects(elements, data) {
        let categoryTomSelect = elements.categorySelectElement.tomselect || new TomSelect(elements.categorySelectElement);
        let productTomSelect = elements.productSelectElement.tomselect || new TomSelect(elements.productSelectElement);
        let tomSelectScope = elements.selectScopeElement.tomselect || new TomSelect(elements.selectScopeElement);

        // Handle scope selection
        if (data.scope_type === 'category') {
            tomSelectScope.addOption({
                value: data.scope_id,
                text: data.scope_category_title,
            });
            tomSelectScope.setValue(data.scope_id);
        } else {
            tomSelectScope.clearOptions();
            tomSelectScope.clear();
        }

        if (data.categories && Array.isArray(data.categories)) {
            data.categories.forEach(item => {
                categoryTomSelect.addOption({ value: item.id, text: item.title });
            });
            categoryTomSelect.setValue(data.categories.map(item => item.id));
        }

        if (data.selected_products && Array.isArray(data.selected_products)) {
            data.selected_products.forEach(item => {
                productTomSelect.addOption({ value: item.id, text: item.title });
            });
            const allIds = data.selected_products.map(item => item.id);
            productTomSelect.setValue(allIds);
        }
    }

    // Restore the zones state from the API payload. Empty list = "all zones".
    setupZones(elements, data) {
        const zoneIds = Array.isArray(data.zones) ? data.zones.map(z => z.id) : [];
        const allZones = zoneIds.length === 0;

        if (elements.allZonesCheckbox) elements.allZonesCheckbox.checked = allZones;

        const ts = this.ensureZonesTomSelect();
        if (ts) {
            ts.clear();
            if (!allZones) ts.setValue(zoneIds);
        }

        this.toggleZonesField();
    }

    // Setup FilePond for image upload
    setupFilePond(elements, data) {
        if (typeof FilePond === 'undefined') return;

        const backgroundType = data.background_type;
        if (backgroundType !== 'image') return;

        const map = [
            {el: elements.imageUpload4k, key: 'desktop_4k_background_image'},
            {el: elements.imageUploadFhd, key: 'desktop_fdh_background_image'},
            {el: elements.imageUploadTablet, key: 'tablet_background_image'},
            {el: elements.imageUploadMobile, key: 'mobile_background_image'},
        ];

        map.forEach(({el, key}) => {
            if (!el) return;
            const pond = FilePond.find(el);
            if (!pond) return;
            pond.removeFiles();
            if (data[key]) {
                pond.addFile(data[key]);
            }
        });
    }

    // Utility functions
    removeMethodInput(form) {
        const methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) methodInput.remove();
    }

    clearTomSelects(elements) {
        const categoryTomSelect = elements.categorySelectElement.tomselect;
        const productTomSelect = elements.productSelectElement.tomselect;
        const tomSelectScope = elements.selectScopeElement.tomselect;

        if (tomSelectScope) {
            tomSelectScope.clearOptions();
            tomSelectScope.clear();
        }
        if (categoryTomSelect) {
            categoryTomSelect.clearOptions();
            categoryTomSelect.clear();
        }
        if (productTomSelect) {
            productTomSelect.clearOptions();
            productTomSelect.clear();
        }
    }

    hideBackgroundFields(elements) {
        elements.colorField.style.display = 'none';
        elements.imageField.style.display = 'none';
    }

    clearFilePond(imageUpload) {
        if (typeof FilePond !== 'undefined' && imageUpload) {
            const pond = FilePond.find(imageUpload);
            if (pond) pond.removeFiles();
        }
    }

    // Handle delete functionality
    handleDelete(event) {
        handleDelete(event, '.delete-featured-section', `/${panel}/featured-sections/`, 'You are about to delete this Featured Section.');
    }
}

// Initialize the Featured Section Manager when DOM is ready
$(document).ready(() => {
    new FeaturedSectionManager();
    const table = $('#featured-table').DataTable();

    $('#typeFilter, #statusFilter, #scopeTypeFilter, #zoneFilter').on('change', function () {
        table.ajax.reload(null, false);
    });

    // Add filter params to AJAX request
    $('#featured-table').on('preXhr.dt', function (e, settings, data) {
        data.type = $('#typeFilter').val();
        data.visibility_status = $('#statusFilter').val();
        data.scope_type = $('#scopeTypeFilter').val();
        data.zone_id = $('#zoneFilter').val();
    });
});
