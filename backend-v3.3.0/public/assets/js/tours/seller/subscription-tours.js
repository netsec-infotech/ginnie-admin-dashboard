/**
 * Seller Subscription & Usage Guided Tour Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        seller_subscription_plans: {
            url: getBasePath() + '/seller/subscription-plans',
            validPaths: [
                '/seller/subscription-plans',
                '/seller/subscription-plans/current',
                '/seller/subscription-plans/history'
            ],
            filterSteps: function(steps, currentPath) {
                if (currentPath.endsWith('/seller/subscription-plans/current')) {
                    return steps.filter(step => step.stepGroup === 'current');
                } else if (currentPath.endsWith('/seller/subscription-plans/history')) {
                    return steps.filter(step => step.stepGroup === 'history');
                } else {
                    return steps.filter(step => step.stepGroup === 'index' || !step.stepGroup);
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/seller/subscription-plans/history')) {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                } else if (currentPath.endsWith('/seller/subscription-plans/current')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    window.location.href = getBasePath() + '/seller/subscription-plans/history';
                } else {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    window.location.href = getBasePath() + '/seller/subscription-plans/current';
                }
            },
            steps: [
                {
                    stepGroup: 'index',
                    element: '#subscription-index-target',
                    popover: {
                        title: 'Subscription Plans & Tiers',
                        description: 'Explore available seller subscription plans, pricing options, and feature limits.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Current Subscription →'
                    }
                },
                {
                    stepGroup: 'current',
                    element: '#subscription-target-1',
                    popover: {
                        title: 'Current Active Subscription',
                        description: 'View details of your active plan, expiration date, and feature usage breakdown.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Subscription History →'
                    }
                },
                {
                    stepGroup: 'history',
                    element: '#subscription-history-table, .card-table, .card',
                    popover: {
                        title: 'Subscription Purchase History',
                        description: 'Check transaction logs and invoices for all previous subscription purchases.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Finish Tour ✓'
                    }
                }
            ]
        }
    });
})();
