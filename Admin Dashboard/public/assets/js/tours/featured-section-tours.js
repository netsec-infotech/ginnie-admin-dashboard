/**
 * Featured Section Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_featured_section: {
            url: getBasePath() + '/admin/featured-sections',
            steps: [
                {
                    element: '.card-table',
                    popover: {
                        title: 'List of Featured Sections',
                        description: 'Here you can view all custom product carousels and highlight sections configured for the app.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-featured-section-btn, button[data-bs-target="#featured-section-modal"]',
                    popover: {
                        title: 'Add Featured Section',
                        description: 'Click Add Featured Section to open the creation modal.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-featured-section-btn, button[data-bs-target="#featured-section-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#featured-section-modal .modal-body',
                    popover: {
                        title: 'Section Details & Style',
                        description: 'Configure section title, layout style (grid, carousel, slider), and target items.',
                        side: 'center'
                    }
                },
                {
                    element: '#featured-section-modal button[type="submit"]',
                    popover: {
                        title: 'Save Featured Section',
                        description: 'Click Save to publish the section on your homepage.',
                        side: 'top'
                    }
                }
            ]
        }
    });
})();
