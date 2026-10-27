@extends('layouts.admin.app', [
    'page' => $menuAdmin['social_and_content']['active'] ?? '',
    'sub_page' => $menuAdmin['social_and_content']['route']['stories']['sub_active'] ?? '',
])

@section('title', __('labels.watch_buy_statuses'))

@section('header_data')
    @php
        $page_title = __('labels.watch_buy_statuses');
        $page_pretitle = __('labels.watch_buy_status_gallery');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.stories'), 'url' => null],
    ];
    $statusesEnabled = $config['enabled'] && $config['statusesEnabled'];
@endphp

@section('admin-content')
    <div class="card mb-4">
        <div class="card-header watch-buy-status-toolbar">
            <div>
                <h3 class="card-title">{{ __('labels.watch_buy_status_gallery') }}</h3>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
            <div class="watch-buy-status-toolbar-actions">
                <form method="get" class="watch-buy-status-search">
                    <label class="visually-hidden"
                        for="watch-buy-status-search">{{ __('labels.watch_buy_search_seller_profiles') }}</label>
                    <select class="form-select watch-buy-status-profile-search" id="watch-buy-status-search" name="profile_id"
                        placeholder="{{ __('labels.watch_buy_search_seller_profiles') }}"
                        data-placeholder="{{ __('labels.watch_buy_search_seller_profiles') }}"
                        data-endpoint="{{ route('admin.watch-and-buy.profiles.options') }}">
                        @if ($selectedSearchProfile)
                            <option value="{{ $selectedSearchProfile->id }}" selected>
                                {{ '@' . $selectedSearchProfile->username . ' — ' . ($selectedSearchProfile->seller?->user?->name ?? '-') }}
                            </option>
                        @endif
                    </select>
                </form>
                <button type="button" class="btn btn-outline-primary watch-buy-status-refresh"
                    data-url="{{ route('admin.watch-and-buy.statuses.index') }}"
                    data-loading-text="{{ __('labels.loading') }}"
                    aria-label="{{ __('labels.refresh') }}">
                    <i class="ti ti-refresh me-1"></i>{{ __('labels.refresh') }}
                </button>
                @if ($statusesEnabled)
                    @can('watch_buy.status.create')
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal"
                            data-bs-target="#admin-watch-buy-status-composer">
                            <i class="ti ti-plus me-1"></i>{{ __('labels.watch_buy_add_status') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        @unless ($statusesEnabled)
            <div class="card-body">
                <div class="alert alert-warning mb-0">{{ __('labels.watch_buy_statuses_disabled') }}</div>
            </div>
        @endunless
    </div>

    <div id="watch-buy-status-gallery-container">
        @forelse ($profiles as $profile)
            <section class="watch-buy-status-profile-group mb-4">
                <div class="watch-buy-status-profile-header">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="watch-buy-status-profile-ring flex-shrink-0">
                            @if ($profile->photo_url)
                                <span class="avatar avatar-lg rounded-circle"
                                    style="background-image: url('{{ $profile->photo_url }}')"></span>
                            @else
                                <span
                                    class="avatar avatar-lg rounded-circle bg-primary-lt text-primary">{{ strtoupper(substr($profile->username, 0, 1)) }}</span>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <h3 class="card-title mb-1 text-truncate">{{ '@' . $profile->username }}</h3>
                            <div class="text-secondary small text-truncate">
                                {{ $profile->seller?->user?->name ?? __('labels.watch_buy_platform') }}
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-primary-lt watch-buy-status-group-count"
                        data-singular-label="{{ trans_choice('labels.watch_buy_active_status_count', 1, ['count' => ':count']) }}"
                        data-plural-label="{{ trans_choice('labels.watch_buy_active_status_count', 2, ['count' => ':count']) }}">
                        {{ trans_choice('labels.watch_buy_active_status_count', $profile->statuses->count(), ['count' => $profile->statuses->count()]) }}
                    </span>
                </div>

                @include('watch_buy._status_cards', [
                    'statuses' => $profile->statuses,
                    'profile' => $profile,
                    'deleteRoute' => 'admin.watch-and-buy.statuses.destroy',
                    'canDelete' => auth()->user()?->can('watch_buy.status.delete') ?? false,
                ])
            </section>
        @empty
            <div class="empty card py-5">
                <div class="empty-icon"><i class="ti ti-clock-hour-4"></i></div>
                <p class="empty-title">{{ __('labels.watch_buy_no_active_statuses') }}</p>
                <p class="empty-subtitle text-secondary">{{ __('labels.watch_buy_no_active_statuses_hint') }}</p>
            </div>
        @endforelse

        <div class="mt-3">{{ $profiles->links() }}</div>
    </div>

    @include('watch_buy._status_preview')

    @if ($statusesEnabled)
        @can('watch_buy.status.create')
            @include('watch_buy._status_composer_modal', [
                'modalId' => 'admin-watch-buy-status-composer',
                'action' => route('admin.watch-and-buy.statuses.store'),
                'endpoint' => route('admin.watch-and-buy.statuses.product-options'),
                'pickerId' => 'admin-story-products',
                'admin' => true,
            ])
        @endcan
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/watch-buy-status.css') }}">
@endpush

@push('scripts')
    <script>
        window.watchBuyStatusTranslations = {!! json_encode([
            'uploadHint' => __('labels.watch_buy_status_media_upload_hint'),
            'mediaUnavailable' => __('labels.watch_buy_status_media_unavailable'),
            'createdBy' => __('labels.created_by') . ':',
            'products' => __('labels.products') . ':',
            'expires' => __('labels.watch_buy_expires') . ':',
        ]) !!};
    </script>
    <script src="{{ asset('assets/js/watch-buy-status.js') }}" defer></script>

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('admin-watch-buy-status-composer')).show();
            });
        </script>
    @endif
@endpush
