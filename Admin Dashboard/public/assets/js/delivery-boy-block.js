'use strict';

/**
 * Phase 1B-block — admin block / unblock for a delivery boy. Both flows go
 * through axios; the block flow opens a reason modal first, the unblock flow
 * confirms via SweetAlert and fires straight away.
 */
document.addEventListener('DOMContentLoaded', function () {
    const blockModalEl = document.getElementById('blockDeliveryBoyModal');
    const unblockBtn = document.getElementById('unblockDeliveryBoyBtn');

    // Block flow ------------------------------------------------------------
    if (blockModalEl) {
        const form = blockModalEl.querySelector('#blockDeliveryBoyForm');
        // The trigger button carries the URL via data-url so future placements
        // (e.g. inline action on the listing) reuse the same JS.
        let blockUrl = null;

        document.querySelectorAll('[data-bs-target="#blockDeliveryBoyModal"]').forEach((trigger) => {
            trigger.addEventListener('click', function () {
                blockUrl = this.getAttribute('data-url');
                const reasonField = blockModalEl.querySelector('#blockReason');
                if (reasonField) {
                    reasonField.value = '';
                    reasonField.classList.remove('is-invalid');
                }
                const fb = blockModalEl.querySelector('.invalid-feedback');
                if (fb) fb.textContent = '';
            });
        });

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!blockUrl) return;
                const reason = blockModalEl.querySelector('#blockReason').value.trim();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                window.axios.post(blockUrl, { reason })
                    .then((response) => {
                        const data = response.data || {};
                        if (data.success) {
                            Toast.fire({ icon: 'success', title: data.message });
                            // Reload so the badge + buttons reflect the new state.
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                        }
                    })
                    .catch((err) => {
                        const errors = err.response?.data?.errors;
                        if (errors && errors.reason) {
                            const reasonField = blockModalEl.querySelector('#blockReason');
                            const fb = blockModalEl.querySelector('.invalid-feedback');
                            reasonField.classList.add('is-invalid');
                            if (fb) fb.textContent = errors.reason[0];
                        } else {
                            Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                        }
                    })
                    .finally(() => {
                        if (submitBtn) submitBtn.disabled = false;
                    });
            });
        }
    }

    // Unblock flow ----------------------------------------------------------
    if (unblockBtn) {
        unblockBtn.addEventListener('click', function () {
            const url = this.getAttribute('data-url');
            if (!url) return;

            Swal.fire({
                title: 'Unblock this delivery boy?',
                text: 'They will be able to log in and accept orders again.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, unblock',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
            }).then((result) => {
                if (!result.isConfirmed) return;

                window.axios.post(url)
                    .then((response) => {
                        const data = response.data || {};
                        if (data.success) {
                            Toast.fire({ icon: 'success', title: data.message });
                            setTimeout(() => window.location.reload(), 800);
                        } else {
                            Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                        }
                    })
                    .catch((err) => {
                        Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                    });
            });
        });
    }
});
