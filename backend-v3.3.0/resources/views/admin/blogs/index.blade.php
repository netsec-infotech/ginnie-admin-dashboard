@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['blogs']['sub_active'] ?? '',
])

@section('title', __('labels.blogs'))

@section('header_data')
    @php
        $page_title = __('labels.blogs');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.social_and_content'), 'url' => null],
        ['title' => __('labels.blogs'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.blogs') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <select class="form-select" id="filter-category" style="min-width: 180px;">
                                    <option value="">{{ __('labels.all_categories') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <a href="{{ route('admin.blogs.create') }}" class="btn btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 5l0 14" />
                                        <path d="M5 12l14 0" />
                                    </svg>{{ __('labels.create_blog') }}
                                </a>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh" type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>{{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="blogs-table" :columns="$columns" route="{{ route('admin.blogs.index') }}"
                            :options="['order' => [[0, 'desc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = $('#blogs-table');

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

            // Category filter change
            $('#filter-category').on('change', function() {
                table.DataTable().ajax.reload();
            });

            // Pass category_id in DataTable request
            table.on('preXhr.dt', function(e, settings, data) {
                data.category_id = $('#filter-category').val();
            });

            // Toggle Status Handler
            $(document).on('change', '.toggle-status', function() {
                const checkbox = $(this);
                const url = checkbox.data('url');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message ||
                                @json(__('labels.blog_status_updated_successfully')));
                            table.DataTable().ajax.reload(null, false);
                        } else {
                            showToast('error', response.message ||
                                @json(__('messages.something_went_wrong')));
                            checkbox.prop('checked', !checkbox.prop('checked'));
                        }
                    },
                    error: function() {
                        showToast('error', @json(__('messages.something_went_wrong')));
                        checkbox.prop('checked', !checkbox.prop('checked'));
                    }
                });
            });

            // Toggle Editor's Pick Handler
            $(document).on('change', '.toggle-editors-pick', function() {
                const checkbox = $(this);
                const url = checkbox.data('url');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message ||
                                @json(__('labels.blog_editors_pick_status_updated')));
                            table.DataTable().ajax.reload(null, false);
                        } else {
                            showToast('error', response.message ||
                                @json(__('messages.something_went_wrong')));
                            checkbox.prop('checked', !checkbox.prop('checked'));
                        }
                    },
                    error: function() {
                        showToast('error', @json(__('messages.something_went_wrong')));
                        checkbox.prop('checked', !checkbox.prop('checked'));
                    }
                });
            });

            // Delete Handler
            $(document).on('click', '.delete-record', function() {
                const btn = $(this);
                const url = btn.data('url');

                Swal.fire({
                    title: @json(__('labels.are_you_sure')),
                    text: @json(__('labels.you_wont_be_able_to_revert_this')),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: @json(__('labels.yes_delete_it')),
                    cancelButtonText: @json(__('labels.cancel'))
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: url,
                            type: 'DELETE',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.success) {
                                    showToast('success', response.message ||
                                        @json(__('labels.blog_deleted_successfully'))
                                    );
                                    table.DataTable().ajax.reload(null, false);
                                } else {
                                    showToast('error', response.message ||
                                        @json(__('messages.something_went_wrong'))
                                    );
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ?
                                    xhr.responseJSON.message : @json(__('messages.something_went_wrong'));
                                showToast('error', errorMsg);
                            }
                        });
                    }
                });
            });
        });
    </script>
@endpush
