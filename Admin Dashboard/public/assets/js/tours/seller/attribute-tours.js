/**
 * Seller Product Attributes Guided Tour Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        seller_product_attributes: {
            url: getBasePath() + '/seller/attributes',
            steps: [
                {
                    element: '#global-attribute-table_wrapper, #global-attribute-table, .card-table',
                    popover: {
                        title: 'Product Attributes Catalog',
                        description: 'Here you can view all product attributes (e.g., Size, Color, Weight) used for product variants.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-attribute-btn, button[data-bs-target="#attribute-create-update-modal"]',
                    popover: {
                        title: 'Create Product Attribute',
                        description: 'Click Create Attribute to define a new attribute type (e.g. Size).',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-attribute-btn, button[data-bs-target="#attribute-create-update-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#attribute-create-update-modal .modal-body',
                    popover: {
                        title: 'Attribute Name & Type',
                        description: 'Enter attribute title (e.g. Size) and choose display type (select, radio, color).',
                        side: 'center'
                    }
                },
                {
                    element: '#add-attribute-value-btn, button[data-bs-target="#attribute-value-create-update-modal"]',
                    popover: {
                        title: 'Create Attribute Values',
                        description: 'Click Create Attribute Value to define specific values (e.g., Small, Medium, Large).',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const attrModal = document.getElementById('attribute-create-update-modal');
                        if (attrModal) {
                            const closeBtn = attrModal.querySelector('.btn-close, [data-bs-dismiss="modal"]');
                            if (closeBtn) closeBtn.click();
                        }
                        const valBtn = document.querySelector('#add-attribute-value-btn, button[data-bs-target="#attribute-value-create-update-modal"]');
                        if (valBtn) valBtn.click();
                    }
                },
                {
                    element: '#attribute-value-create-update-modal .modal-body',
                    popover: {
                        title: 'Assign Attribute Values',
                        description: 'Select parent attribute and add value options (e.g. Red, Blue, XL).',
                        side: 'center'
                    }
                }
            ]
        }
    });
})();
