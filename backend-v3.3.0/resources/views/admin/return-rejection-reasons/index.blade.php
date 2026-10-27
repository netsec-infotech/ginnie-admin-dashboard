@extends('layouts.admin.app', ['page' => $menuAdmin['returns']['active'] ?? 'returns', 'sub_page' => $menuAdmin['returns']['route']['return_rejection_reasons']['sub_active'] ?? 'return-rejection-reasons'])

@section('title', __('labels.return_rejection_reasons') ?? 'Return Rejection Reasons')
@section('header_data')
    @php
        $page_title =  __('labels.return_rejection_reasons') ?? 'Return Rejection Reasons';
        $page_pretitle = __('labels.list') ?? 'List';
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home') ?? 'Home', 'url' => route('admin.dashboard')],
        ['title' => __('labels.return_rejection_reasons') ?? 'Return Rejection Reasons', 'url' => '']
    ];
@endphp

@section('admin-content')
    <div class="page-body">
        <div class="row row-cards">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ __('labels.return_rejection_reasons') ?? 'Store Return Rejection Reasons' }}</h3>
                            <x-breadcrumb :items="$breadcrumbs"/>
                        </div>
                        <div class="card-actions">
                            <div class="row g-2">
                                <div class="col-auto">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rejection-reason-modal" onclick="resetRejectionForm()">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <line x1="12" y1="5" x2="12" y2="19"/>
                                            <line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                        {{ __('labels.add_rejection_reason') ?? 'Add Rejection Reason' }}
                                    </button>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-outline-primary" id="refresh" onclick="refreshRejectionReasonsTable()">
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
                            <x-datatable id="rejection-reasons-table" :columns="$columns"
                                         route="{{ route('admin.return-rejection-reasons.datatable') }}"
                                         :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Rejection Reason Modal -->
    <div class="modal modal-blur fade" id="rejection-reason-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejection-modal-title">{{ __('labels.add_rejection_reason') ?? 'Add Rejection Reason' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="form-submit" id="rejection-form" method="POST" action="{{ route('admin.return-rejection-reasons.store') }}">
                    @csrf
                    <input type="hidden" id="rejection_id" name="id">
                    <div id="admin-rejection-method-container"></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.title') ?? 'Rejection Reason Title' }}</label>
                            <input type="text" class="form-control" id="rejection_title" name="title" required placeholder="{{ __('labels.enter_rejection_title') ?? 'e.g., Wrong Item Received' }}">
                        </div>

                        <!-- Comment Required Toggle Switch -->
                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="comment_required" name="comment_required" value="1">
                                <span class="form-check-label fw-medium">{{ __('labels.comment_required') ?? 'Comment Required on Rejection' }}</span>
                            </label>
                            <small class="form-hint text-muted">When enabled, seller must enter a detailed comment when selecting this rejection reason.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('labels.status') ?? 'Status' }}</label>
                            <select class="form-select" id="rejection_status" name="status">
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
                            {{ __('labels.save') ?? 'Save Rejection Reason' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function resetRejectionForm() {
            document.getElementById('rejection-modal-title').innerText = '{{ __("labels.add_rejection_reason") ?? "Add Rejection Reason" }}';
            document.getElementById('rejection-form').action = "{{ route('admin.return-rejection-reasons.store') }}";
            document.getElementById('admin-rejection-method-container').innerHTML = '';
            document.getElementById('rejection_id').value = '';
            document.getElementById('rejection_title').value = '';
            document.getElementById('comment_required').checked = false;
            document.getElementById('rejection_status').value = 'active';
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

        function refreshRejectionReasonsTable() {
            if (window.DatatableUtils && typeof window.DatatableUtils.refreshDatatable === 'function') {
                window.DatatableUtils.refreshDatatable('rejection-reasons-table');
            } else if ($.fn.DataTable && $.fn.DataTable.isDataTable('#rejection-reasons-table')) {
                $('#rejection-reasons-table').DataTable().ajax.reload(null, false);
            }
        }

        window.refreshRejectionReasonsTable = refreshRejectionReasonsTable;

        function editRejectionReason(buttonElement) {
            try {
                const data = JSON.parse(buttonElement.getAttribute('data-reason'));
                document.getElementById('rejection-modal-title').innerText = '{{ __("labels.edit_rejection_reason") ?? "Edit Rejection Reason" }}';
                document.getElementById('rejection-form').action = "/admin/return-rejection-reasons/" + data.id;
                document.getElementById('admin-rejection-method-container').innerHTML = '<input type="hidden" name="_method" value="PUT">';
                document.getElementById('rejection_id').value = data.id;
                document.getElementById('rejection_title').value = data.title || '';
                document.getElementById('comment_required').checked = Boolean(data.comment_required);
                document.getElementById('rejection_status').value = data.status || 'active';
                
                openAppModal('rejection-reason-modal');
            } catch (e) {
                console.error('Error parsing rejection reason data:', e);
            }
        }

        function deleteRejectionReason(id) {
            Swal.fire({
                title: "Are you sure?",
                text: "You are about to delete this rejection reason.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: (typeof primaryColor !== 'undefined' ? primaryColor : '#206bc4'),
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete("/admin/return-rejection-reasons/" + id, {
                        headers: { 'X-CSRF-TOKEN': csrfToken }
                    }).then(function(res) {
                        if (res.data && res.data.success) {
                            Swal.fire("Deleted!", res.data.message || "Rejection reason deleted successfully.", "success");
                            refreshRejectionReasonsTable();
                        } else {
                            Swal.fire("Error!", res.data ? res.data.message : "Error deleting rejection reason.", "error");
                        }
                    }).catch(function(err) {
                        Swal.fire("Error!", "There was a problem deleting the rejection reason.", "error");
                    });
                }
            });
        }
    </script>
@endsection
