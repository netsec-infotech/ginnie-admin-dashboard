@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['storage']['sub_active'] ?? "" ])

@section('title', __('labels.storage_settings'))

@section('header_data')
    @php
        $page_title = __('labels.storage_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.storage_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.storage_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <!-- BEGIN PAGE BODY -->
    <!-- BEGIN PAGE BODY -->
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link active" href="#pills-provider">Storage Provider</a>
                            <a class="nav-link" href="#pills-credentials">Cloud Credentials</a>
                            <a class="nav-link" href="#pills-sync">Media Migration & Sync</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">
                                @csrf
                                <input type="hidden" name="type" value="storage">

                                <div class="card mb-4" id="pills-provider">
                                    <div class="card-header">
                                        <h4 class="card-title">Active Catalog Storage Provider</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">Storage Provider</label>
                                            <select class="form-select" name="storageDisk" id="storageDiskSelect" required>
                                                <option value="local" {{ ($settings['storageDisk'] ?? 'local') == 'local' ? 'selected' : '' }}>Local Server Storage (Default)</option>
                                                <option value="s3" {{ ($settings['storageDisk'] ?? '') == 's3' ? 'selected' : '' }}>Amazon S3</option>
                                                <option value="r2" {{ ($settings['storageDisk'] ?? '') == 'r2' ? 'selected' : '' }}>Cloudflare R2 Object Storage</option>
                                            </select>
                                            <small class="form-hint">Select where e-commerce product photos, system branding logos, and catalog media will be stored.</small>

                                            <!-- [CHANGED BY AGENT]: Dynamic sync warning alert container -->
                                            <div id="sync-warning-alert" class="alert alert-warning mt-3 mb-0" style="display: none;">
                                                <div class="d-flex">
                                                    <div>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v2m0 4v.01" /><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75" /></svg>
                                                    </div>
                                                    <div>
                                                        <h4 class="alert-title">Storage Provider Changed</h4>
                                                        <div class="text-secondary">
                                                            You are changing the active storage provider. After saving, you <strong>must</strong> use the <a href="#pills-sync">Media Migration & Sync</a> tools below to migrate your existing files. If you do not, old files will still be accessible, but they will remain on the old provider.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-credentials">
                                    <div class="card-header">
                                        <h4 class="card-title">Storage Credentials & Configuration</h4>
                                    </div>
                                    <div class="card-body">
                                        <div id="section-local-notice" class="alert alert-info mb-0">
                                            <div class="d-flex">
                                                <div>
                                                    <h4 class="alert-title">Local Server Disk Active</h4>
                                                    <div class="text-muted">Catalog files will be saved directly to the server's public storage directory (<code>public/storage/</code>). No external API credentials are required.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="section-s3-fields" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.aws_access_key_id') }}</label>
                                                <input type="text" class="form-control" name="awsAccessKeyId" data-required-for="s3"
                                                       placeholder="{{ __('labels.aws_access_key_id_placeholder') }}"
                                                       value="{{ $isDemoUser ? Str::mask(($settings['awsAccessKeyId'] ?? '****'), '****', 3, 8) : ($settings['awsAccessKeyId'] ?? '') }}"/>
                                                <small class="form-hint">Enter your AWS IAM Access Key ID.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.aws_secret_access_key') }}</label>
                                                <input type="text" class="form-control" name="awsSecretAccessKey" data-required-for="s3"
                                                       placeholder="{{ __('labels.aws_secret_access_key_placeholder') }}"
                                                       value="{{ $isDemoUser ? Str::mask(($settings['awsSecretAccessKey'] ?? '****'), '****', 3, 8) : ($settings['awsSecretAccessKey'] ?? '') }}"/>
                                                <small class="form-hint">Enter your AWS IAM Secret Access Key.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.aws_region') }}</label>
                                                <input type="text" class="form-control" name="awsRegion" data-required-for="s3"
                                                       placeholder="e.g. us-east-1"
                                                       value="{{ $settings['awsRegion'] ?? '' }}"/>
                                                <small class="form-hint">Enter the AWS bucket region, for example us-east-1 or eu-west-1.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">{{ __('labels.aws_bucket') }}</label>
                                                <input type="text" class="form-control" name="awsBucket" data-required-for="s3"
                                                       placeholder="{{ __('labels.aws_bucket_placeholder') }}"
                                                       value="{{ $settings['awsBucket'] ?? '' }}"/>
                                                <small class="form-hint">Enter the exact AWS S3 bucket name.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ __('labels.aws_asset_url') }}</label>
                                                <input type="url" class="form-control" name="awsAssetUrl"
                                                       placeholder="https://bucket.s3.amazonaws.com or https://cdn.yourdomain.com"
                                                       value="{{ $settings['awsAssetUrl'] ?? '' }}"/>
                                                <small class="form-hint">Optional public URL or CDN domain used to serve files.</small>
                                            </div>
                                        </div>

                                        <div id="section-r2-fields" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label required">Cloudflare R2 Access Key ID</label>
                                                <input type="text" class="form-control" name="r2AccessKeyId" data-required-for="r2"
                                                       placeholder="Enter R2 Access Key ID"
                                                       value="{{ $isDemoUser ? Str::mask(($settings['r2AccessKeyId'] ?? '****'), '****', 3, 8) : ($settings['r2AccessKeyId'] ?? '') }}"/>
                                                <small class="form-hint">Enter the R2 access key from Cloudflare API tokens.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Cloudflare R2 Secret Access Key</label>
                                                <input type="text" class="form-control" name="r2SecretAccessKey" data-required-for="r2"
                                                       placeholder="Enter R2 Secret Access Key"
                                                       value="{{ $isDemoUser ? Str::mask(($settings['r2SecretAccessKey'] ?? '****'), '****', 3, 8) : ($settings['r2SecretAccessKey'] ?? '') }}"/>
                                                <small class="form-hint">Enter the matching R2 secret access key.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Cloudflare R2 Bucket</label>
                                                <input type="text" class="form-control" name="r2Bucket" data-required-for="r2"
                                                       placeholder="Enter R2 bucket name"
                                                       value="{{ $settings['r2Bucket'] ?? '' }}"/>
                                                <small class="form-hint">Enter the exact R2 bucket name.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Cloudflare R2 Public URL</label>
                                                <input type="url" class="form-control" name="r2AssetUrl"
                                                       placeholder="https://pub-xxxxx.r2.dev or https://cdn.yourdomain.com"
                                                       value="{{ $settings['r2AssetUrl'] ?? '' }}"/>
                                                <small class="form-hint">Optional public R2 URL or CDN domain used to serve files.</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Cloudflare R2 Endpoint URL</label>
                                                <input type="url" class="form-control" name="r2Endpoint" data-required-for="r2"
                                                       placeholder="https://<account_id>.r2.cloudflarestorage.com"
                                                       value="{{ $settings['r2Endpoint'] ?? '' }}"/>
                                                <small class="form-hint">Required S3-compatible API endpoint from Cloudflare R2.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-end">
                                        <div class="d-flex">
                                            @can('updateSetting', [\App\Models\Setting::class, 'storage'])
                                                <button type="submit"
                                                        class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <!-- [CHANGED BY AGENT]: Added Media Migration & Sync card with direct link to Cron Monitor for one-click execution -->
                            <div class="card mb-4" id="pills-sync">
                                <div class="card-header">
                                    <h4 class="card-title">Media Migration & Synchronization</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h5 class="mb-1">On-Demand Cloud Media Transfer</h5>
                                            <p class="text-muted mb-0 small">
                                                Transfer existing local media files to your configured cloud storage provider (<code>media:migrate-cloud</code>), or sync cloud files back to the local server (<code>media:sync-local</code>). Both commands can be monitored and executed on demand with a single click from the Cron Monitor dashboard.
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                            <a href="{{ route('admin.cron-monitor.index') }}" class="btn btn-outline-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/>
                                                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
                                                </svg>
                                                Open Cron Monitor & Run Sync
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/storage-settings.js') }}" defer></script>
@endpush
