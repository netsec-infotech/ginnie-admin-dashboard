/**
 * Seller Wallet & Withdrawals Guided Tour Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        seller_wallet_setup: {
            url: getBasePath() + '/seller/wallet',
            validPaths: [
                '/seller/wallet',
                '/seller/wallet/transactions',
                '/seller/withdrawals',
                '/seller/withdrawals/history'
            ],
            filterSteps: function(steps, currentPath) {
                if (currentPath.endsWith('/seller/wallet/transactions')) {
                    return steps.filter(step => step.stepGroup === 'transactions');
                } else if (currentPath.endsWith('/seller/withdrawals/history')) {
                    return steps.filter(step => step.stepGroup === 'withdrawals_history');
                } else if (currentPath.endsWith('/seller/withdrawals')) {
                    return steps.filter(step => step.stepGroup === 'withdrawals');
                } else {
                    return steps.filter(step => step.stepGroup === 'wallet' || !step.stepGroup);
                }
            },
            onTourDestroy: function(tourName, currentPath) {
                if (currentPath.endsWith('/seller/withdrawals/history')) {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                } else if (currentPath.endsWith('/seller/withdrawals')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    window.location.href = getBasePath() + '/seller/withdrawals/history';
                } else if (currentPath.endsWith('/seller/wallet/transactions')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    window.location.href = getBasePath() + '/seller/withdrawals';
                } else if (currentPath.endsWith('/seller/wallet')) {
                    localStorage.setItem('active_seller_tour', tourName);
                    localStorage.setItem('active_admin_tour', tourName);
                    window.location.href = getBasePath() + '/seller/wallet/transactions';
                } else {
                    localStorage.removeItem('active_seller_tour');
                    localStorage.removeItem('active_admin_tour');
                }
            },
            steps: [
                {
                    stepGroup: 'wallet',
                    element: '#wallet-balance-summary',
                    popover: {
                        title: 'Wallet Balance Summary',
                        description: 'View your total available store wallet balance, basic information and also option to request withdrawal.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    stepGroup: 'wallet',
                    element: '#wallet-transactions-btn',
                    popover: {
                        title: 'Transaction History Button',
                        description: 'Click to view detailed transaction history for all orders and payouts.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Transactions →'
                    }
                },
                {
                    stepGroup: 'transactions',
                    element: '#transactions-table',
                    popover: {
                        title: 'Transaction Logs',
                        description: 'Here you have all the transaction history of your wallet like order debits and withdrawal requests.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Withdrawals →'
                    }
                },
                {
                    stepGroup: 'withdrawals',
                    element: '#withdrawal-page-1',
                    popover: {
                        title: 'Deposit & Withdrawal Requests',
                        description: 'Submit withdrawal payout requests or add money to your store wallet.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    stepGroup: 'withdrawals',
                    element: '#withdrawal-history-btn',
                    popover: {
                        title: 'Withdrawal History Button',
                        description: 'Click to view detailed withdrawal requests history.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Go to Withdrawal History →'
                    }
                },
                {
                    stepGroup: 'withdrawals_history',
                    element: '#withdrawal-page-2',
                    popover: {
                        title: 'Withdrawal History Table',
                        description: 'Here you have all the withdrawal requests for your wallet.',
                        side: 'bottom',
                        align: 'center',
                        doneBtnText: 'Finish Tour ✓'
                    }
                }
            ]
        }
    });
})();
