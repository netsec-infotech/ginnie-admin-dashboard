'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const markReadyBtn = document.getElementById('btnMarkReadyForPickup');
    const verifyOtpForm = document.getElementById('verifyPickupOtpForm');

    // Helper to safely show toast notification
    function showToast(icon, title) {
        if (typeof Toast !== 'undefined' && typeof Toast.fire === 'function') {
            Toast.fire({ icon: icon, title: title });
        } else if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
            Swal.fire({
                icon: icon,
                title: title,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            alert(title);
        }
    }

    // Helper to safely hide bootstrap modal
    function hideModal(el) {
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            try {
                const modal = window.bootstrap.Modal.getOrCreateInstance(el);
                if (modal) {
                    modal.hide();
                }
            } catch (e) {
                console.error('Error hiding modal via bootstrap:', e);
            }
        } else if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(el).modal('hide');
        } else {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    }

    // 1. Mark ready for pickup
    if (markReadyBtn) {
        markReadyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');
            if (!orderId) return;

            markReadyBtn.disabled = true;

            const isScheduledPickup = window.location.pathname.includes('scheduled-orders');
            const pickupPrefix = isScheduledPickup ? '/seller/scheduled-orders' : '/seller/orders';

            window.axios.post(`${pickupPrefix}/${orderId}/mark-ready-for-pickup`)
                .then(response => {
                    const data = response.data || {};
                    if (data.success) {
                        showToast('success', data.message || 'Order marked ready for pickup.');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        showToast('error', data.message || 'Failed to update order status.');
                        markReadyBtn.disabled = false;
                    }
                })
                .catch(err => {
                    const msg = err.response?.data?.message || 'Something went wrong.';
                    showToast('error', msg);
                    markReadyBtn.disabled = false;
                });
        });
    }

    // 2. Verify OTP code
    if (verifyOtpForm) {
        const otpInput = document.getElementById('pickup_otp_input');
        const errorMsgEl = document.getElementById('otp-error-msg');
        const submitBtn = document.getElementById('btnSubmitOtp');

        if (otpInput) {
            otpInput.addEventListener('input', function () {
                // Clear non-digits
                this.value = this.value.replace(/\D/g, '');
                // Clear error status
                this.classList.remove('is-invalid');
                if (errorMsgEl) {
                    errorMsgEl.textContent = '';
                }
            });
        }

        verifyOtpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const orderId = this.getAttribute('data-order-id');

            if (!orderId || !otpInput) return;

            // Clear previous errors
            otpInput.classList.remove('is-invalid');
            if (errorMsgEl) errorMsgEl.textContent = '';

            const otp = otpInput.value.trim();
            if (!/^\d{6}$/.test(otp)) {
                otpInput.classList.add('is-invalid');
                if (errorMsgEl) errorMsgEl.textContent = 'OTP must be exactly 6 digits.';
                return;
            }

            submitBtn.disabled = true;

            const isScheduledPickup = window.location.pathname.includes('scheduled-orders');
            const pickupPrefix = isScheduledPickup ? '/seller/scheduled-orders' : '/seller/orders';

            window.axios.post(`${pickupPrefix}/${orderId}/verify-pickup-otp`, { otp: otp })
                .then(response => {
                    const data = response.data || {};
                    if (data.success) {
                        showToast('success', data.message || 'OTP verified successfully.');
                        const modalEl = document.getElementById('verifyOtpModal');
                        hideModal(modalEl);
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        otpInput.classList.add('is-invalid');
                        const errMsg = data.message || 'OTP verification failed.';
                        if (errorMsgEl) errorMsgEl.textContent = errMsg;
                        showToast('error', errMsg);
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    const responseData = err.response?.data || {};
                    let msg = responseData.message || 'OTP verification failed.';
                    if (responseData.errors && typeof responseData.errors === 'object') {
                        const firstErrorKey = Object.keys(responseData.errors)[0];
                        if (firstErrorKey && Array.isArray(responseData.errors[firstErrorKey]) && responseData.errors[firstErrorKey][0]) {
                            msg = responseData.errors[firstErrorKey][0];
                        }
                    }
                    otpInput.classList.add('is-invalid');
                    if (errorMsgEl) errorMsgEl.textContent = msg;
                    showToast('error', msg);
                    submitBtn.disabled = false;
                });
        });
    }

    // Modal reset logic
    const modalEl = document.getElementById('verifyOtpModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (verifyOtpForm) verifyOtpForm.reset();
            const otpInput = document.getElementById('pickup_otp_input');
            const errorMsgEl = document.getElementById('otp-error-msg');
            if (otpInput) {
                otpInput.classList.remove('is-invalid');
            }
            if (errorMsgEl) {
                errorMsgEl.textContent = '';
            }
            const submitBtn = document.getElementById('btnSubmitOtp');
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        });
    }

    // 3. Individual item status transition for pickup orders
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-transition-status');
        if (!btn) return;

        e.preventDefault();
        const itemId = btn.getAttribute('data-item-id');
        const status = btn.getAttribute('data-status');
        if (!itemId || !status) return;

        btn.disabled = true;

        const isScheduledPickup = window.location.pathname.includes('scheduled-orders');
        const orderPathSegment = isScheduledPickup ? 'scheduled-orders' : 'orders';
        const path = (typeof base_url !== 'undefined' && typeof panel !== 'undefined')
            ? `${base_url}/${panel}/${orderPathSegment}/${itemId}/${status}`
            : `/seller/${orderPathSegment}/${itemId}/${status}`;

        window.axios.post(path)
            .then(response => {
                const data = response.data || {};
                if (data.success) {
                    showToast('success', data.message || 'Status updated successfully.');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast('error', data.message || 'Failed to update status.');
                    btn.disabled = false;
                }
            })
            .catch(err => {
                const msg = err.response?.data?.message || 'Something went wrong.';
                showToast('error', msg);
                btn.disabled = false;
            });
    });
});

