@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['blogs']['sub_active'] ?? '',
])

@section('title', __('labels.edit_blog'))

@section('header_data')
    @php
        $page_title = __('labels.edit_blog');
        $page_pretitle = __('labels.edit');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.social_and_content'), 'url' => null],
        ['title' => __('labels.blogs'), 'url' => route('admin.blogs.index')],
        ['title' => __('labels.edit_blog'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <form action="{{ route('admin.blogs.update', $blog->id) }}" method="POST" enctype="multipart/form-data"
        class="form-submit">
        @csrf
        @method('PUT')
        <div class="row mb-3 align-items-center">
            <div class="col">
                <div class="page-pretitle">{{ __('labels.edit') }}</div>
                <h2 class="page-title">{{ __('labels.edit_blog') }}</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-outline-primary shadow-sm" id="open-ai-blog-modal-btn" data-bs-toggle="modal" data-bs-target="#ai-blog-modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-sparkles me-1 text-primary">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z" />
                    </svg>
                    {{ __('labels.generate_with_ai') }}
                </button>
            </div>
        </div>

        @include('admin.blogs._fields')

        <div class="row mt-3">
            <div class="col-12 text-end">
                <a href="{{ route('admin.blogs.index') }}" class="btn btn-secondary">
                    <i class="ti ti-arrow-left me-1"></i>{{ __('labels.cancel') }}
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-1"></i>{{ __('labels.save') }}
                </button>
            </div>
        </div>
    </form>

    @include('admin.blogs._ai_modal')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('blog-category-ids');
            if (categorySelect) {
                if (window.TomSelect) {
                    new TomSelect(categorySelect, {
                        plugins: ['remove_button'],
                        create: false,
                        maxItems: null,
                        placeholder: "{{ __('labels.select_categories') }}",
                    });
                } else if ($.fn.select2) {
                    $(categorySelect).select2({
                        placeholder: "{{ __('labels.select_categories') }}",
                        allowClear: true
                    });
                }
            }

            const productSelect = document.getElementById('blog-product-ids');
            if (productSelect) {
                if (window.TomSelect) {
                    new TomSelect(productSelect, {
                        plugins: ['remove_button'],
                        create: false,
                        maxItems: null,
                        placeholder: "{{ __('labels.select_linked_products') }}",
                    });
                } else if ($.fn.select2) {
                    $(productSelect).select2({
                        placeholder: "{{ __('labels.select_linked_products') }}",
                        allowClear: true
                    });
                }
            }

            const tagsSelect = document.getElementById('blog-tags');
            if (tagsSelect) {
                if (window.TomSelect) {
                    new TomSelect(tagsSelect, {
                        plugins: ['remove_button'],
                        create: true,
                        persist: false,
                        placeholder: "{{ __('labels.enter_or_select_tags') }}",
                    });
                } else if ($.fn.select2) {
                    $(tagsSelect).select2({
                        tags: true,
                        placeholder: "{{ __('labels.enter_or_select_tags') }}",
                        allowClear: true
                    });
                }
            }
        });
    </script>
@endpush
