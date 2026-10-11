'use strict';

/**
 * Phase 1D — wires up the seller-side post-accept Cancel and Confirm-Received
 * actions. The modals are populated from the listing dropdown's data-id and
 * submitted through axios. On success we trigger the same datatable reload
 * that order.js uses, so the row's status/badge updates without a full reload.
 */
document.addEventListener('DOMContentLoaded', function () {
    initSellerBulkUpdate();

    const cancelModalEl = document.getElementById('cancelItemModal');
    const confirmModalEl = document.getElementById('confirmReturnModal');
    if (!cancelModalEl && !confirmModalEl) return;

    // Reload the datatable that's currently visible on the orders index. Falls
    // back to no-op when invoked from the order detail page.
    const reloadActiveTable = function () {
        if (typeof $ === 'undefined') return;
        const ordersListTable = $('#orders-list-table').length ? $('#orders-list-table').DataTable() : null;
        const ordersTable = $('#orders-table').length ? $('#orders-table').DataTable() : null;
        const activeIsOrders = $('#orders-pane').length && $('#orders-pane').hasClass('active');
        if (activeIsOrders && ordersListTable) {
            ordersListTable.ajax.reload(null, false);
        } else if (ordersTable) {
            ordersTable.ajax.reload(null, false);
        }
    };

    // Bootstrap dispatches show.bs.modal with relatedTarget = the trigger
    // element. We pull data-id off the dropdown item and seed the hidden input.
    if (cancelModalEl) {
        cancelModalEl.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            const id = trigger && trigger.getAttribute('data-id');
            if (id) {
                cancelModalEl.querySelector('#cancelItemId').value = id;
            }
            // Reset previous state
            const reasonField = cancelModalEl.querySelector('#cancelItemReason');
            if (reasonField) {
                reasonField.value = '';
                reasonField.classList.remove('is-invalid');
            }
            const fb = cancelModalEl.querySelector('.invalid-feedback');
            if (fb) fb.textContent = '';
        });

        const cancelForm = cancelModalEl.querySelector('#cancelItemForm');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const id = cancelModalEl.querySelector('#cancelItemId').value;
                const reason = cancelModalEl.querySelector('#cancelItemReason').value.trim();
                if (!id) return;

                const submitBtn = cancelForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                window.axios.post(`${base_url}/${panel}/orders/${id}/cancel-item`, { reason })
                    .then((response) => {
                        const data = response.data || {};
                        if (data.success) {
                            Toast.fire({ icon: 'success', title: data.message });
                            $('#cancelItemModal').modal('hide');
                            reloadActiveTable();
                        } else {
                            Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                        }
                    })
                    .catch((err) => {
                        const errors = err.response?.data?.errors;
                        if (errors && errors.reason) {
                            const reasonField = cancelModalEl.querySelector('#cancelItemReason');
                            const fb = cancelModalEl.querySelector('.invalid-feedback');
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

    if (confirmModalEl) {
        confirmModalEl.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            const id = trigger && trigger.getAttribute('data-id');
            if (id) {
                confirmModalEl.querySelector('#confirmReturnId').value = id;
            }
        });

        const confirmForm = confirmModalEl.querySelector('#confirmReturnForm');
        if (confirmForm) {
            confirmForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const id = confirmModalEl.querySelector('#confirmReturnId').value;
                if (!id) return;

                const submitBtn = confirmForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                window.axios.post(`${base_url}/${panel}/orders/${id}/confirm-return`)
                    .then((response) => {
                        const data = response.data || {};
                        if (data.success) {
                            $('#confirmReturnModal').modal('hide');

                            Toast.fire({ icon: 'success', title: data.message });
                            reloadActiveTable();
                        } else {
                            Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                        }
                    })
                    .catch((err) => {
                        Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                    })
                    .finally(() => {
                        if (submitBtn) submitBtn.disabled = false;
                    });
            });
        }
    }

    /* ────────────────────────────────────────────────────────────────── */
    /*  Bulk Update Item Status (seller)                                   */
    /* ────────────────────────────────────────────────────────────────── */

    function initSellerBulkUpdate() {
        const endpoints = window.sellerOrderEndpoints || {};
        const url = endpoints.itemsBulkUpdateStatus;
        if (!url) return;

        const selectAll = document.getElementById('sellerSelectAllItems');
        const bulkBtn = document.getElementById('sellerBulkUpdateBtn');
        const countBadge = document.getElementById('sellerBulkSelectedCount');
        const modalEl = document.getElementById('sellerBulkUpdateStatusModal');
        if (!bulkBtn || !modalEl) return;

        const statusSelect = modalEl.querySelector('#sellerBulkStatusSelect');
        const statusHint = modalEl.querySelector('#sellerBulkStatusHint');
        const summaryEl = modalEl.querySelector('#sellerBulkSelectionSummary');
        const submitBtn = modalEl.querySelector('#sellerBulkUpdateSubmit');
        const form = modalEl.querySelector('#sellerBulkUpdateStatusForm');
        const resultBlock = modalEl.querySelector('#sellerBulkResultBlock');
        const resultList = modalEl.querySelector('#sellerBulkResultList');

        const transitionsByItem = window.sellerOrderItemTransitions || {};

        const getCheckboxes = () =>
            Array.from(document.querySelectorAll('input.seller-item-checkbox:not(:disabled)'));
        const getSelectedIds = () =>
            getCheckboxes().filter((c) => c.checked).map((c) => parseInt(c.value, 10));

        function refreshButtonState() {
            const ids = getSelectedIds();
            const n = ids.length;
            bulkBtn.disabled = n === 0;
            if (countBadge) {
                countBadge.textContent = String(n);
                countBadge.classList.toggle('d-none', n === 0);
            }
            if (selectAll) {
                const all = getCheckboxes();
                selectAll.indeterminate = n > 0 && n < all.length;
                selectAll.checked = n > 0 && n === all.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                getCheckboxes().forEach((c) => { c.checked = selectAll.checked; });
                refreshButtonState();
            });
        }
        document.addEventListener('change', function (e) {
            if (e.target && e.target.matches('input.seller-item-checkbox')) {
                refreshButtonState();
            }
        });

        function commonTransitions(ids) {
            if (!ids.length) return [];
            const lists = ids.map((id) => transitionsByItem[id] || []);
            const first = lists[0] || [];
            return first.filter((t) =>
                lists.every((l) => l.some((x) => x.value === t.value))
            );
        }

        function repopulateStatusOptions() {
            const ids = getSelectedIds();
            const common = commonTransitions(ids);

            statusSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '—';
            statusSelect.appendChild(placeholder);

            common.forEach((t) => {
                const opt = document.createElement('option');
                opt.value = t.value;
                opt.textContent = t.label;
                statusSelect.appendChild(opt);
            });

            const hasOptions = common.length > 0;
            if (statusHint) statusHint.hidden = hasOptions;
            statusSelect.disabled = !hasOptions;
            submitBtn.disabled = !hasOptions;

            if (summaryEl) {
                summaryEl.textContent = ids.length === 1
                    ? '1 item selected.'
                    : `${ids.length} items selected.`;
            }
        }

        modalEl.addEventListener('show.bs.modal', function () {
            clearSellerFieldErrors(form);
            if (resultBlock) resultBlock.hidden = true;
            if (resultList) resultList.innerHTML = '';
            repopulateStatusOptions();
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const ids = getSelectedIds();
            if (!ids.length) {
                Toast.fire({ icon: 'error', title: 'No items selected.' });
                return;
            }
            if (!statusSelect.value) {
                markSellerInvalid(form, 'status', 'Pick a target status.');
                return;
            }

            const payload = {
                item_ids: ids,
                status: statusSelect.value,
            };

            submitBtn.disabled = true;
            clearSellerFieldErrors(form);
            if (resultBlock) resultBlock.hidden = true;
            if (resultList) resultList.innerHTML = '';

            window.axios.post(url, payload)
                .then((response) => {
                    const data = response.data || {};
                    const payloadData = data.data || {};
                    const failures = (payloadData.results || []).filter((r) => !r.success);

                    if (data.success) {
                        Toast.fire({ icon: 'success', title: data.message });
                        $('#sellerBulkUpdateStatusModal').modal('hide');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        Toast.fire({ icon: 'error', title: data.message || 'Some items failed.' });
                        if (failures.length && resultBlock && resultList) {
                            failures.forEach((f) => {
                                const li = document.createElement('li');
                                li.className = 'text-danger';
                                li.textContent = `#${f.order_item_id} — ${f.message}`;
                                resultList.appendChild(li);
                            });
                            resultBlock.hidden = false;
                        }
                    }
                })
                .catch((err) => {
                    const errors = err.response?.data?.errors;
                    if (errors) {
                        Object.entries(errors).forEach(([field, msgs]) => {
                            markSellerInvalid(form, field, msgs?.[0] || 'Invalid');
                        });
                    } else {
                        Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                    }
                })
                .finally(() => {
                    submitBtn.disabled = false;
                });
        });

        refreshButtonState();
    }

    function markSellerInvalid(form, field, message) {
        if (!form) return;
        const input = form.querySelector(`[name="${field}"]`);
        if (input) input.classList.add('is-invalid');
        const fb = form.querySelector(`.invalid-feedback[data-field="${field}"]`);
        if (fb) fb.textContent = message;
    }

    function clearSellerFieldErrors(form) {
        if (!form) return;
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => (el.textContent = ''));
    }
});
