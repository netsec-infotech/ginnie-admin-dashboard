@extends('layouts.seller.app', ['page' => $menuSeller['products']['active'] ?? "", 'sub_page' => $menuSeller['products']['route']['bulk_upload']['sub_active'] ?? null])

@section('title', __('labels.bulk_upload') . ' ' . __('labels.images'))

@section('header_data')
    @php
        $page_title = __('labels.bulk_upload') . ' ' . __('labels.images');
        $page_pretitle = __('labels.products');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('seller.dashboard')],
        ['title' => __('labels.products'), 'url' => route('seller.products.index')],
        ['title' => __('labels.bulk_upload') . ' ' . __('labels.images'), 'url' => null],
    ];
@endphp

@section('seller-content')
<div class="row row-cards">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div>
          <h3 class="card-title">{{ __('labels.bulk_upload') }} {{ __('labels.images') }}</h3>
          <x-breadcrumb :items="$breadcrumbs"/>
        </div>
        <div class="card-actions">
            <a href="{{ route('seller.products.bulk-upload.page') }}" class="btn btn-outline-secondary">
                {{ __('labels.back') ?? 'Back' }}
            </a>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-12">
            <div class="alert alert-info" role="alert">
              <div class="alert-title">{{ __('labels.instructions') ?? 'Instructions' }}</div>
              <ul class="mb-2">
                <li>Upload a ZIP file containing product and variant images.</li>
                <li>Use product ID, product handle, or product title in the filename:
                  <ul>
                    <li>productKey.ext -> main image (e.g., 10.jpg, classic-t-shirt.jpg)</li>
                    <li>productKey-n.ext -> additional images (e.g., 10-1.jpg, classic-t-shirt-2.png)</li>
                    <li>productKey_variantId.ext -> variant image (e.g., 10_101.webp)</li>
                  </ul>
                </li>
                <li>Supported extensions: jpg, jpeg, png, webp. Max ZIP size: 200MB.</li>
                <li>Only existing products/variants will be processed. Others will be listed as failed.</li>
              </ul>
            </div>
          </div>
          <div class="col-12 d-none" id="images-upload-warning">
            <div class="alert alert-warning" role="alert">
              <div class="alert-title">Previous upload interrupted</div>
              The previous images upload appears to have been interrupted before it started. Please select the ZIP and try again.
            </div>
          </div>
          <div class="col-12">
            <form id="images-upload-form" enctype="multipart/form-data" Method="POST" action="{{ route('seller.products.images-upload') }}">
              @csrf
              <label class="form-label">Images ZIP</label>
              <input id="images-zip" type="file" name="images-zip" class="form-control" required>
              <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="images-upload-submit">
                  <span class="upload-text">{{ __('labels.upload') }}</span>
                  <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
                <a href="{{ route('seller.products.bulk-upload.page') }}" class="btn btn-outline-secondary">{{ __('labels.bulk_upload') }} {{ __('labels.products') }}</a>
              </div>
            </form>
          </div>
        </div>

        <div class="mt-3 d-none" id="images-upload-progress">
          <div class="d-flex justify-content-between mb-1">
            <span>Processing images...</span>
            <span id="images-progress-text">0%</span>
          </div>
          <div class="progress">
            <div id="images-progress-bar" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>

        <div class="mt-4 d-none" id="images-upload-result">
          <div class="alert alert-success" id="images-success" role="alert"></div>
          <div class="alert alert-danger d-none" id="images-failed" role="alert"></div>
          <div class="table-responsive d-none" id="images-failed-table-wrap">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ __('labels.title') }}</th>
                  <th>{{ __('labels.error') }}</th>
                </tr>
              </thead>
              <tbody id="images-failed-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
    <script>
        window.BULK_IMG_UPLOAD_CFG = {
            uploadUrl: "{{ route('seller.products.images-upload') }}",
            statusBaseUrl: "{{ route('seller.products.images-upload.status', ['token' => 'TOKEN']) }}".replace('TOKEN',''),
            csrf: "{{ csrf_token() }}",
            i18n: {
                upload_success_count: "{{ __('labels.upload_success_count') }}",
                upload_failed_count: "{{ __('labels.upload_failed_count') }}",
                some_rows_failed: "{{ __('labels.some_rows_failed') }}",
                upload_failed_generic: "{{ __('labels.upload_failed_generic') ?? 'Upload failed' }}",
                unexpected_error: "{{ __('labels.unexpected_error') ?? 'Unexpected error occurred' }}"
            }
        };
    </script>
    <script src="{{ asset('assets/js/product-images-upload.js') }}" defer></script>
@endpush
