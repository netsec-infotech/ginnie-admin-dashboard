@extends('layouts.admin.app', [
    'page' => $menuAdmin['products']['active'] ?? "",
    'sub_page' => $menuAdmin['products']['route']['products']['sub_active'] ?? '',
])
@php
    $title = empty($product) ? __('labels.add_product') : __('labels.edit_product');
@endphp
@section('title', $title)

@section('header_data')
    @php
        $page_title = $title;
        $page_pretitle = __('labels.admin') . ' ' . __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.products'), 'url' => route('admin.products.index')],
        ['title' => $title, 'url' => ''],
    ];
@endphp

@section('admin-content')
    @include('seller.products._form_body', [
        'isAdminMode' => true,
        'formAction' => empty($product)
            ? route('admin.products.store')
            : route('admin.products.update', ['id' => $product->id]),
        'indexUrl' => route('admin.products.index'),
        'selectedSellerId' => $product->seller_id ?? null,
        'selectedSellerName' => $product->seller?->user?->name ?? '',
    ])
@endsection

@include('seller.products._form_assets', ['isAdminMode' => true])
