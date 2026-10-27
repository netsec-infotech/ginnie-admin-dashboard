@extends('layouts.admin.app', ['page' => $menuAdmin['products']['active'] ?? "", 'sub_page' => $menuAdmin['products']['route']['badges']['sub_active']])

@section('title', __('labels.badges'))

@section('header_data')
    @php
        $page_title = __('labels.badges');
        $page_pretitle = __('labels.list');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.badges'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">{{ __('labels.badges') }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                    </div>
                    <div class="card-actions">
                        <div class="row g-2">
                            @if($createPermission ?? false)
                                <div class="col-auto">
                                    <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal"
                                       data-bs-target="#badge-modal">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                             stroke-linecap="round" stroke-linejoin="round" class="icon icon-2">
                                            <path d="M12 5l0 14"/>
                                            <path d="M5 12l14 0"/>
                                        </svg>
                                        {{ __('labels.add_badge') }}
                                    </a>
                                </div>
                            @endif
                            <div class="col-auto">
                                <button class="btn btn-outline-primary" id="refresh">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                    </svg>
                                    {{ __('labels.refresh') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-table">
                    <div class="row w-full p-3">
                        <x-datatable id="badges-table" :columns="$columns"
                                     route="{{ route('admin.badges.datatable') }}"
                                     :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(($createPermission ?? false) || ($editPermission ?? false))
        <div class="modal modal-blur fade" id="badge-modal" tabindex="-1" role="dialog" aria-hidden="true"
             data-bs-backdrop="static">
            <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                <div class="modal-content">
                    <form class="form-submit" id="badge-form" action="{{ route('admin.badges.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="_method" id="badge-method" value="POST">
                        <input type="hidden" name="badge_id" id="badge-id" value="">

                        <div class="modal-header">
                            <h5 class="modal-title" id="badge-modal-title">{{ __('labels.create_badge') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.badge_name') }}
                                    <x-form-info-tooltip :title="__('labels.badge_enter_name')"/>
                                </label>
                                <input type="text" class="form-control" name="name" id="badge-name"
                                       placeholder="{{ __('labels.badge_enter_name') }}" required/>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.badge_label') }}
                                    <x-form-info-tooltip :title="__('labels.badge_enter_label')"/>
                                </label>
                                <input type="text" class="form-control" name="label" id="badge-label"
                                       placeholder="{{ __('labels.badge_enter_label') }}" required/>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label required">{{ __('labels.badge_bg_color') }}</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color"
                                               name="bg_color" id="badge-bg-color" value="#3b82f6" required/>
                                        <input type="text" class="form-control font-monospace" id="badge-bg-color-hex"
                                               value="#3b82f6" maxlength="7" placeholder="#000000"/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label required">{{ __('labels.badge_text_color') }}</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color"
                                               name="text_color" id="badge-text-color" value="#ffffff" required/>
                                        <input type="text" class="form-control font-monospace" id="badge-text-color-hex"
                                               value="#ffffff" maxlength="7" placeholder="#000000"/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ __('labels.badge_border_color') }}</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color"
                                               name="border_color" id="badge-border-color" value="#2563eb"/>
                                        <input type="text" class="form-control font-monospace" id="badge-border-color-hex"
                                               value="#2563eb" maxlength="7" placeholder="#000000"/>
                                    </div>
                                </div>
                            </div>

                            {{-- Live preview --}}
                            <div class="mb-2">
                                <label class="form-label">{{ __('labels.badge_preview') }}</label>
                                <div class="p-3 bg-light rounded d-flex align-items-center gap-3">
                                    <span id="badge-preview"
                                          style="background-color:#3b82f6;color:#ffffff;border:1px solid #2563eb;padding:4px 12px;border-radius:4px;font-size:0.8rem;font-weight:600;">
                                        Badge
                                    </span>
                                    <small class="text-muted">{{ __('labels.badge_preview') }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <a href="#" class="btn" data-bs-dismiss="modal">{{ __('labels.cancel') }}</a>
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                     stroke-linejoin="round" class="icon icon-2">
                                    <path d="M12 5l0 14"/>
                                    <path d="M5 12l14 0"/>
                                </svg>
                                <span id="badge-submit-label">{{ __('labels.create_badge') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        window.badgeLabels = {
            create: @json(__('labels.create_badge')),
            edit: @json(__('labels.edit_badge')),
            deleteConfirm: @json(__('labels.you_are_about_to_delete_this_badge', ['item' => __('labels.badge')])),
        };
    </script>
    <script src="{{ hyperAsset('assets/js/badges.js') }}" defer></script>
@endpush
