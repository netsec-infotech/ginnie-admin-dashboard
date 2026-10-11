@extends('layouts.admin.app', ['page' => $menuAdmin['returns']['active'] ?? 'returns', 'sub_page' => $menuAdmin['returns']['route']['return_reasons']['sub_active'] ?? 'return-reasons'])

@section('title', __('labels.return_reasons') ?? 'Return Reasons')
@section('header_data')
    @php
        $page_title =  __('labels.return_reasons') ?? 'Return Reasons';
        $page_pretitle = __('labels.list') ?? 'List';
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home') ?? 'Home', 'url' => route('admin.dashboard')],
        ['title' => __('labels.return_reasons') ?? 'Return Reasons', 'url' => '']
    ];
@endphp

@section('admin-content')
    <div class="page-body">
        <div class="row row-cards">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('labels.return_reasons') ?? 'Global Return Reasons' }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                        <div class="card-actions">
                            <div class="row g-2">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#return-reason-modal" onclick="resetReasonForm()">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <line x1="12" y1="5" x2="12" y2="19"/>
                                            <line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                        {{ __('labels.add_reason') ?? 'Add Return Reason' }}
                                    </button>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-outline-primary" id="refresh" onclick="refreshReasonsTable()">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                        </svg>
                                        {{ __('labels.refresh') ?? 'Refresh' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-table">
                        <div class="row w-full p-3">
                            <x-datatable id="reasons-table" :columns="$columns"
                                         route="{{ route('admin.return-reasons.datatable') }}"
                                         :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Reason Modal -->
    <div class="modal modal-blur fade" id="return-reason-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reason-modal-title">{{ __('labels.add_reason') ?? 'Add Return Reason' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="form-submit" id="reason-form" method="POST" action="{{ route('admin.return-reasons.store') }}">
                    @csrf
                    <input type="hidden" id="reason_id" name="id">
                    <div id="admin-reason-method-container"></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.title') ?? 'Reason Title' }}</label>
                            <input type="text" class="form-control" id="title" name="title" required placeholder="{{ __('labels.enter_reason_title') ?? 'e.g., Wrong Item Received' }}">
                        </div>

                        {{-- Return Flow Fix: Handle mandatory proof independently of return reason configuration. --}}

                        <!-- OTP Required Toggle Switch -->
                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="otp_required" name="otp_required" value="1">
                                <span class="form-check-label fw-medium">{{ __('labels.otp_required') ?? 'Require Rider Pickup OTP Verification' }}</span>
                            </label>
                            <small class="form-hint text-muted">When enabled, rider must verify customer OTP before marking the item picked up.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.status') ?? 'Status' }}</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            {{ __('labels.save') ?? 'Save Reason' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function resetReasonForm() {
            document.getElementById('reason-modal-title').innerText = '{{ __("labels.add_reason") ?? "Add Return Reason" }}';
            document.getElementById('reason-form').action = "{{ route('admin.return-reasons.store') }}";
            document.getElementById('admin-reason-method-container').innerHTML = '';
            document.getElementById('reason_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('otp_required').checked = false;
            document.getElementById('status').value = 'active';
        }

        function openAppModal(modalId) {
            var modalEl = document.getElementById(modalId);
            if (!modalEl) return;

            if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.modal === 'function') {
                window.jQuery(modalEl).modal('show');
                return;
            }
            if (typeof $ !== 'undefined' && typeof $.fn.modal === 'function') {
                $('#' + modalId).modal('show');
                return;
            }
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
                return;
            }

            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }

        function refreshReasonsTable() {
            if (window.DatatableUtils && typeof window.DatatableUtils.refreshDatatable === 'function') {
                window.DatatableUtils.refreshDatatable('reasons-table');
            } else if ($.fn.DataTable && $.fn.DataTable.isDataTable('#reasons-table')) {
                $('#reasons-table').DataTable().ajax.reload(null, false);
            }
        }

        window.refreshReasonsTable = refreshReasonsTable;

        function editReason(buttonElement) {
            try {
                const data = JSON.parse(buttonElement.getAttribute('data-reason'));
                document.getElementById('reason-modal-title').innerText = '{{ __("labels.edit_reason") ?? "Edit Return Reason" }}';
                document.getElementById('reason-form').action = "/admin/return-reasons/" + data.id;
                document.getElementById('admin-reason-method-container').innerHTML = '<input type="hidden" name="_method" value="PUT">';
                document.getElementById('reason_id').value = data.id;
                document.getElementById('title').value = data.title || '';
                document.getElementById('otp_required').checked = Boolean(data.otp_required);
                document.getElementById('status').value = data.status || 'active';
                
                openAppModal('return-reason-modal');
            } catch (e) {
                console.error('Error parsing return reason data:', e);
            }
        }

        function deleteReason(id) {
            Swal.fire({
                title: "Are you sure?",
                text: "You are about to delete this return reason.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: (typeof primaryColor !== 'undefined' ? primaryColor : '#206bc4'),
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete("/admin/return-reasons/" + id, {
                        headers: { 'X-CSRF-TOKEN': csrfToken }
                    }).then(function(res) {
                        if (res.data && res.data.success) {
                            Swal.fire("Deleted!", res.data.message || "Reason deleted successfully.", "success");
                            refreshReasonsTable();
                        } else {
                            Swal.fire("Error!", res.data ? res.data.message : "Error deleting reason.", "error");
                        }
                    }).catch(function(err) {
                        Swal.fire("Error!", "There was a problem deleting the return reason.", "error");
                    });
                }
            });
        }
    </script>
@endsection
