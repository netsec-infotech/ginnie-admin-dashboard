@php
    $syncResult = session('language_pack_sync');
    $syncTexts = [
        'confirm' => __('labels.language_sync_confirm'),
        'submit' => __('labels.language_sync_title'),
        'cancel' => __('labels.cancel'),
        'running' => __('labels.language_sync_running'),
        'failed' => __('labels.language_sync_failed'),
        'counts' => [
            'inserted' => __('labels.language_sync_inserted'),
            'existing' => __('labels.language_sync_existing'),
            'skipped' => __('labels.language_sync_skipped'),
            'failed' => __('labels.language_sync_failed_count'),
        ],
        'statuses' => [
            'completed' => __('labels.language_sync_status_completed'),
            'skipped' => __('labels.language_sync_status_skipped'),
            'failed' => __('labels.language_sync_status_failed'),
        ],
    ];
@endphp
<div class="card" id="language-pack-sync" data-texts="{{ json_encode($syncTexts) }}">
    <div class="card-body">
        <h3 class="card-title">{{ __('labels.language_sync_title') }}</h3>
        <p class="text-muted">{{ __('labels.language_sync_hint') }}</p>
        <form method="POST" action="{{ route('admin.languages.sync-packs') }}" class="d-flex flex-wrap gap-2 align-items-end" id="language-pack-sync-form">
            @csrf
            <div>
                <label class="form-label" for="language-pack-sync-surface">{{ __('labels.language_sync_surface') }}</label>
                <select class="form-select" name="surface" id="language-pack-sync-surface">
                    <option value="all">{{ __('labels.language_sync_all') }}</option>
                    @foreach(\App\Enums\LanguageSurfaceEnum::cases() as $surface)
                        <option value="{{ $surface->value }}">{{ $surface->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary" type="submit">{{ __('labels.language_sync_title') }}</button>
        </form>
        @error('surface') <p class="text-danger mt-2">{{ $message }}</p> @enderror
        <div id="language-pack-sync-message" class="mt-3" role="status" aria-live="polite">{{ $syncResult['message'] ?? '' }}</div>
        <div id="language-pack-sync-totals" class="text-muted">
            @foreach(($syncResult['report']['totals'] ?? []) as $key => $count)
                {{ $syncTexts['counts'][$key] }}: {{ $count }}
            @endforeach
        </div>
    </div>
    <div class="table-responsive" id="language-pack-sync-results" @if(empty($syncResult['report'])) hidden @endif>
        <table class="table table-vcenter">
            <thead><tr>
                <th>{{ __('labels.language_sync_surface') }}</th><th>{{ __('labels.code') }}</th><th>{{ __('labels.status') }}</th>
                @foreach($syncTexts['counts'] as $label)<th>{{ $label }}</th>@endforeach
                <th>{{ __('labels.message') }}</th>
            </tr></thead>
            <tbody>
                @foreach(($syncResult['report']['results'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['surface'] }}</td><td>{{ $row['locale'] }}</td><td>{{ $syncTexts['statuses'][$row['status']] }}</td>
                        @foreach(array_keys($syncTexts['counts']) as $key)<td>{{ $row[$key] }}</td>@endforeach
                        <td>{{ $row['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@push('scripts')
    <script src="{{ asset('assets/js/language-pack-sync.js') }}" defer></script>
@endpush
