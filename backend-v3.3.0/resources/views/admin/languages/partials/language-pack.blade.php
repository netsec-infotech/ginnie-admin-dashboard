<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title d-flex align-items-center gap-2">
                <i class="ti ti-package-import text-primary"></i>
                {{ __('labels.language_pack') }}
            </h3>
            <div class="text-muted mt-1">{{ __('labels.language_pack_subtitle') }}</div>
        </div>
    </div>
    <form
        action="{{ isset($surface) ? route('admin.languages.clients.import', [$surface->value, $language]) : route('admin.languages.import', $language) }}"
        method="POST"
        enctype="multipart/form-data"
        class="form-submit"
        data-redirect="{{ request()->fullUrl() }}"
    >
        @csrf
        <div class="card-body">
            <div class="alert alert-info d-flex align-items-start gap-2" role="note">
                <i class="ti ti-bulb fs-3"></i>
                <div>{{ __('labels.language_pack_ai_suggestion') }}</div>
            </div>
            <div class="mw-100">
                <label class="form-label required" for="language-pack">{{ __('labels.import_labels_json') }}</label>
                <input
                    id="language-pack"
                    type="file"
                    class="filepond @error('labels_file') is-invalid @enderror"
                    name="labels_file"
                    accept=".json,application/json"
                    data-language-pack
                    required
                >
                @error('labels_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer d-flex flex-column flex-sm-row justify-content-end gap-2">
            <a href="{{ isset($surface) ? route('admin.languages.clients.export', [$surface->value, $language]) : route('admin.languages.export', $language) }}" class="btn btn-outline-primary" data-language-download>
                <i class="ti ti-download"></i>
                {{ __('labels.export_labels_json') }}
            </a>
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-upload"></i>
                {{ __('labels.import') }}
            </button>
        </div>
    </form>
</div>

