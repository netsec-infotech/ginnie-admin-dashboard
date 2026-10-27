/**
 * Home Page Builder Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        customize_home_page: {
            url: getBasePath() + '/admin/home-builder',
            validPaths: ['/admin/home-builder'],
            steps: [
                {
                    element: '#hb-tabs',
                    popover: {
                        title: 'Home Builder Workspaces',
                        description: 'Switch seamlessly between <strong>Delivery Zone Layouts</strong>, <strong>Navbar & Header Appearance</strong>, <strong>App Storefront Layout</strong>, and <strong>Web Storefront Layout</strong>.',
                        side: 'bottom',
                        align: 'start'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="zones"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#zones-layout-table, #hb-pane-zones .card, #hb-pane-zones',
                    popover: {
                        title: 'Zone-Wise Layout Matrix',
                        description: 'Manage storefronts across delivery zones. Zones use the <strong>Global Fallback</strong> by default, or can be customized with zone-specific banners, categories, and promotional sections.',
                        side: 'top',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="zones"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#hb-pane-navbar-header, #categories-sortable-list, .hb-navbar-header-card',
                    popover: {
                        title: 'Navbar Categories & Header Appearance',
                        description: 'Drag and drop to reorder category navigation pills. Customize background colors, upload category header banner images, or configure animated Lottie graphics.',
                        side: 'top',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="navbar-header"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#hb-pane-app .hb-canvas-preview-wrapper, #hb-pane-app .hb-builder-canvas',
                    popover: {
                        title: 'Interactive Real-Time Device Canvas',
                        description: 'Preview changes live inside an interactive mobile mockup. Test Light and Dark mode appearances, toggle screen resolutions, and expand into Fullscreen mode.',
                        side: 'right',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="app"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#hb-pane-app [data-role="open-templates"]',
                    popover: {
                        title: 'Template & Preset Library',
                        description: 'Click <strong>Browse Templates</strong> to insert pre-built hero banners, flash sale sliders, product grids, or full cohesive homepage theme packs with a single click.',
                        side: 'bottom',
                        align: 'start'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="app"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#hb-pane-app .hb-sections-sidebar, #hb-pane-app .hb-editor-card',
                    popover: {
                        title: 'Section Manager & Drag-Drop Ordering',
                        description: 'Easily drag and drop sections to rearrange the homepage flow. Duplicate, hide, or save custom sections as reusable presets.',
                        side: 'left',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="app"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#hb-pane-app [data-role="section-form"], #hb-pane-app .hb-field:first-of-type, #hb-pane-app .hb-editor-card',
                    popover: {
                        title: 'Fine-Tune Feeds & Styling',
                        description: 'Customize section titles, display formats (horizontal carousel, 2-column grid, slider), automated data feeds (Top Rated, Newly Added), and custom background colors.',
                        side: 'left',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="app"]');
                        if (tab) tab.click();
                    }
                },
                {
                    element: '#hb-pane-app [data-role="save-layout"], #hb-pane-app [data-role="open-revisions"], #hb-pane-app .hb-editor-actions',
                    popover: {
                        title: 'Publish & Revisions History',
                        description: 'Click <strong>Save Changes</strong> to publish your layout live to customers. Inspect and roll back to previous layout snapshots anytime with <strong>Revisions</strong>.',
                        side: 'top',
                        align: 'end'
                    },
                    onHighlightStarted: function() {
                        const tab = document.querySelector('#hb-tabs a[data-pane="app"]');
                        if (tab) tab.click();
                    }
                }
            ]
        }
    });
})();
