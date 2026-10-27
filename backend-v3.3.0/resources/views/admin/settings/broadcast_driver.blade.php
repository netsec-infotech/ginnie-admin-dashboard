@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? '', 'sub_page' => $menuAdmin['settings']['route']['broadcast_driver']['sub_active'] ?? ''])

@section('title', __('labels.broadcast_driver'))

@section('header_data')
    @php
        $page_title = __('labels.broadcast_driver');
        $page_pretitle = __('labels.admin').' '.__('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.broadcast_driver'), 'url' => null],
    ];
    $driver = $settings['broadcastDriver'] ?? 'reverb';
    $hasReverbSecret = !empty($settings['reverbSecret']);
    $hasPusherSecret = !empty($settings['pusherSecret']);
    $allowedOrigins = collect(preg_split('/[\s,]+/', (string) ($settings['allowedOrigins'] ?? 'localhost')))
        ->map(fn ($origin) => trim($origin))
        ->filter()
        ->unique()
        ->values();
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.broadcast_driver') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <form id="broadcast-settings" action="{{ route('admin.settings.store') }}" method="post" class="form-submit"
                  data-test-url="{{ route('admin.settings.broadcast-driver.test') }}"
                  data-configured="{{ ($broadcastConfigured ?? false) ? '1' : '0' }}"
                  data-admin-id="{{ auth()->id() }}"
                  data-realtime='@json($realtime ?? [])'
                  data-connecting-message="{{ __('labels.broadcast_test_connecting') }}"
                  data-success-title="{{ __('labels.broadcast_test_socket_success') }}"
                  data-success-message="{{ __('labels.broadcast_test_socket_success_text', ['driver' => '__DRIVER__']) }}"
                  data-save-before-testing="{{ __('labels.broadcast_save_before_testing') }}"
                  data-configuration-required="{{ __('labels.broadcast_configuration_required') }}">
                @csrf
                <input type="hidden" name="type" value="broadcast_driver">
                <input type="hidden" name="redirect_url" value="{{ route('admin.settings.show', ['setting' => 'broadcast_driver']) }}">

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.broadcast_driver_settings') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary">{{ __('labels.broadcast_driver_hint') }}</p>
                        <label class="form-label required" for="broadcast-driver">{{ __('labels.broadcast_driver_settings') }}</label>
                        <select class="form-select" id="broadcast-driver" name="broadcastDriver" required>
                            <option value="reverb" @selected($driver === 'reverb')>{{ __('labels.broadcast_driver_reverb') }}</option>
                            <option value="pusher" @selected($driver === 'pusher')>{{ __('labels.broadcast_driver_pusher') }}</option>
                        </select>
                    </div>
                </div>

                <div class="card mb-4 broadcast-driver-panel" id="reverb-settings" data-broadcast-section="reverb">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.reverb_section_heading') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" role="alert">
                            {{ __('labels.reverb_dynamic_settings_notice') }}
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label required" for="allowed-origins-select">{{ __('labels.broadcast_allowed_origins') }}</label>
                                <select class="form-select reverb-required" id="allowed-origins-select" multiple
                                        data-placeholder="Add a host and press Enter">
                                    @foreach($allowedOrigins as $origin)
                                        <option value="{{ $origin }}" selected>{{ $origin }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" id="allowed-origins" name="allowedOrigins" value="{{ $allowedOrigins->implode("\n") }}">
                                <small class="form-hint">{{ __('labels.broadcast_allowed_origins_tooltip') }}</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">{{ __('labels.reverb_app_id') }}</label>
                                <input class="form-control reverb-required" name="reverbAppId" value="{{ $settings['reverbAppId'] ?? '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">{{ __('labels.reverb_key') }}</label>
                                <input class="form-control reverb-required" name="reverbKey" value="{{ $settings['reverbKey'] ?? '' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">{{ __('labels.reverb_secret') }}</label>
                                <input type="password" class="form-control reverb-required" name="reverbSecret"
                                       data-has-secret="{{ $hasReverbSecret ? '1' : '0' }}" autocomplete="new-password"
                                       value="{{ $isDemoUser && $hasReverbSecret ? '••••••••' : ($settings['reverbSecret'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.reverb_host') }}</label>
                                <input class="form-control reverb-required" name="reverbHost" value="{{ $settings['reverbHost'] ?? '' }}" placeholder="ws.example.com">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required">{{ __('labels.reverb_port') }}</label>
                                <input type="number" min="1" max="65535" class="form-control reverb-required" name="reverbPort" value="{{ $settings['reverbPort'] ?? 443 }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">{{ __('labels.reverb_scheme') }}</label>
                                <select class="form-select reverb-required" name="reverbScheme">
                                    <option value="https" @selected(($settings['reverbScheme'] ?? 'https') === 'https')>HTTPS / WSS</option>
                                    <option value="http" @selected(($settings['reverbScheme'] ?? 'https') === 'http')>HTTP / WS</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.reverb_server_host') }}</label>
                                <input class="form-control reverb-required" name="reverbServerHost" value="{{ $settings['reverbServerHost'] ?? '0.0.0.0' }}">
                                <small class="form-hint">{{ __('labels.reverb_server_host_tooltip') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.reverb_server_port') }}</label>
                                <input type="number" min="1" max="65535" class="form-control reverb-required" name="reverbServerPort" value="{{ $settings['reverbServerPort'] ?? 8080 }}">
                                <small class="form-hint">{{ __('labels.reverb_server_port_tooltip') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 broadcast-driver-panel" id="pusher-settings" data-broadcast-section="pusher">
                    <div class="card-header">
                        <h3 class="card-title">Pusher Channels</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                            <i class="ti ti-info-circle fs-2 flex-shrink-0"></i>
                            <div>{{ __('labels.pusher_client_events_hint') }}</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.pusher_app_id') }}</label>
                                <input class="form-control pusher-required" name="pusherAppId" value="{{ $settings['pusherAppId'] ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.pusher_key') }}</label>
                                <input class="form-control pusher-required" name="pusherKey" value="{{ $settings['pusherKey'] ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.pusher_secret') }}</label>
                                <input type="password" class="form-control pusher-required" name="pusherSecret"
                                       data-has-secret="{{ $hasPusherSecret ? '1' : '0' }}" autocomplete="new-password"
                                       value="{{ $isDemoUser && $hasPusherSecret ? '••••••••' : ($settings['pusherSecret'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">{{ __('labels.pusher_cluster') }}</label>
                                <input class="form-control pusher-required" name="pusherCluster" value="{{ $settings['pusherCluster'] ?? 'mt1' }}" placeholder="ap2">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer px-0 d-flex justify-content-between gap-2">
                    @can('updateSetting', [\App\Models\Setting::class, 'broadcast_driver'])
                        <button id="broadcast-test" type="button" class="btn btn-outline-primary">
                            <i class="ti ti-plug-connected me-1"></i>{{ __('labels.broadcast_test_button') }}
                        </button>
                        <button type="submit" class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/broadcast-settings.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/pusher/pusher.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/laravel-echo/echo.iife.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/broadcast-settings.js') }}"></script>
@endpush
