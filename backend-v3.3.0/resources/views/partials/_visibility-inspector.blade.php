@php
    /**
     * Empty shell for the visibility inspector.
     *
     * Hydrated by public/assets/js/visibility-inspector.js via
     * renderVisibilityInspector(targetEl, payload).
     *
     * Usage:
     *   <x-visibility-inspector ... />  (preferred wrapper if added later)
     *   @include('partials._visibility-inspector', ['endpoint' => route('admin.brands.visibility', $id)])
     */
    $endpoint = $endpoint ?? null;
@endphp
<div class="visibility-inspector card mb-3" data-visibility-inspector @if($endpoint) data-endpoint="{{ $endpoint }}" @endif hidden>
    <div class="card-header py-2 d-flex align-items-center">
        <h4 class="card-title m-0">
            {{ __('labels.visibility_check') }}
            <span class="badge ms-2" data-visibility-status></span>
        </h4>
        <button type="button" class="btn btn-sm btn-link ms-auto" data-visibility-refresh hidden>
            {{ __('labels.refresh') }}
        </button>
    </div>
    <div class="card-body">
        <ul class="list-unstyled m-0" data-visibility-checks></ul>
        <div class="mt-2 small" data-visibility-zone-summary></div>
        <div class="mt-2 small" data-visibility-problem-zones hidden></div>
    </div>
</div>
