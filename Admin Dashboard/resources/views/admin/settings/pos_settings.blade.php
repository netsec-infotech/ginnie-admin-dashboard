@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['pos_settings']['sub_active'] ?? "" ])

@section('title', __('labels.pos_settings'))

@section('header_data')
    @php
        $page_title = __('labels.pos_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.pos_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.pos_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-general">{{ __('labels.pos_general') }}</a>
                            <a class="nav-link" href="#pills-receipt">{{ __('labels.pos_receipt_settings') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="pos_settings">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.pos_general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                {{ __('labels.pos_walkin_user_id') }}
                                                <x-form-info-tooltip :title="__('labels.pos_walkin_user_id_tooltip')" />
                                            </label>
                                            <input type="text" class="form-control" name="walkin_user_id"
                                                   value="{{ $settings['walkin_user_id'] ?? '' }}" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-receipt">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.pos_receipt_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                {{ __('labels.pos_default_receipt_footer') }}
                                                <x-form-info-tooltip :title="__('labels.pos_default_receipt_footer_tooltip')" />
                                            </label>
                                            <textarea class="form-control" name="default_receipt_footer" rows="4"
                                                      placeholder="{{ __('labels.pos_default_receipt_footer') }}">{{ $settings['default_receipt_footer'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'pos_settings'])
                                            <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
