@props([
    'module',
    'createPermission' => true,
    'showExport' => true,
    'layout' => 'buttons',
])

{{--
    IMPORTANT: this component emits only the action TRIGGERS (buttons or
    dropdown items). The modal markup they target lives in
    <x-bulk-upload.modals module="..." /> — drop that once per page at the
    document root so Bootstrap can locate the modal by ID. Nesting a modal
    inside a <ul.dropdown-menu> breaks the HTML parser.
--}}

@if($layout === 'dropdown-items')
    {{-- Compact dropdown-item layout, designed to be placed inside <ul class="dropdown-menu"> --}}
    @if($createPermission)
        <li>
            <a class="dropdown-item" href="{{ route('admin.'.$module.'.bulk-upload.page') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-2" width="20" height="20"
                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/>
                    <path d="M7 9l5 -5l5 5"/>
                    <path d="M12 4l0 12"/>
                </svg>
                {{ __('labels.bulk_upload') }}
            </a>
        </li>
    @endif
    <li>
        <a class="dropdown-item" href="{{ route('admin.'.$module.'.bulk-upload.jobs.index') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-2" width="20" height="20"
                 viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                 stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M3 12a9 9 0 1 0 9 -9"/>
                <path d="M3 4.001v5h5"/>
                <path d="M12 7v5l3 3"/>
            </svg>
            {{ __('labels.bulk_upload_history') }}
        </a>
    </li>
    @if($showExport)
        <li>
            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#{{ $module }}-export-modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-2" width="20" height="20"
                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>
                    <path d="M12 17v-6"/>
                    <path d="M9.5 14.5l2.5 2.5l2.5 -2.5"/>
                </svg>
                {{ __('labels.export') }}
            </a>
        </li>
    @endif
@else
    @if($createPermission)
        <a href="{{ route('admin.'.$module.'.bulk-upload.page') }}" class="btn btn-outline-success">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/>
                <path d="M7 9l5 -5l5 5"/>
                <path d="M12 4l0 12"/>
            </svg>
            {{ __('labels.bulk_upload') }}
        </a>
    @endif

    <a href="{{ route('admin.'.$module.'.bulk-upload.jobs.index') }}" class="btn btn-outline-primary">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
             stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
            <path d="M3 12a9 9 0 1 0 9 -9"/>
            <path d="M3 4.001v5h5"/>
            <path d="M12 7v5l3 3"/>
        </svg>
        {{ __('labels.bulk_upload_history') }}
    </a>

    @if($showExport)
        <button type="button" class="btn btn-outline-info"
                data-bs-toggle="modal" data-bs-target="#{{ $module }}-export-modal">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>
                <path d="M12 17v-6"/>
                <path d="M9.5 14.5l2.5 2.5l2.5 -2.5"/>
            </svg>
            {{ __('labels.export') }}
        </button>
    @endif
@endif
