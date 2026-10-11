/**
 * Seller Ad Campaigns Guided Tour Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        seller_ad_campaigns: {
            url: getBasePath() + '/seller/ads/campaigns/dashboard',
            validPaths: [
                '/seller/ads/campaigns/dashboard',
                '/seller/ads/campaigns',
                '/seller/ads/campaigns/create'
            ],
            filterSteps: function(steps, currentPath) {
                if (currentPath.endsWith('/seller/ads/campaigns/create')) {
                    return steps.filter(step => step.stepGroup === 'create');
                } else if (currentPath.endsWith('/seller/ads/campaigns')) {
                    return steps.filter(step => step.stepGroup === 'index');
                } else {
                    return steps.filter(step => step.stepGroup === 'dashboard' || !step.stepGroup);
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/seller/ads/campaigns/create')) {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                } else if (currentPath.endsWith('/seller/ads/campaigns')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    var createLink = document.querySelector('#create-campaign-btn, a[href*="/ads/campaigns/create"]');
                    window.location.href = createLink ? createLink.href : (getBasePath() + '/seller/ads/campaigns/create');
                } else if (currentPath.endsWith('/seller/ads/campaigns/dashboard')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    window.location.href = getBasePath() + '/seller/ads/campaigns';
                } else {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    stepGroup: 'dashboard',
                    element: '#ad-dashboard, .row-cards, .card',
                    popover: {
                        title: 'Ad Campaigns Dashboard',
                        description: 'View overall advertising metrics, impressions, clicks, spent budget, and wallet overview.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Go to Ad Campaigns →'
                    }
                },
                {
                    stepGroup: 'index',
                    element: '#campaigns-table_wrapper, #campaigns-table, .card-table',
                    popover: {
                        title: 'Ad Campaigns List',
                        description: 'View all sponsored campaigns, status, impressions, and click analytics.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    stepGroup: 'index',
                    element: '#create-campaign-btn, a[href*="/ads/campaigns/create"]',
                    popover: {
                        title: 'Create Ad Campaign',
                        description: 'Click Create Ad Campaign to set up product promotions and featured ad placements.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Create Campaign Form →'
                    }
                },
                {
                    stepGroup: 'create',
                    element: 'input[name="title"]',
                    popover: {
                        title: 'Campaign Title',
                        description: 'Enter campaign title and select advertising objective.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: 'input[name="budget"], input[name="daily_budget"]',
                    popover: {
                        title: 'Campaign Budget & Schedule',
                        description: 'Set daily or total promotional budget and campaign start/end dates.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    stepGroup: 'create',
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Launch Campaign',
                        description: 'Click Launch Campaign to activate your advertisement.',
                        side: 'top',
                        align: 'center',
                        doneBtnText: 'Finish Tour ✓'
                    }
                }
            ]
        }
    });
})();
