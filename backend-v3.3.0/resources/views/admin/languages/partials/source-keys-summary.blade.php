<div class="card h-100">
    <div class="card-header">
        <div>
            <h3 class="card-title d-flex align-items-center gap-2">
                <i class="ti ti-file-type-json text-primary"></i>
                {{ __('labels.translation_source_keys') }}
            </h3>
            <div class="text-muted mt-1">{{ __('labels.translation_source_keys_hint') }}</div>
        </div>
        <div class="card-actions d-flex align-items-center gap-2">
            <code dir="ltr">resources/language-sources/{{ $surface->value }}/en.json</code>
            <span class="badge bg-primary-lt">
                {{ __('labels.source_keys_count') }}: <span data-source-count>{{ $sourceCount }}</span>
            </span>
            @if($missingSourceKeys->isNotEmpty())
                <span class="badge bg-warning-lt">
                    {{ __('labels.missing') }}: {{ $missingSourceKeys->count() }}
                </span>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info d-flex align-items-start gap-2 mb-0" role="note">
            <i class="ti ti-info-circle fs-3"></i>
            <div>{{ __('labels.translation_source_keys_release_hint') }}</div>
        </div>
    </div>
    @if($missingSourceKeys->isNotEmpty())
        <div class="card-body border-top">
            <div class="alert alert-warning mb-3" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <i class="ti ti-alert-triangle fs-3"></i>
                    <div>
                        <div class="fw-semibold">{{ __('labels.source_labels_missing_values') }}</div>
                        <div>{{ __('labels.source_labels_missing_values_hint') }}</div>
                    </div>
                </div>
            </div>
            <div class="list-group list-group-flush border rounded">
                @foreach($missingSourceKeys as $key)
                    <div class="list-group-item">
                        <code dir="ltr">{{ $key }}</code>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
