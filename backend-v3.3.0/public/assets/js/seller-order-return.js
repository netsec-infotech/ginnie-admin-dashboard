'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const returnBtn = document.getElementById('btnPickupReturn');
    const modalEl = document.getElementById('pickup-refund-modal');
    
    if (!returnBtn || !modalEl) {
        return;
    }

    const $refundLoading    = document.getElementById('pickup-refund-loading');
    const $refundEmpty      = document.getElementById('pickup-refund-empty');
    const $refundFormWrap   = document.getElementById('pickup-refund-form-wrap');
    const $refundRows       = document.getElementById('pickup-refund-rows');
    const $refundAlready    = document.getElementById('pickup-refund-already');
    const $refundReason     = document.getElementById('pickup-refund-reason');
    const $refundTotal      = document.getElementById('pickup-refund-total');
    const $refundSubmit     = document.getElementById('pickup-refund-submit');
    const $refundMethodNote = document.getElementById('pickup-refund-method-note');

    let refundCtx = { orderId: null, lines: [] };

    // Format money using the dynamic currency symbol
    function formatMoney(val) {
        const symbol = window.currencySymbol || '$';
        return symbol + Number(val).toFixed(2);
    }

    // Escape HTML to prevent XSS
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

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

    function refundComputeTotal() {
        let total = 0;
        for (const ln of refundCtx.lines) {
            const qty = Math.max(0, Math.min(ln.max_refundable, parseInt(ln.input?.value || '0', 10) || 0));
            total += qty * ln.per_unit_amount;
        }
        $refundTotal.textContent = formatMoney(total);
        $refundSubmit.disabled = total <= 0;
        return total;
    }

    function renderRefundLines(preview) {
        refundCtx.lines = (preview.refundable || []).map(r => ({ ...r, input: null }));
        if (!refundCtx.lines.length) {
            $refundFormWrap.classList.add('d-none');
            $refundEmpty.classList.remove('d-none');
            $refundSubmit.disabled = true;
            return;
        }
        $refundEmpty.classList.add('d-none');
        $refundFormWrap.classList.remove('d-none');

        $refundRows.innerHTML = refundCtx.lines.map((ln, i) => {
            const meta = [ln.variant_title, ln.addon_summary].filter(Boolean).join(' · ');
            return `
                <tr data-i="${i}">
                    <td>
                        <div class="fw-medium">${escapeHtml(ln.title)}</div>
                        ${meta ? `<div class="text-secondary small">${escapeHtml(meta)}</div>` : ''}
                        ${ln.already_refunded > 0 ? `<div class="text-secondary small">${ln.already_refunded} of ${ln.quantity} already returned</div>` : ''}
                    </td>
                    <td class="text-center text-secondary">${ln.quantity}</td>
                    <td class="text-center">
                        <input type="number" min="0" max="${ln.max_refundable}" value="0"
                               class="form-control form-control-sm pickup-refund-qty"
                               data-i="${i}" inputmode="numeric" style="max-width: 90px; margin: 0 auto;">
                        <div class="text-secondary small mt-1">max ${ln.max_refundable}</div>
                    </td>
                    <td class="text-end">
                        <span class="fw-medium pickup-refund-line-amt" data-i="${i}">${formatMoney(0)}</span>
                        <div class="text-secondary small">${formatMoney(ln.per_unit_amount)} each</div>
                    </td>
                </tr>`;
        }).join('');

        // Wire up qty inputs
        refundCtx.lines.forEach((ln, i) => {
            ln.input = $refundRows.querySelector(`input.pickup-refund-qty[data-i="${i}"]`);
            const $amt = $refundRows.querySelector(`.pickup-refund-line-amt[data-i="${i}"]`);
            ln.input?.addEventListener('input', () => {
                let q = parseInt(ln.input.value || '0', 10) || 0;
                if (q < 0) q = 0;
                if (q > ln.max_refundable) q = ln.max_refundable;
                ln.input.value = String(q);
                if ($amt) $amt.textContent = formatMoney(q * ln.per_unit_amount);
                refundComputeTotal();
            });
        });
        refundComputeTotal();
    }

    async function loadRefundPreview(orderId) {
        refundCtx = { orderId, lines: [] };
        $refundReason.value = '';
        $refundTotal.textContent = formatMoney(0);
        $refundSubmit.disabled = true;
        $refundLoading.classList.remove('d-none');
        $refundEmpty.classList.add('d-none');
        $refundFormWrap.classList.add('d-none');
        $refundAlready.classList.add('d-none');
        $refundRows.innerHTML = '';
        if ($refundMethodNote) $refundMethodNote.value = '';

        try {
            const res = await window.axios.get(window.sellerRefundEndpoints.preview);
            const preview = res?.data?.data || {};
            if (preview.already_refunded_total > 0) {
                $refundAlready.classList.remove('d-none');
                $refundAlready.textContent = `${formatMoney(preview.already_refunded_total)} already returned against this order.`;
            }
            renderRefundLines(preview);
        } catch (err) {
            $refundLoading.classList.remove('d-none');
            $refundLoading.textContent = err?.response?.data?.message || 'Could not load return preview details.';
        } finally {
            $refundLoading.classList.add('d-none');
        }
    }

    async function submitRefund() {
        if (!refundCtx.orderId) return;
        const lines = refundCtx.lines
            .map(ln => ({ order_item_id: ln.order_item_id, quantity: parseInt(ln.input?.value || '0', 10) || 0 }))
            .filter(l => l.quantity > 0);
        if (!lines.length) return;

        const method = document.querySelector('input[name="pickup-refund-method"]:checked')?.value || 'cash';
        const note   = ($refundMethodNote?.value || '').trim();

        $refundSubmit.disabled = true;
        $refundSubmit.textContent = 'Processing…';
        try {
            const res = await window.axios.post(window.sellerRefundEndpoints.create, {
                lines,
                reason: ($refundReason.value || '').trim() || null,
                method,
                method_note: note || null,
            });
            
            const data = res?.data || {};
            if (data.success) {
                showToast('success', data.message || 'Return successfully processed.');
                const modal = window.bootstrap?.Modal?.getInstance(modalEl);
                if (modal) modal.hide();
                setTimeout(() => window.location.reload(), 600);
            } else {
                throw new Error(data.message || 'Failed to process return.');
            }
        } catch (err) {
            const msg = err.response?.data?.message || err.message || 'Something went wrong.';
            showToast('error', msg);
            $refundSubmit.disabled = false;
            $refundSubmit.textContent = 'Process Return';
        }
    }

    returnBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const orderId = this.getAttribute('data-order-id');
        if (!orderId) return;

        loadRefundPreview(orderId);
    });

    $refundSubmit.addEventListener('click', submitRefund);
});
