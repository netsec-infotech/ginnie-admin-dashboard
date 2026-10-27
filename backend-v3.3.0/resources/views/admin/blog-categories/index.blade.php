@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['blog_categories']['sub_active'] ?? '',
])

@section('title', __('labels.blog_categories'))

@section('header_data')
    @php
        $page_title = __('labels.blog_categories');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.social_and_content'), 'url' => null],
        ['title' => __('labels.blog_categories'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.blog_categories') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs" />
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            <div class="col-auto">
                                <button type="button" class="btn btn-primary" id="add-blog-category-btn" data-bs-toggle="modal" data-bs-target="#blog-category-modal">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M12 5l0 14" />
                                        <path d="M5 12l14 0" />
                                    </svg>{{ __('labels.add_category') }}
                                </button>
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
                        <x-datatable id="blog-categories-table" :columns="$columns" route="{{ route('admin.blog-categories.index') }}"
                            :options="['order' => [[5, 'asc']], 'pageLength' => 10]" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.blog-categories._modal')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = $('#blog-categories-table');
            const form = $('#blog-category-form');
            const modal = $('#blog-category-modal');
            const storeUrl = "{{ route('admin.blog-categories.store') }}";

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

            function showCategoryModal() {
                const modalEl = document.getElementById('blog-category-modal');
                if (!modalEl) return;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else if (typeof $ !== 'undefined' && typeof $('#blog-category-modal').modal === 'function') {
                    $('#blog-category-modal').modal('show');
                }
            }

            function hideCategoryModal() {
                const modalEl = document.getElementById('blog-category-modal');
                if (!modalEl) return;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const inst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                    inst.hide();
                } else if (typeof $ !== 'undefined' && typeof $('#blog-category-modal').modal === 'function') {
                    $('#blog-category-modal').modal('hide');
                }
            }

            // Open Add Modal
            $('#add-blog-category-btn').on('click', function() {
                form[0].reset();
                $('#blog-category-id').val('');
                $('#blog-category-method').val('POST');
                form.attr('action', storeUrl);
                $('#blog-category-modal-title').text(@json(__('labels.create_blog_category')));
                $('#blog-category-status').prop('checked', true);
                $('#blog-category-image').val('');
                $('#blog-category-image-preview-container').addClass('d-none');
                $('#blog-category-image-preview').attr('src', '');
                $('#blog-category-seo-title').val('');
                $('#blog-category-seo-keywords').val('');
                $('#blog-category-seo-description').val('');
            });

            // Edit Category
            $(document).on('click', '.edit-category-btn', function() {
                const btn = $(this);
                const url = btn.data('url');

                btn.prop('disabled', true);

                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        if (response.success && response.data) {
                            const data = response.data;
                            form[0].reset();
                            $('#blog-category-id').val(data.id);
                            $('#blog-category-method').val('POST');
                            form.attr('action', data.update_url);
                            $('#blog-category-modal-title').text(@json(__('labels.edit_blog_category')));

                            $('#blog-category-name').val(data.name);
                            $('#blog-category-slug').val(data.slug);
                            $('#blog-category-sort-order').val(data.sort_order);
                            $('#blog-category-description').val(data.description || '');
                            $('#blog-category-status').prop('checked', !!data.status);
                            $('#blog-category-image').val('');

                            if (data.image) {
                                $('#blog-category-image-preview').attr('src', data.image);
                                $('#blog-category-image-preview-container').removeClass('d-none');
                            } else {
                                $('#blog-category-image-preview-container').addClass('d-none');
                                $('#blog-category-image-preview').attr('src', '');
                            }

                            if (data.metadata) {
                                $('#blog-category-seo-title').val(data.metadata.seo_title || '');
                                const keywords = Array.isArray(data.metadata.seo_keywords) ? data.metadata.seo_keywords.join(', ') : (data.metadata.seo_keywords || '');
                                $('#blog-category-seo-keywords').val(keywords);
                                $('#blog-category-seo-description').val(data.metadata.seo_description || '');
                            } else {
                                $('#blog-category-seo-title').val('');
                                $('#blog-category-seo-keywords').val('');
                                $('#blog-category-seo-description').val('');
                            }

                            showCategoryModal();
                        } else {
                            showToast('error', response.message || @json(__('messages.something_went_wrong')));
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ?
                            xhr.responseJSON.message : @json(__('messages.something_went_wrong'));
                        showToast('error', errorMsg);
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });

            // Form Submit (AJAX with FormData)
            form.on('submit', function(e) {
                e.preventDefault();

                const submitBtn = $('#blog-category-submit-btn');
                const spinner = submitBtn.find('.spinner-border');
                const isEdit = $('#blog-category-id').val() !== '';
                const url = form.attr('action');

                const formData = new FormData(this);
                if (isEdit) {
                    formData.set('_method', 'PUT');
                }

                // Handle checkbox value
                formData.set('status', $('#blog-category-status').is(':checked') ? 1 : 0);

                // Handle SEO keywords split
                const keywordsVal = $('#blog-category-seo-keywords').val();
                if (keywordsVal) {
                    formData.delete('metadata[seo_keywords][]');
                    keywordsVal.split(',').map(s => s.trim()).filter(Boolean).forEach(kw => {
                        formData.append('metadata[seo_keywords][]', kw);
                    });
                }

                submitBtn.prop('disabled', true);
                spinner.removeClass('d-none');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message || @json(__('labels.blog_category_created_successfully')));
                            hideCategoryModal();
                            form[0].reset();
                            table.DataTable().ajax.reload(null, false);
                        } else {
                            showToast('error', response.message || @json(__('messages.something_went_wrong')));
                        }
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            const errors = xhr.responseJSON.errors;
                            Object.keys(errors).forEach(function(key) {
                                showToast('error', errors[key][0]);
                            });
                        } else {
                            showToast('error', @json(__('messages.something_went_wrong')));
                        }
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false);
                        spinner.addClass('d-none');
                    }
                });
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
                            showToast('success', response.message || @json(__('labels.blog_category_status_updated_successfully')));
                            table.DataTable().ajax.reload(null, false);
                        } else {
                            showToast('error', response.message || @json(__('messages.something_went_wrong')));
                            checkbox.prop('checked', !checkbox.prop('checked'));
                        }
                    },
                    error: function() {
                        showToast('error', @json(__('messages.something_went_wrong')));
                        checkbox.prop('checked', !checkbox.prop('checked'));
                    }
                });
            });

            // Delete Record Handler
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
                                    showToast('success', response.message || @json(__('labels.blog_category_deleted_successfully')));
                                    table.DataTable().ajax.reload(null, false);
                                } else {
                                    showToast('error', response.message || @json(__('messages.something_went_wrong')));
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
