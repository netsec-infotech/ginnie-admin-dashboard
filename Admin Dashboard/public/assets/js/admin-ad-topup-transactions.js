'use strict';

$(document).ready(function () {
    const table = $('#admin-ad-topup-transactions-table').DataTable();
    window.__adTopupTableReload = function () {
        table.ajax.reload(null, false);
    };
    const initialSeller = window.adTopupConfig?.initialSeller ?? null;
    window.initAdTopupSellerTomSelect?.('transactionSellerFilter', function () {
        table.ajax.reload(null, false);
    }, initialSeller);

    $('#transactionPaymentMethodFilter').on('change', function () {
        table.ajax.reload(null, false);
    });

    table.on('preXhr.dt', function (e, settings, data) {
        data.seller_id = $('#transactionSellerFilter').val();
        data.payment_method = $('#transactionPaymentMethodFilter').val();
    });

    $('#transactionsRefresh').on('click', function () {
        table.ajax.reload(null, false);
    });
});
