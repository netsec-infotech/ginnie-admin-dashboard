// Define a global namespace for datatable utilities
window.DatatableUtils = {
    // Public method to refresh a specific datatable by ID or all datatables
    refreshDatatable: function (tableId = null) {
        if (tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
            // Refresh specific table if ID is provided and table exists
            $('#' + tableId).DataTable().ajax.reload(function () {
                console.log('Datatable ' + tableId + ' refreshed successfully');
                // Refresh lightbox for new content
                if (typeof refreshFsLightbox !== 'undefined') {
                    refreshFsLightbox();
                }
            }, false);
        } else {
            // Refresh all datatables if no ID provided or table not found
            const datatables = $.fn.dataTable.tables();
            if (datatables.length > 0) {
                $(datatables).each(function () {
                    $(this).DataTable().ajax.reload(function () {
                        console.log('Datatable refreshed successfully');
                        // Refresh lightbox for new content
                        if (typeof refreshFsLightbox !== 'undefined') {
                            refreshFsLightbox();
                        }
                    }, false);
                });
            }
        }
    }
};

$(document).ready(function () {


    // Show spinner on AJAX start, hide on complete
    $(document).on('ajaxStart', function () {
        $('#datatable-loading').removeClass('d-none');
    }).on('ajaxStop', function () {
        $('#datatable-loading').addClass('d-none');
    });
    // Reset filters
    $('#resetFilters').on('click', function () {
        $('#statusFilter, #brandFilter, #categoryFilter').val('');
        $('#reportrange').val('');
        // Trigger filter update if needed
    });


    const datatableElements = document.querySelectorAll('[data-datatable]');

    datatableElements.forEach((element) => {
        const route = element.getAttribute('data-route');
        const columns = JSON.parse(element.getAttribute('data-columns'));
        // Read the custom options, if they exist.
        const rawOptions = element.getAttribute('data-options');
        let customOptions = rawOptions ? JSON.parse(rawOptions) : {};

        // Check if the DataTable is already initialized
        if ($.fn.DataTable.isDataTable(element)) {
            return; // Skip initialization if already initialized
        }

        // Define your default options.
        let defaultOptions = {
            language: {
                emptyTable: "<div class='text-center text-secondary p-4'><i class='ti ti-database fs-1'></i><br>No data available.</div>",
                lengthMenu: "Show _MENU_ per page",
                info: "_START_-_END_ of _TOTAL_",
                infoEmpty: "0-0 of 0",
                infoFiltered: "",
                paginate: {
                    previous: "<i class='ti ti-arrow-left'></i>",
                    next: "<i class='ti ti-arrow-right'></i>"
                }
            },
            layout: {
                topStart: {
                    buttons: [
                        {
                            extend: 'colvis',
                            text: '<i class="ti ti-columns-3 me-1"></i><span>Columns</span>',
                            columns: ':not(:first-child)',
                            columnText: function (dt, idx, title) {
                                return idx + ': ' + title;
                            },
                            init: function (dt, node) {
                                $(node)
                                    .removeClass('btn-secondary')
                                    .addClass('btn btn-outline-secondary btn-sm rounded-2 dt-toolbar-btn dropdown-toggle');
                            }
                        },
                        {
                            extend: 'collection',
                            text: '<i class="ti ti-download me-1"></i><span>Export</span>',
                            className: 'btn',
                            buttons: [
                                {
                                    extend: 'excelHtml5',
                                    text: '<i class="ti ti-file-spreadsheet me-2 text-success"></i><span>Excel</span>',
                                    exportOptions: { columns: ':visible' }
                                }
                            ],
                            init: function (dt, node) {
                                $(node)
                                    .removeClass('btn-secondary')
                                    .addClass('btn btn-outline-secondary btn-sm ms-2 rounded-2 dt-toolbar-btn dropdown-toggle');
                            }
                        },
                    ]
                },
                topEnd: 'search',
                bottomStart: 'pageLength',
                bottomEnd: {
                    info: {},
                    paging: {}
                }
            },
            initComplete: function () {
                const $container = $(element).closest('.dt-container');
                $container.children('.row').first().addClass('p-3 m-0');
                $container.children('.row').last().addClass('p-3 m-0');

                const $search = $container.find('.dt-search input');
                $search.removeClass('form-control-sm')
                    .addClass('ms-0')
                    .attr('placeholder', 'Search...');
                $container.find('.dt-search label').contents().filter(function () {
                    return this.nodeType === 3;
                }).remove();

                $container.find('.dt-length select').removeClass('form-select-sm');
            },
            select: false,
            responsive: true,
            processing: true,
            serverSide: true,
            scrollX: true,
            pagingType: 'simple_numbers',
            ajax: {
                url: route,
            },
            columns: columns,
            drawCallback: function() {
                const api = this.api();
                const info = api.page.info();

                if (info.page > 0 && info.pages > 0 && info.page >= info.pages) {
                    api.page(info.pages - 1).draw('page');
                    return;
                }

                if (typeof refreshFsLightbox !== 'undefined') {
                    refreshFsLightbox();
                }

                // Re-initialize Bootstrap tooltips for dynamically rendered rows
                if (typeof bootstrap !== 'undefined') {
                    api.table().container().querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                        if (!bootstrap.Tooltip.getInstance(el)) {
                            new bootstrap.Tooltip(el);
                        }
                    });
                }
            }
        };

        // Merge custom options with defaultOptions.
        // If customOptions is an array, merge each object into defaultOptions.
        if (Array.isArray(customOptions)) {
            customOptions.forEach(option => {
                defaultOptions = $.extend(true, {}, defaultOptions, option);
            });
        } else {
            defaultOptions = $.extend(true, {}, defaultOptions, customOptions);
        }
        $(element).DataTable(defaultOptions);
    });
});
$(document).ready(function () {
    // Add click event listener for refresh button
    $('#refresh, .refresh-table').on('click', function () {
        // Try to find the closest datatable to the clicked button
        const closestTable = $(this).closest('.card').find('table.dataTable');

        if (closestTable.length > 0) {
            // If found, refresh only that table
            const tableId = closestTable.attr('id');
            if (tableId) {
                // Use the global method to refresh the specific table
                window.DatatableUtils.refreshDatatable(tableId);
                return;
            }
        }

        // If no specific table found, refresh all datatables
        window.DatatableUtils.refreshDatatable();
    });
});
