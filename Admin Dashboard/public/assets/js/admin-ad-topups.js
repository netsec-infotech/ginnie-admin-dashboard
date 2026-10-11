'use strict';

$(document).ready(function () {
    const table = $('#admin-ad-topups-table').DataTable();
    window.initAdTopupSellerTomSelect?.('sellerFilter', function () {
        table.ajax.reload(null, false);
    });

    $('#activityFilter').on('change', function () {
        table.ajax.reload(null, false);
    });

    table.on('preXhr.dt', function (e, settings, data) {
        data.seller_id = $('#sellerFilter').val();
        data.activity_filter = $('#activityFilter').val();
    });

    $('#refresh').on('click', function () {
        table.ajax.reload(null, false);
    });
});
