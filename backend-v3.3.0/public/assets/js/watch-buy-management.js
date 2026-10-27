document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.watch-buy-profile-seller-select').forEach((select) => {
        if (typeof TomSelect === 'undefined' || select.tomselect) {
            return;
        }

        const renderSeller = (data, escape) => {
            const subtitle = data.subtitle ? `<div class="small text-secondary">${escape(data.subtitle)}</div>` : '';
            return `<div><div>${escape(data.text || '')}</div>${subtitle}</div>`;
        };
        const sellerSelect = new TomSelect(select, {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: select.dataset.placeholder,
            preload: true,
            maxOptions: 50,
            render: {item: renderSeller, option: renderSeller},
            load(query, callback) {
                const url = new URL(select.dataset.endpoint, window.location.origin);
                url.searchParams.set('search', query);
                fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                    .then((response) => response.ok ? response.json() : Promise.reject())
                    .then((data) => callback(data))
                    .catch(() => callback());
            },
        });

        if (select.dataset.selected && select.dataset.selected !== '0') {
            const url = new URL(select.dataset.endpoint, window.location.origin);
            url.searchParams.append('find_in[]', select.dataset.selected);
            fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                .then((response) => response.json())
                .then((options) => {
                    sellerSelect.addOptions(options);
                    sellerSelect.setValue(select.dataset.selected, true);
                });
        }
    });
});

const reloadWatchBuyTables = () => {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('.data-table').each(function () {
            if (jQuery.fn.DataTable.isDataTable(this)) {
                jQuery(this).DataTable().ajax.reload(null, false);
            }
        });
    }
};

const submitWatchBuyAction = (form) => {
    if (form.dataset.submitting === '1') {
        return;
    }

    form.dataset.submitting = '1';
    const button = form.querySelector('button');
    if (button) {
        button.disabled = true;
    }

    axios.post(form.action, new FormData(form), {
        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
    }).then((response) => {
        Toast.fire({icon: 'success', title: response.data.message});
        reloadWatchBuyTables();
    }).catch((error) => {
        Toast.fire({icon: 'error', title: error.response?.data?.message || 'Something went wrong.'});
    }).finally(() => {
        delete form.dataset.submitting;
        if (button) {
            button.disabled = false;
        }
    });
};

document.addEventListener('submit', (event) => {
    const form = event.target.closest('.watch-buy-action-form');
    if (!form) {
        return;
    }

    event.preventDefault();
    submitWatchBuyAction(form);
});

document.addEventListener('click', (event) => {
    const deleteButton = event.target.closest('.watch-buy-delete');
    if (deleteButton) {
        Swal.fire({
            title: deleteButton.dataset.title,
            text: deleteButton.dataset.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: deleteButton.dataset.confirmText,
            cancelButtonText: deleteButton.dataset.cancelText,
            confirmButtonColor: '#d63939',
        }).then((result) => {
            if (result.isConfirmed) {
                submitWatchBuyAction(deleteButton.closest('form'));
            }
        });

        return;
    }

    const reasonButton = event.target.closest('.watch-buy-reason-action');
    if (!reasonButton) {
        return;
    }

    Swal.fire({
        title: reasonButton.dataset.title,
        input: 'textarea',
        inputPlaceholder: reasonButton.dataset.placeholder,
        inputAttributes: {maxlength: 2000},
        showCancelButton: true,
        confirmButtonText: reasonButton.dataset.confirmText,
        cancelButtonText: reasonButton.dataset.cancelText,
        inputValidator: (value) => value?.trim() ? null : reasonButton.dataset.reasonRequired,
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        const form = reasonButton.closest('form');
        form.querySelector('[name="reason"]').value = result.value.trim();
        submitWatchBuyAction(form);
    });
});
