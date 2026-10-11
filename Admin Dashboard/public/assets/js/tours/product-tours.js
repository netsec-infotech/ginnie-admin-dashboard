/**
 * Product Management Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_product: {
            url: getBasePath() + '/admin/products',
            validPaths: ['/admin/products', '/admin/products/create'],
            filterSteps: function(steps, currentPath) {
                const isFormPage = currentPath.endsWith('/admin/products/create');
                if (isFormPage) {
                    return steps.filter(step => !step.element.includes('products-table') && !step.element.includes('add-product-btn'));
                } else {
                    return steps.filter(step => step.element.includes('products-table') || step.element.includes('add-product-btn'));
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/admin/products')) {
                    localStorage.setItem('active_admin_tour', tourName);
                    var addLink = document.querySelector('#add-product-btn, .card-actions a[href*="/products/create"]');
                    window.location.href = addLink ? addLink.href : (getBasePath() + '/admin/products/create');
                } else {
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    element: '#products-table_wrapper, #products-table, .card-table',
                    popover: {
                        title: 'List of Products',
                        description: 'Here you can see the list of all products in the store catalog.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-product-btn, .card-actions a[href*="/products/create"]',
                    popover: {
                        title: 'Add Product',
                        description: 'Click on the Add New Product button to open the product registration form.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: '#product-category',
                    popover: {
                        title: 'Select Category',
                        description: 'Select category from the list or you can also search for a category.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#product-info',
                    popover: {
                        title: 'Enter basic information',
                        description: 'Enter the basic information of the product.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#product-additional-info',
                    popover: {
                        title: 'Enter additional Information',
                        description: 'Enter the additional information of the product such as brand, country of origin, preparartion time etc.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#product-variations',
                    popover: {
                        title: 'Enter Product Variants',
                        description: 'Enter the information about the different variants of the product.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#product-images',
                    popover: {
                        title: 'Select Product images',
                        description: 'Select the images for the product.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#product-policy-features',
                    popover: {
                        title: 'Enter Product Policies',
                        description: 'Enter the information about the different product related policies like order quantitiy, return and cancellation policies etc.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#product-pricing-taxes',
                    popover: {
                        title: 'Enter Product Pricing',
                        description: 'Enter pricing of the product and also select the tax group which is applicable for this and select store where this product should be displayed as well.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Save Product',
                        description: 'Click Save to register and publish the new product into the catalog.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
