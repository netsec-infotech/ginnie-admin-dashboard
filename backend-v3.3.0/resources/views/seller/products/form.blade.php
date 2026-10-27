@extends('layouts.seller.app', [
    'page' => $menuSeller['products']['active'] ?? "",
    'sub_page' => $menuSeller['products']['route']['add_products']['sub_active']
])
@php
    $title = empty($product) ? __('labels.add_product') : __('labels.edit_product');
@endphp
@section('title', $title)

@section('header_data')
    @php
        $page_title = $title;
        $page_pretitle = __('labels.seller') . " " . __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.products'), 'url' => route('seller.products.index')],
        ['title' => $title, 'url' => '']
    ];
@endphp

@section('seller-content')
    @include('seller.products._form_body', [
        'isAdminMode' => false,
        'formAction' => empty($product)
            ? route('seller.products.store')
            : route('seller.products.update', ['id' => $product->id]),
        'indexUrl' => route('seller.products.index'),
    ])
@endsection

@include('seller.products._form_assets')
