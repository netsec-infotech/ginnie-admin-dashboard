/**
 * Seller Store Setup Guided Tour Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        seller_store_setup: {
            url: getBasePath() + '/seller/stores',
            validPaths: ['/seller/stores', '/seller/stores/create'],
            filterSteps: function(steps, currentPath) {
                const isFormPage = currentPath.endsWith('/seller/stores/create');
                if (isFormPage) {
                    return steps.filter(step => {
                        const el = step.element || '';
                        return !el.includes('stores-table') && !el.includes('add-store-btn');
                    });
                } else {
                    return steps.filter(step => {
                        const el = step.element || '';
                        return el.includes('stores-table') || el.includes('add-store-btn');
                    });
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/seller/stores')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    var addLink = document.querySelector('#add-store-btn, .card-actions a[href*="/stores/create"]');
                    window.location.href = addLink ? addLink.href : (getBasePath() + '/seller/stores/create');
                } else {
                    localStorage.removeItem('active_seller_tour');
                }
            },
            steps: [
                {
                    element: '#stores-table_wrapper, #stores-table, .card-table',
                    popover: {
                        title: 'My Stores List',
                        description: 'Here you can view all your registered store outlets and their verification status.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-store-btn, .card-actions a[href*="/stores/create"]',
                    popover: {
                        title: 'Add New Store',
                        description: 'Click Add New Store to open the store registration form.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: '#pills-basic',
                    popover: {
                        title: 'Enter basic information',
                        description: 'Enter your store name,contact phone number, and email.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#pills-location',
                    popover: {
                        title: 'Enter store location information',
                        description: 'Enter details regarding store location such as country, address, landmark etc. Use the interactive google map to pinpoint your location',
                        side: 'bottom',
                        align: 'center'
                    }
                },
                {
                    element: '#pills-logos',
                    popover: {
                        title: 'Select Logo and banner',
                        description: 'Pick the logo and banner for your store',
                        side: 'bottom',
                        align: 'center'
                    }
                },
                {
                    element: '#pills-documents',
                    popover: {
                        title: 'Enter Business details',
                        description: 'Upload documents such as address proof, voided check and enter tax related details.',
                        side: 'bottom',
                        align: 'center'
                    }
                },
                {
                    element: '#pills-bank',
                    popover: {
                        title: 'Enter Bank Details',
                        description: 'Enter Bank details such as bank name, branch code, account number, account type etc.',
                        side: 'bottom',
                        align: 'center'
                    }
                },
                {
                    element: '#pills-seo',
                    popover: {
                        title: 'Enter SEO Information',
                        description: 'Enter seo title, description and keywords.',
                        side: 'bottom',
                        align: 'center'
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Create Store',
                        description: 'Click Create Store to submit your store registration.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
