@extends('layouts.admin.app', ['page' => $menuAdmin['languages']['active'] ?? '', 'sub_page' => $menuAdmin['languages']['route']['panel']['sub_active'] ?? ''])

@php
    $editing = $language->exists;
@endphp
@section('title', $editing ? __('labels.edit_language') : __('labels.add_language'))

@section('header_data')
    @php
        $page_title = $editing ? __('labels.edit_language') : __('labels.add_language');
        $page_pretitle = $editing ? __('labels.update_details') : __('labels.create_new');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.languages_management'), 'url' => route('admin.languages.index')],
        ['title' => __('labels.panel_languages'), 'url' => route('admin.languages.panel')],
        ['title' => $editing ? __('labels.edit') : __('labels.create'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="row row-cards">
        <div class="col-12">
            @include('admin.languages.partials.details-form')
        </div>

        @if($editing && $language->code !== 'en')
            <div class="col-12">
                @include('admin.languages.partials.language-pack')
            </div>
            <div class="col-12">
                @include('admin.languages.partials.translations')
            </div>
        @elseif($editing)
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    {{ __('labels.language_english_file_managed') }}
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        window.languageLabels = {
            areYouSure: @json(__('labels.are_you_sure')),
            leaveWithoutSaving: @json(__('labels.language_leave_without_saving')),
            stayOnPage: @json(__('labels.language_stay_on_page')),
            unsavedChanges: @json(__('labels.unsaved_changes')),
            unsavedWarning: @json(__('labels.unsaved_changes_warning')),
        };
        window.languageOldTranslations = @json(
            collect(old('translations', []))->mapWithKeys(
                fn (array $row) => isset($row['key']) ? [$row['key'] => $row['value'] ?? ''] : []
            )
        );
    </script>
    <script src="{{ hyperAsset('assets/js/languages.js') }}" defer></script>
@endpush

