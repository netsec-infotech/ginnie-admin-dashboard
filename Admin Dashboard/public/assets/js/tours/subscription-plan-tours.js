/**
 * Subscription Plan Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_subscription_plans: {
            url: getBasePath() + '/admin/subscription-plans',
            validPaths: ['/admin/subscription-plans', '/admin/subscription-plans/create'],
            filterSteps: function(steps, currentPath) {
                const isFormPage = currentPath.endsWith('/admin/subscription-plans/create');
                if (isFormPage) {
                    return steps.filter(step => !step.element.includes('subscription-plans-table') && !step.element.includes('add-plan-btn'));
                } else {
                    return steps.filter(step => step.element.includes('subscription-plans-table') || step.element.includes('add-plan-btn'));
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/admin/subscription-plans')) {
                    localStorage.setItem('active_admin_tour', tourName);
                    var addLink = document.querySelector('#add-plan-btn, .card-actions a[href*="/subscription-plans/create"]');
                    window.location.href = addLink ? addLink.href : (getBasePath() + '/admin/subscription-plans/create');
                } else {
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    element: '#subscription-plans-table_wrapper, #subscription-plans-table, .card-table',
                    popover: {
                        title: 'List of Subscription Plans',
                        description: 'Here you can view all seller tier membership plans and prices.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-plan-btn, .card-actions a[href*="/subscription-plans/create"]',
                    popover: {
                        title: 'Add Subscription Plan',
                        description: 'Click Add Plan to configure a new membership package.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Form →'
                    }
                },
                {
                    element: '#basic-subscription-details',
                    popover: {
                        title: 'Enter Details',
                        description: 'Enter plan name, price, and billing cycle duration.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '#plan-configurations',
                    popover: {
                        title: 'Configure Plan',
                        description: 'Check and set the plan configurations from here',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Save Subscription Plan',
                        description: 'Click Save to register and enable the subscription plan for seller signups.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
