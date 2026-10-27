/**
 * Category Management Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_category: {
            url: getBasePath() + '/admin/categories',
            validPaths: ['/admin/categories', '/admin/categories/create'],
            filterSteps: function(steps, currentPath) {
                const isFormPage = currentPath.endsWith('/admin/categories/create');
                if (isFormPage) {
                    return steps.filter(step => !step.element.includes('categories-table') && !step.element.includes('add-category-btn'));
                } else {
                    return steps.filter(step => step.element.includes('categories-table') || step.element.includes('add-category-btn'));
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/admin/categories')) {
                    localStorage.setItem('active_admin_tour', tourName);
                    var addLink = document.querySelector('#add-category-btn, .card-actions a[href*="/categories/create"]');
                    window.location.href = addLink ? addLink.href : (getBasePath() + '/admin/categories/create');
                } else {
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    element: '#categories-table_wrapper, #categories-table, .card-table',
                    popover: {
                        title: 'List of Categories',
                        description: 'Here you can see the list of all registered product categories and sub-categories.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-category-btn, .card-actions a[href*="/categories/create"]',
                    popover: {
                        title: 'Add Category',
                        description: 'Click on the Add Category button to open the category creation form.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: 'input[name="title"]',
                    popover: {
                        title: 'Category Name',
                        description: 'Enter the category title (e.g. Fresh Vegetables, Electronics).',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: 'textarea[name="description"]',
                    popover: {
                        title: 'Category Description',
                        description: 'Provide a brief summary explaining what products belong in this category.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#select-parent-category',
                    popover: {
                        title: 'Select Parent Category',
                        description: 'Select the parent category if you want to create a sub-category.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#category-details',
                    popover: {
                        title: 'Category Details',
                        description: 'Provide all the other category details such as labels, photos, appearance.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#category-settings',
                    popover: {
                        title: 'Category Settings',
                        description: 'Set the commission and SEO fields for this category',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Save Category',
                        description: 'Click this button to save and publish your new category into the store catalog.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
