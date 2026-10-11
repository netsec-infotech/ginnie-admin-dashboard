@props([
    'module',
    'filters' => [],
])

@php
    $modalId = $module . '-export-modal';
    $exportUrl = route('admin.'.$module.'.bulk-upload.export');
    $countUrl = route('admin.'.$module.'.bulk-upload.export.count');
@endphp

<div class="modal modal-blur fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="{{ $modalId }}-form" method="GET" action="{{ $exportUrl }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('labels.export') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">{{ __('labels.bulk_upload_export_hint') }}</p>

                    @foreach($filters as $filter)
                        @php
                            $depends = $filter['depends_on'] ?? null;
                            $dependsAttr = $depends
                                ? 'data-depends-on="' . e($depends['key']) . '" data-depends-value="' . e(is_object($depends['value']) ? $depends['value']->value : $depends['value']) . '"'
                                : '';
                        @endphp
                        <div class="mb-3 export-filter-row" {!! $dependsAttr !!}>
                            <label class="form-label">{{ __($filter['label']) }}</label>

                            @if($filter['type'] === 'select')
                                <select name="filters[{{ $filter['key'] }}]" class="form-select">
                                    @foreach($filter['options'] as $opt)
                                        @php
                                            $val = is_object($opt['value']) ? $opt['value']->value : $opt['value'];
                                        @endphp
                                        <option value="{{ $val }}">{{ __($opt['label']) }}</option>
                                    @endforeach
                                </select>
                            @elseif($filter['type'] === 'select_remote')
                                <select name="filters[{{ $filter['key'] }}]" class="form-select export-remote-select"
                                        data-remote-url="{{ route($filter['route']) }}">
                                    <option value="">{{ __('labels.all') }}</option>
                                </select>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <span class="text-muted small" id="{{ $modalId }}-count-label">
                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"
                                  id="{{ $modalId }}-count-spinner"></span>
                            <span id="{{ $modalId }}-count-text">{{ __('labels.bulk_upload_export_count_loading') }}</span>
                        </span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="{{ $modalId }}-submit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2" width="24" height="24"
                             viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>
                            <path d="M12 17v-6"/>
                            <path d="M9.5 14.5l2.5 2.5l2.5 -2.5"/>
                        </svg>
                        {{ __('labels.download_csv') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            'use strict';
            document.addEventListener('DOMContentLoaded', function () {
                const modalId = @json($modalId);
                const countUrl = @json($countUrl);
                const i18n = {
                    loading:  @json(__('labels.bulk_upload_export_count_loading')),
                    matches:  @json(__('labels.bulk_upload_export_count_matches')),
                    failed:   @json(__('labels.bulk_upload_export_count_failed')),
                    no_match: @json(__('labels.bulk_upload_export_count_zero')),
                };
                const form = document.getElementById(modalId + '-form');
                const modalEl = document.getElementById(modalId);
                const countText = document.getElementById(modalId + '-count-text');
                const countSpinner = document.getElementById(modalId + '-count-spinner');
                const submitBtn = document.getElementById(modalId + '-submit');
                if (!form) return;

                // Show/hide rows whose depends_on condition is not met.
                function refreshDependentRows() {
                    form.querySelectorAll('.export-filter-row[data-depends-on]').forEach(function (row) {
                        const dependsKey = row.getAttribute('data-depends-on');
                        const expected = row.getAttribute('data-depends-value');
                        const parentSelect = form.querySelector('[name="filters[' + dependsKey + ']"]');
                        const matches = parentSelect && parentSelect.value === expected;
                        row.style.display = matches ? '' : 'none';
                        if (!matches) {
                            const innerControl = row.querySelector('select, input');
                            if (innerControl) innerControl.value = '';
                        }
                    });
                }

                // Lightweight Tom Select for remote category lookups (only if Tom Select is loaded).
                form.querySelectorAll('.export-remote-select').forEach(function (sel) {
                    if (typeof TomSelect === 'undefined') return;
                    new TomSelect(sel, {
                        valueField: 'id',
                        labelField: 'text',
                        searchField: 'text',
                        load: function (q, callback) {
                            if (!q.length) return callback();
                            axios.get(sel.getAttribute('data-remote-url'), { params: { q: q } })
                                .then(function (resp) { callback(resp.data || []); })
                                .catch(function () { callback(); });
                        },
                    });
                });

                // Live count — debounced refetch on any filter change.
                let countTimer = null;
                let lastReq = 0;
                function buildFilterParams() {
                    const params = {};
                    form.querySelectorAll('[name^="filters["]').forEach(function (el) {
                        const m = el.name.match(/^filters\[(.+)\]$/);
                        if (!m) return;
                        const key = m[1];
                        if (el.value !== '' && el.value != null) {
                            params['filters[' + key + ']'] = el.value;
                        }
                    });
                    return params;
                }
                function setCountText(txt) { if (countText) countText.textContent = txt; }
                function setBusy(busy) {
                    if (countSpinner) countSpinner.classList.toggle('d-none', !busy);
                }
                function refreshCount() {
                    if (!countText) return;
                    setBusy(true);
                    setCountText(i18n.loading);
                    const reqId = ++lastReq;
                    axios.get(countUrl, { params: buildFilterParams() })
                        .then(function (resp) {
                            if (reqId !== lastReq) return; // stale
                            const c = (resp && resp.data && resp.data.data && resp.data.data.count) || 0;
                            const tpl = c === 0 ? i18n.no_match : i18n.matches;
                            setCountText(tpl.replace(':count', c));
                            if (submitBtn) submitBtn.disabled = c === 0;
                        })
                        .catch(function () {
                            if (reqId !== lastReq) return;
                            setCountText(i18n.failed);
                        })
                        .finally(function () {
                            if (reqId === lastReq) setBusy(false);
                        });
                }
                function scheduleCount() {
                    clearTimeout(countTimer);
                    countTimer = setTimeout(refreshCount, 250);
                }

                form.addEventListener('change', function () {
                    refreshDependentRows();
                    scheduleCount();
                });
                // Recompute when the modal is opened (filters may have changed since last open).
                if (modalEl) {
                    modalEl.addEventListener('shown.bs.modal', function () {
                        refreshDependentRows();
                        refreshCount();
                    });
                }

                refreshDependentRows();
                refreshCount();
            });
        })();
    </script>
@endpush
