'use strict';

/**
 * Admin "Add Note" modal — single-form axios submit. Endpoint URL is seeded
 * via window.adminOrderNoteEndpoint by show.blade.php so this file stays
 * order-id agnostic. On success we reload the page to refresh the timeline;
 * on validation failure we render the error inline beside the textarea.
 */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('adminAddNoteModal');
    if (!modalEl) return;

    const form = modalEl.querySelector('#adminAddNoteForm');
    if (!form) return;

    const noteField = modalEl.querySelector('#adminNoteBody');
    const fbEl = modalEl.querySelector('.invalid-feedback');
    const submitBtn = modalEl.querySelector('#adminAddNoteSubmit');

    // Reset state every time the modal opens — keeps stale errors out.
    modalEl.addEventListener('show.bs.modal', function () {
        if (noteField) {
            noteField.value = '';
            noteField.classList.remove('is-invalid');
        }
        if (fbEl) fbEl.textContent = '';
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const url = window.adminOrderEndpoints.addNote;
        const note = (noteField?.value || '').trim();
        console.log('Submitting note:', { url, note });
        if (!url || !note) return;

        if (submitBtn) submitBtn.disabled = true;

        window.axios.post(url, { note })
            .then((response) => {
                const data = response.data || {};
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    $('#adminAddNoteModal').modal('hide');
                    // Reload so the new entry appears in the timeline. Could be
                    // swapped for an inline prepend later if we expose a JSON
                    // shape that mirrors the audit-log-row partial.
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    Toast.fire({ icon: 'error', title: data.message || 'Failed' });
                }
            })
            .catch((err) => {
                const errors = err.response?.data?.errors;
                if (errors && errors.note) {
                    if (noteField) noteField.classList.add('is-invalid');
                    if (fbEl) fbEl.textContent = errors.note[0];
                } else {
                    Toast.fire({ icon: 'error', title: err.response?.data?.message || 'Something went wrong' });
                }
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
            });
    });
});
