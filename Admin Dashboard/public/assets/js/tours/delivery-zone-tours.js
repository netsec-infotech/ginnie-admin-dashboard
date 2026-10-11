/**
 * Delivery Zone Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_delivery_zone: {
            url: getBasePath() + '/admin/delivery-zones',
            validPaths: ['/admin/delivery-zones', '/admin/delivery-zones/create'],
            filterSteps: function(steps, currentPath) {
                const isFormPage = currentPath.endsWith('/admin/delivery-zones/create');
                if (isFormPage) {
                    return steps.filter(step => !step.element.includes('delivery-zones-table') && !step.element.includes('add-delivery-zone-btn'));
                } else {
                    return steps.filter(step => step.element.includes('delivery-zones-table') || step.element.includes('add-delivery-zone-btn'));
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/admin/delivery-zones')) {
                    localStorage.setItem('active_admin_tour', tourName);
                    var addLink = document.querySelector('#add-delivery-zone-btn, .card-actions a[href*="/delivery-zones/create"]');
                    window.location.href = addLink ? addLink.href : (getBasePath() + '/admin/delivery-zones/create');
                } else {
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    element: '#delivery-zones-table_wrapper, #delivery-zones-table, .card-table',
                    popover: {
                        title: 'List of Delivery Zones',
                        description: 'Here you can see the list of all active and configured delivery zones in the system.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-delivery-zone-btn, .card-actions a[href*="/delivery-zones/create"]',
                    popover: {
                        title: 'Add Delivery Zone',
                        description: 'Click on the Add Delivery Zone button to open the map and zone creation form.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: '#draw-zone-btn, .zone-toolbar',
                    popover: {
                        title: 'Draw Zone Toolbar',
                        description: 'Click <strong>Draw Zone</strong> to begin placing polygon points on the map for your delivery area.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#map',
                    popover: {
                        title: 'Interactive Map Boundary',
                        description: 'Use the interactive Google Map to draw or search locations and set exact zone boundaries.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#zone-name',
                    popover: {
                        title: 'Zone Name',
                        description: 'Provide a unique and descriptive name for this delivery zone (e.g. Downtown Sector 1).',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#delivery-zone-details',
                    popover: {
                        title: 'Delivery Charges & Buffer Time',
                        description: 'Configure free delivery thresholds, buffer times, and delivery charges.',
                        side: 'top',
                        align: 'start'
                    }
                },
                {
                    element: '#delivery-boy-earnings',
                    popover: {
                        title: 'Delivery Boy earning configurations',
                        description: 'Configure base fee, store pickup free, etc which will be earned by the rider per order.',
                        side: 'top',
                        align: 'start'
                    }
                },
                {
                    element: '#status',
                    popover: {
                        title: 'Status',
                        description: 'Make this delivery zone active or inactive based on your requirements.',
                        side: 'top',
                        align: 'start'
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Save Delivery Zone',
                        description: 'Click Save to register and publish the new delivery zone into the system.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
