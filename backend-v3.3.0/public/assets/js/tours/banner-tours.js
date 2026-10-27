/**
 * Banner Management Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_banner: {
            url: getBasePath() + '/admin/banners',
            validPaths: ['/admin/banners', '/admin/banners/create'],
            filterSteps: function(steps, currentPath) {
                const isFormPage = currentPath.endsWith('/admin/banners/create');
                if (isFormPage) {
                    return steps.filter(step => !step.element.includes('banners-table') && !step.element.includes('add-banner-btn'));
                } else {
                    return steps.filter(step => step.element.includes('banners-table') || step.element.includes('add-banner-btn'));
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/admin/banners')) {
                    localStorage.setItem('active_admin_tour', tourName);
                    var addLink = document.querySelector('#add-banner-btn, .card-actions a[href*="/banners/create"]');
                    window.location.href = addLink ? addLink.href : (getBasePath() + '/admin/banners/create');
                } else {
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    element: '#banners-table_wrapper, #banners-table, .card-table',
                    popover: {
                        title: 'List of Banners',
                        description: 'Here you can see all promotion banners displayed across the store app.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-banner-btn, .card-actions a[href*="/banners/create"]',
                    popover: {
                        title: 'Create Banner',
                        description: 'Click Create Banner to design a new promotional banner.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: '#banner-visibility',
                    popover: {
                        title: 'Select the scope whether you want to display in main page or in a specific category page and if you want it to be displayed in all zones',
                        description: 'Click Create Banner to design a new promotional banner.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: '#basic-banner-info',
                    popover: {
                        title: 'Banner Information',
                        description: 'Enter a all the basic info such as title, type, position etc.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Publish Banner',
                        description: 'Click Save to publish the banner onto your home screen.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
