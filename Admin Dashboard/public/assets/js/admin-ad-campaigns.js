'use strict';

$(document).ready(function () {

    let table = $('#ad-campaigns-table').DataTable();
    let actionUrl = '';

    $('#statusFilter').on('change', function () {
        table.ajax.reload(null, false);
    });

    table.on('preXhr.dt', function (e, settings, data) {
        data.status = $('#statusFilter').val();
    });

    $(document).on('click', '.btn-campaign-approve', function () {
        const url = this.dataset.url;
        Swal.fire({
            title: 'Approve Campaign?',
            text: 'The campaign will immediately start running.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Approve',
            confirmButtonColor: '#2fb344',
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            campaignAction(url, { action: 'approve' }, function () {
                table.ajax.reload(null, false);
            });
        });
    });

    $('#forceStopModal, #rejectModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        actionUrl = button.data('url');
    });

    $(document).on('click', '.btn-campaign-reject', function () {
        const rejectReason = document.getElementById('rejectReason');
        if (rejectReason) {
            rejectReason.value = '';
            rejectReason.classList.remove('is-invalid');
        }
    });

    document.getElementById('confirmRejectBtn')?.addEventListener('click', function () {
        const rejectReason = document.getElementById('rejectReason');
        const reason = rejectReason.value.trim();
        if (!reason) {
            rejectReason.classList.add('is-invalid');
            return;
        }
        rejectReason.classList.remove('is-invalid');

        campaignAction(actionUrl, { action: 'reject', reason: reason }, function () {
            $('#rejectModal').modal('hide');
            table.ajax.reload(null, false);
        });
    });

    $(document).on('click', '.btn-campaign-force-stop', function () {
        const forceStopReason = document.getElementById('forceStopReason');
        if (forceStopReason) {
            forceStopReason.value = '';
            forceStopReason.classList.remove('is-invalid');
        }
    });

    document.getElementById('confirmForceStopBtn')?.addEventListener('click', function () {
        const forceStopReason = document.getElementById('forceStopReason');
        const reason = forceStopReason.value.trim();
        if (!reason) {
            forceStopReason.classList.add('is-invalid');
            return;
        }
        forceStopReason.classList.remove('is-invalid');

        campaignAction(actionUrl, { action: 'force_stop', reason: reason }, function () {
            $('#forceStopModal').modal('hide');
            table.ajax.reload(null, false);
        });
    });
});

function campaignAction(url, payload, onSuccess) {
    axios.post(url, payload, {
        headers: { 'X-CSRF-TOKEN': csrfToken },
    }).then(function (res) {
        const data = res.data;
        if (data.success) {
            Toast.fire({ icon: 'success', title: data.message });
            if (onSuccess) {
                onSuccess();
            }
        } else {
            Toast.fire({ icon: 'error', title: data.message });
        }
    }).catch(function () {
        Toast.fire({ icon: 'error', title: 'Something went wrong.' });
    });
}
