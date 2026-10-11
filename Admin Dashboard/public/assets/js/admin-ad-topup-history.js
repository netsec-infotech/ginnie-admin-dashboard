'use strict';

$(document).ready(function () {
    const table = $('#admin-ad-topup-history-table').DataTable();
    const initialSeller = window.adTopupHistoryConfig?.initialSeller ?? null;

    window.initAdTopupSellerTomSelect?.('transactionSellerFilter', function () {
        table.ajax.reload(null, false);
    }, initialSeller);

    $('#transactionStatusFilter, #transactionPaymentMethodFilter').on('change', function () {
        table.ajax.reload(null, false);
    });

    table.on('preXhr.dt', function (e, settings, data) {
        data.seller_id = $('#transactionSellerFilter').val();
        data.status = $('#transactionStatusFilter').val();
        data.payment_method = $('#transactionPaymentMethodFilter').val();
    });

    $('#transactionsRefresh').on('click', function () {
        table.ajax.reload(null, false);
    });
});
