(function () {
    const selections = new Map();

    function getToolbar(tableId) {
        return document.querySelector(`.catalog-selection-toolbar[data-table-id="${tableId}"]`);
    }

    function getSelected(tableId) {
        if (!selections.has(tableId)) {
            selections.set(tableId, new Set());
        }

        return selections.get(tableId);
    }

    function getTableIdFromCheckbox(checkbox) {
        const table = checkbox.closest('table');
        if (table?.id) {
            return table.id;
        }

        return checkbox.closest('.dt-container')?.querySelector('table.data-table[id]')?.id || null;
    }

    function getRowCheckboxes(tableId) {
        const table = document.getElementById(tableId);

        return table ? table.querySelectorAll('tbody .bulk-delete-row-checkbox') : [];
    }

    function selectedIds(tableId) {
        return Array.from(getSelected(tableId));
    }

    function notifySelectionChanged(tableId) {
        document.dispatchEvent(new CustomEvent('catalog-selection:changed', {
            detail: {
                tableId,
                ids: selectedIds(tableId),
            },
        }));
    }

    function updateToolbar(tableId) {
        const toolbar = getToolbar(tableId);
        const selected = getSelected(tableId);
        const count = selected.size;
        const table = document.getElementById(tableId);
        const selectAll = table?.querySelector('.bulk-delete-select-all');
        const rowCheckboxes = Array.from(getRowCheckboxes(tableId));

        rowCheckboxes.forEach((checkbox) => {
            checkbox.checked = selected.has(parseInt(checkbox.value, 10));
        });

        if (toolbar) {
            const countEl = toolbar.querySelector('.bulk-delete-selected-count');
            toolbar.classList.toggle('d-none', count === 0);
            if (countEl) {
                countEl.textContent = String(count);
            }
        }

        if (selectAll) {
            const checkedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;
            selectAll.checked = rowCheckboxes.length > 0 && checkedCount === rowCheckboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
        }

        notifySelectionChanged(tableId);
    }

    function reloadTable(tableId) {
        if (window.jQuery && $.fn.DataTable.isDataTable(`#${tableId}`)) {
            $(`#${tableId}`).DataTable().ajax.reload(null, false);
        }
    }

    function clearSelection(tableId, reload = false) {
        getSelected(tableId).clear();
        getRowCheckboxes(tableId).forEach((checkbox) => {
            checkbox.checked = false;
        });

        const selectAll = document.getElementById(tableId)?.querySelector('.bulk-delete-select-all');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }

        updateToolbar(tableId);

        if (reload) {
            reloadTable(tableId);
        }
    }

    function buildSummary(data) {
        const payload = data?.data || {};
        const deletedCount = payload.deleted_count || 0;
        const skipped = payload.skipped || [];
        const failed = payload.failed || [];
        const lines = [
            `Deleted: ${deletedCount}`,
            `Skipped: ${skipped.length}`,
            `Failed: ${failed.length}`,
        ];
        const details = skipped.concat(failed).slice(0, 5);

        if (details.length > 0) {
            lines.push('');
            details.forEach((item) => {
                const name = item.title ? `${item.title} (#${item.id})` : `#${item.id}`;
                lines.push(`${name}: ${item.reason || 'Not deleted'}`);
            });
        }

        return lines.join('<br>');
    }

    function visibleExportColumns(tableId) {
        if (!window.jQuery || !$.fn.DataTable.isDataTable(`#${tableId}`)) {
            return [];
        }

        const table = $(`#${tableId}`).DataTable();
        const columns = [];

        table.columns(':visible').every(function () {
            const data = this.dataSrc();
            if (!data || ['select', 'action', 'expand'].includes(data)) {
                return;
            }

            columns.push(data);
        });

        return columns;
    }

    function appendSelectedIds(url, ids, tableId, exportColumnsMode = 'visible') {
        const exportUrl = new URL(url, window.location.origin);
        ids.forEach((id) => exportUrl.searchParams.append('ids[]', id));
        // Import-mode exports keep selected ids but let the backend choose bulk-upload columns.
        if (exportColumnsMode !== 'import') {
            visibleExportColumns(tableId).forEach((column) => exportUrl.searchParams.append('columns[]', column));
        } else {
            exportUrl.searchParams.set('export_format', 'import');
        }

        return exportUrl.toString();
    }

    document.addEventListener('change', function (event) {
        const selectAll = event.target.closest('.bulk-delete-select-all');
        if (selectAll) {
            const tableId = getTableIdFromCheckbox(selectAll);
            if (!tableId) {
                return;
            }

            const selected = getSelected(tableId);
            getRowCheckboxes(tableId).forEach((checkbox) => {
                const id = parseInt(checkbox.value, 10);
                if (!id) {
                    return;
                }
                if (selectAll.checked) {
                    selected.add(id);
                } else {
                    selected.delete(id);
                }
            });
            updateToolbar(tableId);

            return;
        }

        const rowCheckbox = event.target.closest('.bulk-delete-row-checkbox');
        if (!rowCheckbox) {
            return;
        }

        const tableId = getTableIdFromCheckbox(rowCheckbox);
        const id = parseInt(rowCheckbox.value, 10);
        if (!tableId || !id) {
            return;
        }

        const selected = getSelected(tableId);
        if (rowCheckbox.checked) {
            selected.add(id);
        } else {
            selected.delete(id);
        }

        updateToolbar(tableId);
    });

    document.addEventListener('click', function (event) {
        const deselect = event.target.closest('.bulk-delete-deselect');
        if (deselect) {
            const toolbar = deselect.closest('.catalog-selection-toolbar');
            if (toolbar?.dataset.tableId) {
                clearSelection(toolbar.dataset.tableId, true);
            }

            return;
        }

        const exportSelected = event.target.closest('.catalog-export-selected');
        if (exportSelected) {
            const toolbar = exportSelected.closest('.catalog-selection-toolbar');
            const tableId = toolbar?.dataset.tableId;
            const url = toolbar?.dataset.exportUrl;
            const ids = tableId ? selectedIds(tableId) : [];

            if (!tableId || !url || ids.length === 0) {
                return;
            }

            window.location.href = appendSelectedIds(url, ids, tableId, toolbar.dataset.exportColumnsMode);

            return;
        }

        const submit = event.target.closest('.bulk-delete-submit');
        if (!submit) {
            return;
        }

        const toolbar = submit.closest('.catalog-selection-toolbar');
        const tableId = toolbar?.dataset.tableId;
        const url = toolbar?.dataset.bulkDeleteUrl;
        const ids = tableId ? selectedIds(tableId) : [];

        if (!tableId || !url || ids.length === 0) {
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${ids.length} selected item(s).`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: typeof primaryColor !== 'undefined' ? primaryColor : '#206bc4',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete selected',
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            submit.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': typeof csrfToken !== 'undefined' ? csrfToken : '',
                },
                body: JSON.stringify({ids}),
            })
                .then((response) => response.json().then((data) => ({ok: response.ok, data})))
                .then(({ok, data}) => {
                    if (!ok || !data.success) {
                        Swal.fire('Error!', data.message || 'There was a problem deleting the selected items.', 'error');

                        return;
                    }

                    clearSelection(tableId, true);
                    Swal.fire('Deleted!', buildSummary(data), 'success');
                })
                .catch(() => {
                    Swal.fire('Error!', 'There was a problem deleting the selected items.', 'error');
                })
                .finally(() => {
                    submit.disabled = false;
                });
        });
    });

    if (window.jQuery) {
        $(document).on('preXhr.dt', 'table.data-table', function (event, settings, data) {
            if (this.id) {
                data.selected_ids = selectedIds(this.id);
            }
        });

        $(document).on('draw.dt', 'table.data-table', function () {
            if (this.id) {
                updateToolbar(this.id);
            }
        });
    }

    window.CatalogSelection = {
        clear: clearSelection,
        getSelectedIds: selectedIds,
        update: updateToolbar,
    };
}());
