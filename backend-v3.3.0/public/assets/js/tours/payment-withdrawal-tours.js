/**
 * Payments and Withdrawals Guided Tours Module
 */
(function () {
    "use strict";

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        settle_seller_payment: {
            url: getBasePath() + "/admin/commissions",
            validPaths: ["/admin/commissions"],
            steps: [
                {
                    element:
                        ".row-cards:has(#insight-total-earnings), .row.row-cards:first-of-type, #insight-total-earnings",
                    popover: {
                        title: "Settlement Metrics Overview",
                        description:
                            "Monitor Total Earnings, Admin Platform Commission, Paid Settlements, and Pending Settlements in real time.",
                        side: "bottom",
                        align: "center",
                    },
                },
                {
                    element: "#storeFilter, select#storeFilter",
                    popover: {
                        title: "Filter by Store",
                        description:
                            "Filter pending payouts and return deductions for a specific seller store.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element:
                        'ul.nav-tabs a[href="#tab-credits"], #tab-credits',
                    popover: {
                        title: "Seller Payouts (Credits)",
                        description:
                            "View all delivered order commissions ready to be settled into seller balances.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element:
                        "#settle-all-btn, table#unsettled-commissions-table, .card-table",
                    popover: {
                        title: "Settle Seller Payments",
                        description:
                            "Click Settle All to batch-process all pending vendor commissions, or settle individual orders directly from the table actions.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        'ul.nav-tabs a[href="#tab-debits"], #tab-debits',
                    popover: {
                        title: "Returns & Deductions (Debits)",
                        description:
                            "Switch to the Debits tab to inspect return deductions and clawbacks.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element: 'a[href*="/commissions/history"]',
                    popover: {
                        title: "Settlement History",
                        description:
                            "Access complete historical logs and payout archives for all past settled transactions.",
                        side: "bottom",
                        align: "start",
                    },
                },
            ],
        },
        approve_seller_withdrawals: {
            url: getBasePath() + "/admin/seller-withdrawals",
            validPaths: ["/admin/seller-withdrawals"],
            steps: [
                {
                    element:
                        "#seller-withdrawals-table_wrapper, #seller-withdrawals-table, .card",
                    popover: {
                        title: "Pending Seller Withdrawals",
                        description:
                            "View incoming wallet payout requests from sellers, including requested amounts and timestamps.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element: "select#sellerSearch, #sellerSearch",
                    popover: {
                        title: "Search by Seller",
                        description:
                            "Quickly filter withdrawal requests submitted by a specific vendor.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element:
                        "#seller-withdrawals-table tbody tr:first-child, #seller-withdrawals-table, .card-table",
                    popover: {
                        title: "Process Withdrawal Requests",
                        description:
                            "Click the action button on any request to view seller bank details, then Approve (confirm bank transfer) or Reject (refund to wallet with a reason).",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element: 'a[href*="/seller-withdrawals/history"]',
                    popover: {
                        title: "Withdrawal History",
                        description:
                            "Access complete history logs of all approved and rejected seller withdrawals.",
                        side: "bottom",
                        align: "start",
                    },
                },
            ],
        },
        settle_rider_payment: {
            url: getBasePath() + "/admin/delivery-boy-earnings",
            validPaths: ["/admin/delivery-boy-earnings"],
            steps: [
                {
                    element:
                        "#delivery-boy-earnings-table_wrapper, #delivery-boy-earnings-table, .card",
                    popover: {
                        title: "Rider Earnings & Pending Payouts",
                        description:
                            "View delivery partners with accumulated delivery fees, tips, and referral bonuses waiting for payout.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        "select#deliveryBoySearch, #deliveryBoySearch",
                    popover: {
                        title: "Search by Delivery Partner",
                        description:
                            "Filter pending earnings and balances for a specific delivery partner.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element:
                        "#delivery-boy-earnings-table tbody tr:first-child, #delivery-boy-earnings-table, .card-table",
                    popover: {
                        title: "Process Rider Payout",
                        description:
                            "Click the Pay button to open the payout modal, confirm payout amounts, and record the payment transfer.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        'a[href*="/delivery-boy-earnings/history"]',
                    popover: {
                        title: "Rider Payment History",
                        description:
                            "View completed payout archives with reference IDs and transfer receipts.",
                        side: "bottom",
                        align: "start",
                    },
                },
            ],
        },
        approve_reject_rider_withdrawal: {
            url: getBasePath() + "/admin/delivery-boy-withdrawals",
            validPaths: ["/admin/delivery-boy-withdrawals"],
            steps: [
                {
                    element:
                        "#delivery-boy-withdrawals-table_wrapper, #delivery-boy-withdrawals-table, .card",
                    popover: {
                        title: "Pending Rider Withdrawals",
                        description:
                            "View on-demand cash-out requests created by riders from their mobile app wallet.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        "select#deliveryBoySearch, #deliveryBoySearch",
                    popover: {
                        title: "Filter by Rider",
                        description:
                            "Filter withdrawal requests for a specific delivery partner.",
                        side: "bottom",
                        align: "start",
                    },
                },
                {
                    element:
                        "#delivery-boy-withdrawals-table tbody tr:first-child, #delivery-boy-withdrawals-table, .card-table",
                    popover: {
                        title: "Review & Action Withdrawal",
                        description:
                            "Review rider bank account / UPI details and click to Approve payout or Reject with feedback.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        'a[href*="/delivery-boy-withdrawals/history"]',
                    popover: {
                        title: "Rider Withdrawal History",
                        description:
                            "Audit historical withdrawal approvals and payment references.",
                        side: "bottom",
                        align: "start",
                    },
                },
            ],
        },
        approve_customer_withdrawals: {
            url: getBasePath() + "/admin/wallet/deposits",
            validPaths: ["/admin/wallet/deposits"],
            steps: [
                {
                    element:
                        "#admin-wallet-deposits-table_wrapper, #admin-wallet-deposits-table, .card",
                    popover: {
                        title: "Pending Customer Wallet Deposits & Claims",
                        description:
                            "Review customer offline bank deposits and manual wallet refund claims awaiting verification.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        "#admin-wallet-deposits-table tbody tr:first-child, #admin-wallet-deposits-table, .card-table",
                    popover: {
                        title: "Verify & Approve / Reject",
                        description:
                            "Click Complete to verify the payment receipt and credit the funds into the customer wallet, or Reject invalid submissions.",
                        side: "top",
                        align: "center",
                    },
                },
                {
                    element:
                        'a[href*="/wallet/transactions"], .page-header',
                    popover: {
                        title: "Customer Wallet Ledger",
                        description:
                            "Access the universal customer wallet transactions ledger (/admin/wallet/transactions) to audit all user credits, debits, and orders.",
                        side: "bottom",
                        align: "start",
                    },
                },
            ],
        },
    });
})();
