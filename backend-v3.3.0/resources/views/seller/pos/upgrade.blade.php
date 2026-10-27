@extends('layouts.seller.app', [
    'page' => $menuSeller['pos']['active'] ?? '',
    'sub_page' => $menuSeller['pos']['route']['pos_overview']['sub_active'] ?? '',
])

@section('title', __('labels.pos'))

@section('header_data')
    @php
        $page_title = __('labels.pos');
        $page_pretitle = __('labels.seller') . ' ' . __('labels.pos');
    @endphp
@endsection

@section('seller-content')
    <div class="container-xl">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-6 col-md-8">
                <div class="card card-md">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-lg text-muted" width="64"
                                 height="64" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                 fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12"/>
                                <path d="M20 12v4h-4a2 2 0 0 1 0 -4h4"/>
                            </svg>
                        </div>
                        <h2 class="mb-2">{{ __('labels.pos_not_available') }}</h2>
                        <p class="text-secondary mb-0">{{ __('labels.pos_not_in_plan') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
