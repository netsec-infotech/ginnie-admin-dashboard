@php use App\Models\Setting; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['categories']['active'] ?? '', 'sub_page' => $menuAdmin['categories']['route']['categories']['sub_active']])

@section('title', $pageMode === 'edit' ? 'Edit Category' : __('labels.create_category'))

@section('header_data')
    @php
        $page_title = $pageMode === 'edit' ? 'Edit Category' : __('labels.create_category');
        $page_pretitle = __('labels.categories');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.categories'), 'url' => route('admin.categories.index')],
        ['title' => $pageMode === 'edit' ? 'Edit Category' : __('labels.create_category'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="mb-3">
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
            @include('admin.categories._form', ['category' => $category, 'pageMode' => $pageMode])
        </div>
    </div>
@endsection
