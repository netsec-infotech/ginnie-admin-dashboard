/**
 * System Settings Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        configure_payment_options: {
            url: getBasePath() + '/admin/settings/payment',
            steps: [
                {
                    element: '#pills, .sticky-top',
                    popover: {
                        title: 'Payment Settings Navigation',
                        description: 'Use this left menu to navigate through various Payment options.',
                        side: 'right'
                    }
                },
                {
                    element: '#pills-stripe',
                    popover: {
                        title: 'Stripe Payment',
                        description: 'Configure Stripe payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-stripe"]');
                        if (link) link.click();
                    }
                },
                {
                    element: '#pills-razorpay',
                    popover: {
                        title: 'Razorpay Payment',
                        description: 'Configure Razorpay payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-razorpay"]');
                        if (link) link.click();
                    }
                },
                {
                    element: '#pills-paystack',
                    popover: {
                        title: 'Paystack Payment',
                        description: 'Configure Paystack payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-paystack"]');
                        if (link) link.click();
                    }
                },
                {
                    element: '#pills-flutterwave',
                    popover: {
                        title: 'Flutterwave Payment',
                        description: 'Configure Flutterwave payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-flutterwave"]');
                        if (link) link.click();
                    }
                },
                {
                    element: '#pills-xendit',
                    popover: {
                        title: 'Xendit Payment',
                        description: 'Configure Xendit payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-xendit"]');
                        if (link) link.click();
                    }
                },
                {
                    element: '#pills-wallet',
                    popover: {
                        title: 'Wallet Payment',
                        description: 'Configure Wallet payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-wallet"]');
                        if (link) link.click();
                    }
                },
                {
                    element: '#pills-cod',
                    popover: {
                        title: 'Cash on Delivery',
                        description: 'Configure Cash on Delivery payment settings.',
                        side: 'top'
                    },
                    onHighlightStarted: function() {
                        const link = document.querySelector('a[href="#pills-referral"]');
                        if (link) link.click();
                    }
                },
                {
                    element: 'form.form-submit button[type="submit"]',
                    popover: {
                        title: 'Save Payment Settings',
                        description: 'Click Save Settings to apply and update all your payment configurations.',
                        side: 'top'
                    }
                }
            ]
        }
    });
})();
