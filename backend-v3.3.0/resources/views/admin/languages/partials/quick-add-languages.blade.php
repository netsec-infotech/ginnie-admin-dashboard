@if($quickAddLanguages !== [])
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ __('labels.quick_add_languages') }}</h3>
            <div class="text-muted">{{ __('labels.quick_add_languages_hint') }}</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($quickAddLanguages as $code => $definition)
                @php
                    $downloadRoute = isset($surface)
                        ? route('admin.languages.clients.quick-add.download', [$surface->value, $code])
                        : route('admin.languages.quick-add.download', $code);
                    $createRoute = isset($surface)
                        ? route('admin.languages.clients.create', ['surface' => $surface->value, 'quick_add' => $code])
                        : route('admin.languages.create', ['quick_add' => $code]);
                @endphp
                <div class="col-md-6 col-lg-4 col-xl">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-bold" dir="{{ $definition['direction'] }}">
                            {{ $definition['native_name'] }}
                        </div>
                        <div class="small text-muted mb-3" dir="ltr">
                            {{ $definition['name'] }} · {{ $code }} · {{ strtoupper($definition['direction']) }}
                        </div>
                        <div class="mb-3">
                            <span class="badge {{ $definition['has_translations'] ? 'bg-success-lt' : 'bg-secondary-lt' }}">
                                {{ $definition['has_translations'] ? __('labels.language_pack_included') : __('labels.language_pack_empty_template') }}
                            </span>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ $downloadRoute }}" class="btn btn-sm">
                                <i class="ti ti-download"></i>
                                {{ $definition['has_translations'] ? __('labels.download_translation_pack') : __('labels.download_empty_language_template') }}
                            </a>
                            @if($createPermission)
                                <a href="{{ $createRoute }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-plus"></i>
                                    {{ __('labels.quick_add') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

