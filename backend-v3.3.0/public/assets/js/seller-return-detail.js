/* global axios, Toast */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    let currentReturnId = null;

    const acceptModalEl = document.getElementById('acceptModel');
    const rejectModalEl = document.getElementById('rejectModel');
    const storeConfirmModalEl = document.getElementById('storeConfirmModel');
    const storeEscalateModalEl = document.getElementById('storeEscalateModel');

    [acceptModalEl, rejectModalEl, storeConfirmModalEl, storeEscalateModalEl].forEach(function (modalEl) {
        if (!modalEl) {
            return;
        }
        modalEl.addEventListener('show.bs.modal', function (event) {
            currentReturnId = event.relatedTarget?.dataset?.id || null;
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            const noteFields = modalEl.querySelectorAll('textarea');
            noteFields.forEach(function (field) {
                field.value = '';
            });
            const inputFiles = modalEl.querySelectorAll('input[type="file"]');
            inputFiles.forEach(function (field) {
                field.value = '';
            });
            const selects = modalEl.querySelectorAll('select');
            selects.forEach(function (field) {
                field.selectedIndex = 0;
            });
        });
    });

    function submitDecision(action, comment) {
        if (!currentReturnId) {
            return;
        }
        axios.post('/seller/order-returns/' + currentReturnId + '/decision', {
            action: action,
            seller_comment: comment,
        }).then(function (response) {
            const data = response.data;
            if (data.success === false) {
                Toast.fire({ icon: 'error', title: data.message });
                return;
            }
            Toast.fire({ icon: 'success', title: data.message });
            setTimeout(function () { window.location.reload(); }, 1000);
        }).catch(function (error) {
            const msg = error?.response?.data?.message || 'Error';
            Toast.fire({ icon: 'error', title: msg });
        });
    }

    // ==========================================================
    // Return Dispute System
    // Phase 3
    // General Submit Store Receipt (Accept / Escalate) Helper
    // ==========================================================
    function submitStoreConfirm(action, options) {
        options = options || {};
        const targetReturnId = currentReturnId ||
                               document.getElementById('return_id_store_confirm')?.value ||
                               (typeof window !== 'undefined' ? window.currentReturnId : null);
        if (!targetReturnId) {
            Toast.fire({ icon: 'error', title: 'Return ID missing' });
            return;
        }

        const formData = new FormData();
        formData.append('action', action);
        if (options.remarks) {
            formData.append('remarks', options.remarks);
        }
        if (options.dispute_reason) {
            formData.append('dispute_reason', options.dispute_reason);
        }
        if (options.inspection_photos && options.inspection_photos.length > 0) {
            for (let i = 0; i < options.inspection_photos.length; i++) {
                formData.append('inspection_photos[]', options.inspection_photos[i]);
            }
        }

        axios.post('/seller/order-returns/' + targetReturnId + '/store-confirm', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
        .then(function (response) {
            const data = response.data;
            if (data.success === false) {
                Toast.fire({ icon: 'error', title: data.message });
                return;
            }
            Toast.fire({ icon: 'success', title: data.message });
            setTimeout(function () { window.location.reload(); }, 1000);
        }).catch(function (error) {
            const msg = error?.response?.data?.message || error?.message || 'Error';
            Toast.fire({ icon: 'error', title: msg });
        });
    }

    const confirmAccept = document.getElementById('confirmAccept');
    if (confirmAccept) {
        confirmAccept.addEventListener('click', function () {
            const note = document.getElementById('seller_comment')?.value || '';
            submitDecision('approve', note);
        });
    }

    const confirmReject = document.getElementById('confirmReject');
    if (confirmReject) {
        confirmReject.addEventListener('click', function () {
            const note = document.getElementById('seller_comment_reject')?.value || '';
            submitDecision('reject', note);
        });
    }

    const confirmStoreReceiptBtn = document.getElementById('confirmStoreReceiptBtn');
    if (confirmStoreReceiptBtn) {
        confirmStoreReceiptBtn.addEventListener('click', function () {
            // ==========================================================
            // Return Dispute System
            // Phase 3
            // Submit Accept Return Option
            // ==========================================================
            const remarks = document.getElementById('store_confirm_comment')?.value || '';
            const photosInput = document.getElementById('store_confirm_photos');
            const photos = photosInput ? photosInput.files : [];

            submitStoreConfirm('approve', {
                remarks: remarks,
                inspection_photos: photos
            });
        });
    }

    const confirmStoreEscalateBtn = document.getElementById('confirmStoreEscalateBtn');
    if (confirmStoreEscalateBtn) {
        confirmStoreEscalateBtn.addEventListener('click', function (e) {
            // ==========================================================
            // Return Dispute System
            // Phase 3
            // Submit Escalate to Admin Option
            // ==========================================================
            const disputeReasonSelect = document.getElementById('store_escalate_reason');
            const disputeReason = disputeReasonSelect ? disputeReasonSelect.value : '';

            const remarksEl = document.getElementById('store_escalate_remarks');
            const remarks = remarksEl ? remarksEl.value.trim() : '';

            const photosInput = document.getElementById('store_escalate_photos');
            const photos = photosInput ? photosInput.files : [];

            if (!disputeReason) {
                e.preventDefault();
                Toast.fire({ icon: 'warning', title: 'Please select a dispute reason.' });
                disputeReasonSelect?.focus();
                return;
            }

            if (!remarks) {
                e.preventDefault();
                Toast.fire({ icon: 'warning', title: 'Please enter seller remarks describing the issue.' });
                remarksEl?.focus();
                return;
            }

            if (photos.length === 0) {
                e.preventDefault();
                Toast.fire({ icon: 'warning', title: 'At least one inspection photo is required for escalation.' });
                photosInput?.focus();
                return;
            }

            submitStoreConfirm('escalate', {
                dispute_reason: disputeReason,
                remarks: remarks,
                inspection_photos: photos
            });
        });
    }
});
