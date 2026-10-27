'use strict';

(function () {
    var config = window.cronMonitorConfig || {};
    var REFRESH_INTERVAL = 30;
    var countdown = REFRESH_INTERVAL;
    var refreshTimer = null;

    // Track current history state for pagination
    var currentHistoryCommand = null;
    var currentHistoryPage = 1;

    // -----------------------------------------------------------------------
    // Auto-refresh polling
    // -----------------------------------------------------------------------
    function startAutoRefresh() {
        countdown = REFRESH_INTERVAL;
        updateCountdownDisplay();
        clearInterval(refreshTimer);
        refreshTimer = setInterval(function () {
            countdown--;
            updateCountdownDisplay();
            if (countdown <= 0) {
                fetchStatus();
                countdown = REFRESH_INTERVAL;
            }
        }, 1000);
    }

    function updateCountdownDisplay() {
        var el = document.getElementById('auto-refresh-countdown');
        if (el) {
            el.textContent = (config.labels.autoRefreshIn || 'Auto-refresh in') + ' ' + countdown + 's';
        }
    }

    function fetchStatus() {
        axios.get(config.statusUrl, {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function (response) {
            if (response.data && response.data.success && response.data.data) {
                updateHealthCards(response.data.data);
                updateCommandCards(response.data.data.commands || []);
            }
        })
        .catch(function () {
            // Silent fail — next poll will retry
        });
    }

    function updateHealthCards(data) {
        var schedulerIcon = document.getElementById('scheduler-health-icon');
        var schedulerText = document.getElementById('scheduler-health-text');
        if (schedulerIcon) {
            schedulerIcon.style.backgroundColor = data.is_scheduler_healthy ? '#2fb344' : '#d63939';
        }
        if (schedulerText) {
            schedulerText.textContent = data.is_scheduler_healthy
                ? config.labels.running
                : config.labels.notConfigured;
        }

        var queueIcon = document.getElementById('queue-health-icon');
        var queueText = document.getElementById('queue-health-text');
        if (queueIcon) {
            queueIcon.style.backgroundColor = data.is_queue_worker_healthy ? '#2fb344' : '#d63939';
        }
        if (queueText) {
            queueText.textContent = data.is_queue_worker_healthy
                ? config.labels.running
                : config.labels.notConfigured;
        }
    }

    function updateCommandCards(commands) {
        commands.forEach(function (cmd) {
            if (cmd.type !== 'scheduled' && cmd.type !== 'manual') return;

            var card = document.querySelector('[data-command="' + cmd.command + '"]');
            if (!card) return;

            var badge = card.querySelector('.cmd-status-badge');
            if (badge) {
                var statusMap = {
                    success:   { cls: 'badge bg-success-lt',   label: config.labels.success },
                    running:   { cls: 'badge bg-azure-lt',     label: config.labels.statusRunning },
                    failed:    { cls: 'badge bg-danger-lt',    label: config.labels.failed },
                    never_run: { cls: 'badge bg-secondary-lt', label: config.labels.neverRun },
                };
                var s = statusMap[cmd.last_status] || statusMap.never_run;
                badge.className = s.cls;
                badge.textContent = s.label;
            }

            var lastRunEl = card.querySelector('.cmd-last-run');
            if (lastRunEl && cmd.last_run && cmd.last_run.started_at) {
                lastRunEl.textContent = timeAgo(cmd.last_run.started_at);
            }
        });
    }

    // -----------------------------------------------------------------------
    // Manual run
    // -----------------------------------------------------------------------
    $(document).on('click', '.run-command-btn', function (e) {
        e.preventDefault();
        var btn = $(this);
        var command = btn.data('command');

        Swal.fire({
            title: config.labels.runConfirmTitle || 'Run command?',
            text: (config.labels.runConfirmText || 'Execute') + ': ' + command,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: config.labels.confirmYes || 'Yes',
            cancelButtonText: config.labels.confirmCancel || 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            btn.prop('disabled', true)
               .html('<span class="spinner-border spinner-border-sm me-1"></span>' +
                   (config.labels.statusRunning || 'Running...'));

            axios.post(config.runUrl, { command: command }, {
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(function (response) {
                if (response.data && response.data.success) {
                    Toast.fire({ icon: 'success', title: config.labels.commandExecuted || 'Command executed' });
                    fetchStatus();
                } else {
                    Toast.fire({ icon: 'error', title: response.data.message || 'Error' });
                }
            })
            .catch(function (err) {
                var msg = (err.response && err.response.data && err.response.data.message)
                    ? err.response.data.message : 'Error';
                Toast.fire({ icon: 'error', title: msg });
            })
            .finally(function () {
                btn.prop('disabled', false)
                   .html(
                       '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" ' +
                       'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
                       'class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/>' +
                       '<path d="M7 4v16l13 -8z"/></svg>' +
                       (config.labels.runNow || 'Run Now')
                   );
            });
        });
    });

    // -----------------------------------------------------------------------
    // View history modal — open + load page 1
    // -----------------------------------------------------------------------
    $(document).on('click', '.view-history-btn', function (e) {
        e.preventDefault();
        var command = $(this).data('command');
        var name = $(this).data('name');

        currentHistoryCommand = command;
        currentHistoryPage = 1;

        $('#history-modal-title').text(name + ' — ' + (config.labels.runHistory || 'Run History'));
        $('#history-total-count').text('');
        $('#history-modal').modal('show');

        loadHistory(command, 1);
    });

    // -----------------------------------------------------------------------
    // Copy command to clipboard
    // -----------------------------------------------------------------------
    $(document).on('click', '.copy-command-btn', function (e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        var input = document.getElementById(targetId);
        if (!input) return;

        navigator.clipboard.writeText(input.value).then(function () {
            Toast.fire({ icon: 'success', title: config.labels.copied || 'Copied!' });
        });
    });

    // -----------------------------------------------------------------------
    // History pagination click
    // -----------------------------------------------------------------------
    $(document).on('click', '#history-pagination a[data-page]', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page && currentHistoryCommand) {
            currentHistoryPage = page;
            loadHistory(currentHistoryCommand, page);
        }
    });

    // -----------------------------------------------------------------------
    // Show more / show less toggle for output
    // -----------------------------------------------------------------------
    $(document).on('click', '.toggle-output', function (e) {
        e.preventDefault();
        var row = $(this).closest('td');
        var shortEl = row.find('.output-short');
        var fullEl = row.find('.output-full');

        if (fullEl.is(':visible')) {
            fullEl.hide();
            shortEl.show();
        } else {
            shortEl.hide();
            fullEl.show();
        }
    });

    // -----------------------------------------------------------------------
    // Load history for a given command + page
    // -----------------------------------------------------------------------
    function loadHistory(command, page) {
        var tableBody = $('#history-table-body');
        var paginationEl = $('#history-pagination');

        tableBody.html('<tr><td colspan="6" class="text-center text-muted">' +
            (config.labels.loading || 'Loading...') + '</td></tr>');
        paginationEl.html('');

        axios.get(config.historyUrl, {
            params: { command: command, page: page },
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function (response) {
            if (!response.data || !response.data.success) {
                tableBody.html('<tr><td colspan="5" class="text-center text-muted">Error</td></tr>');
                return;
            }

            var paginator = response.data.data || {};
            var items = paginator.data || [];
            var total = paginator.total || 0;

            // Update count in modal header
            $('#history-total-count').text('(' + total + ' ' + (config.labels.totalRecords || 'records') + ')');

            if (items.length === 0) {
                tableBody.html('<tr><td colspan="6" class="text-center text-muted">' +
                    (config.labels.noHistory || 'No history found') + '</td></tr>');
                return;
            }

            var perPage = paginator.per_page || 15;
            var currentPage = paginator.current_page || 1;
            var html = '';
            items.forEach(function (item, index) {
                var badgeCls = item.status === 'success' ? 'bg-success-lt'
                    : item.status === 'failed' ? 'bg-danger-lt'
                    : item.status === 'running' ? 'bg-azure-lt' : 'bg-secondary-lt';

                // Format started_at
                var formattedDate = item.started_at ? formatDateTime(item.started_at) : '-';

                // Output with show more / show less
                var outputHtml = '-';
                if (item.output) {
                    var escaped = escapeHtml(item.output);
                    if (item.output.length > 80) {
                        outputHtml =
                            '<span class="output-short">' +
                                escapeHtml(item.output.substring(0, 80)) + '... ' +
                                '<a href="#" class="toggle-output text-primary small">' +
                                    (config.labels.showMore || 'Show more') +
                                '</a>' +
                            '</span>' +
                            '<span class="output-full" style="display:none;">' +
                                '<pre class="mb-0 small" style="white-space:pre-wrap;max-height:200px;overflow-y:auto;">' +
                                    escaped +
                                '</pre>' +
                                '<a href="#" class="toggle-output text-primary small">' +
                                    (config.labels.showLess || 'Show less') +
                                '</a>' +
                            '</span>';
                    } else {
                        outputHtml = escaped;
                    }
                }

                var rowNum = (currentPage - 1) * perPage + index + 1;

                html += '<tr>' +
                    '<td>' + rowNum + '</td>' +
                    '<td><span class="badge ' + badgeCls + '">' + escapeHtml(item.status) + '</span></td>' +
                    '<td>' + escapeHtml(item.triggered_by || '-') + '</td>' +
                    '<td class="text-nowrap">' + formattedDate + '</td>' +
                    '<td>' + (item.duration_ms !== null ? item.duration_ms + 'ms' : '-') + '</td>' +
                    '<td style="max-width:300px;">' + outputHtml + '</td>' +
                    '</tr>';
            });
            tableBody.html(html);

            // Render pagination
            renderPagination(paginationEl, paginator);
        })
        .catch(function (e) {
            console.error('Error loading history', e);
            tableBody.html('<tr><td colspan="6" class="text-center text-muted">Error loading history</td></tr>');
        });
    }

    // -----------------------------------------------------------------------
    // Pagination renderer
    // -----------------------------------------------------------------------
    function renderPagination(container, paginator) {
        var lastPage = paginator.last_page || 1;
        var currentPage = paginator.current_page || 1;

        if (lastPage <= 1) {
            container.html('');
            return;
        }

        var html = '<ul class="pagination pagination-sm justify-content-center m-0">';

        // Previous
        html += '<li class="page-item' + (currentPage <= 1 ? ' disabled' : '') + '">';
        html += '<a class="page-link" href="#" data-page="' + (currentPage - 1) + '">&laquo;</a>';
        html += '</li>';

        // Page numbers
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(lastPage, currentPage + 2);

        if (startPage > 1) {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if (startPage > 2) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for (var i = startPage; i <= endPage; i++) {
            html += '<li class="page-item' + (i === currentPage ? ' active' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
            html += '</li>';
        }

        if (endPage < lastPage) {
            if (endPage < lastPage - 1) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + lastPage + '">' + lastPage + '</a></li>';
        }

        // Next
        html += '<li class="page-item' + (currentPage >= lastPage ? ' disabled' : '') + '">';
        html += '<a class="page-link" href="#" data-page="' + (currentPage + 1) + '">&raquo;</a>';
        html += '</li>';

        html += '</ul>';

        container.html(html);
    }

    // -----------------------------------------------------------------------
    // Manual refresh button
    // -----------------------------------------------------------------------
    $('#refresh-status').on('click', function () {
        fetchStatus();
        countdown = REFRESH_INTERVAL;
    });

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatDateTime(dateStr) {
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;

        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var day = d.getDate();
        var month = months[d.getMonth()];
        var year = d.getFullYear();
        var hours = String(d.getHours()).padStart(2, '0');
        var minutes = String(d.getMinutes()).padStart(2, '0');
        var seconds = String(d.getSeconds()).padStart(2, '0');

        return month + ' ' + day + ', ' + year + ' ' + hours + ':' + minutes + ':' + seconds;
    }

    function timeAgo(dateStr) {
        var date = new Date(dateStr);
        var now = new Date();
        var seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return seconds + 's ago';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        var days = Math.floor(hours / 24);
        return days + 'd ago';
    }

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------
    $(document).ready(function () {
        startAutoRefresh();
    });
})();
