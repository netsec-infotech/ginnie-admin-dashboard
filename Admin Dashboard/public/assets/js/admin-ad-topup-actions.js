'use strict';

$(document).ready(function () {
    const processBaseUrl = window.adTopupConfig?.processBaseUrl || '';
    const csrfTokenValue = window.adTopupConfig?.csrfToken || '';
    let processingId = null;

    window.setAdTopupModalData = function (payload) {
        processingId = payload?.id || null;
        const sellerName = payload?.sellerName || '';
        const amount = payload?.amount || '';

        $('#approve-deposit-user').text(sellerName);
        $('#approve-deposit-amount').text(amount);
        $('#reject-deposit-user').text(sellerName);
        $('#reject-deposit-amount').text(amount);
    };

    $('#approveDepositModal, #rejectDepositModal').on('hidden.bs.modal', function () {
        $('#approve-deposit-user, #approve-deposit-amount, #reject-deposit-user, #reject-deposit-amount').text('');
        processingId = null;
    });

    window.submitAdTopupAction = async function (action) {
        if (!processingId) {
            Toast.fire({ icon: 'error', title: 'Topup data is missing.' });
            return;
        }

        try {
            const response = await fetch(`${processBaseUrl}/${processingId}/process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfTokenValue,
                },
                body: JSON.stringify({ action }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                Toast.fire({ icon: 'error', title: data.message });
                return;
            }

            Toast.fire({ icon: 'success', title: data.message });
            $('#approveDepositModal, #rejectDepositModal').modal('hide');

            if (window.__adTopupTableReload) {
                window.__adTopupTableReload();
            }
        } catch (error) {
            console.error(error);
            Toast.fire({ icon: 'error', title: 'Action failed' });
        }
    };
});
