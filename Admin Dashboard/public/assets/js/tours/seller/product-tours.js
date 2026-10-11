/**
 * Seller Products Management Guided Tour Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        seller_products_management: {
            url: getBasePath() + '/seller/products',
            validPaths: [
                '/seller/products',
                '/seller/products/bulk-upload',
                '/seller/products/create',
                '/seller/product-faqs',
                '/seller/addon-groups',
                '/seller/product-addons',
                '/seller/store-addon-items'
            ],
            filterSteps: function(steps, currentPath) {
                if (currentPath.endsWith('/seller/products/bulk-upload')) {
                    return steps.filter(step => step.stepGroup === 'bulk-upload');
                } else if (currentPath.endsWith('/seller/products/create')) {
                    return steps.filter(step => step.stepGroup === 'create');
                } else {
                    return steps.filter(step => step.stepGroup === 'index' || !step.stepGroup);
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/seller/products/create')) {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                } else if (currentPath.endsWith('/seller/products/bulk-upload')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    var createLink = document.querySelector('#add-product-btn, a[href*="/products/create"]');
                    window.location.href = createLink ? createLink.href : (getBasePath() + '/seller/products/create');
                } else if (currentPath.endsWith('/seller/products')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    var bulkLink = document.querySelector('#bulk-upload-btn, a[href*="/products/bulk-upload"]');
                    window.location.href = bulkLink ? bulkLink.href : (getBasePath() + '/seller/products/bulk-upload');
                } else {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    stepGroup: 'index',
                    element: '#products-table_wrapper, #products-table, .card-table',
                    popover: {
                        title: 'Product Catalog Table',
                        description: 'View all active product listings, stock status, pricing, and visibility controls.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    stepGroup: 'index',
                    element: '#bulk-upload-btn, a[href*="/products/bulk-upload"]',
                    popover: {
                        title: 'Bulk Upload Products',
                        description: 'Download bulk product templates and import catalog items via CSV/Excel.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Bulk Upload →'
                    }
                },
                {
                    stepGroup: 'bulk-upload',
                    element: '#bulk-upload-target, .card-body',
                    popover: {
                        title: 'Bulk Upload Page',
                        description: 'Download CSV templates, read instructions, and upload catalog files or image ZIPs.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Go to Add Product Form →'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-category',
                    popover: {
                        title: 'Select Category',
                        description: 'Select category from the list or search for a category.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-info',
                    popover: {
                        title: 'Enter basic information',
                        description: 'Enter the basic information of the product.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-additional-info',
                    popover: {
                        title: 'Enter additional Information',
                        description: 'Enter additional product details such as brand, country of origin, and prep time.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-variations',
                    popover: {
                        title: 'Enter Product Variants',
                        description: 'Enter information about different product variants.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-images',
                    popover: {
                        title: 'Select Product images',
                        description: 'Select images for the product.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-policy-features',
                    popover: {
                        title: 'Enter Product Policies',
                        description: 'Enter product policies like minimum order quantity, return, and cancellation policies.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: '#product-pricing-taxes',
                    popover: {
                        title: 'Enter Product Pricing',
                        description: 'Enter product pricing, select applicable tax group, and choose store availability.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Save Product',
                        description: 'Click Save to register and publish the new product into the catalog.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Finish Tour ✓'
                    }
                }
            ]
        }
    });
})();
