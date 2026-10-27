<div class="card">
    <div class="card-header d-block">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <h3 class="card-title">{{ __('labels.translations') }}</h3>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-success-lt">
                    {{ __('labels.translated') }}: <span data-translated-count>{{ $translatedCount }}</span>
                </span>
                <span class="badge bg-warning-lt">
                    {{ __('labels.missing') }}: <span data-missing-count>{{ $missingCount }}</span>
                </span>
                <span class="badge bg-secondary-lt">
                    {{ __('labels.total') }}: <span data-source-count>{{ $sourceCount }}</span>
                </span>
            </div>
        </div>
    </div>
    <form action="{{ $translationsUpdateRoute ?? route('admin.languages.translations.update', $language) }}"
        method="POST" class="form-submit" data-language-translation-form>
        @csrf
        <div class="card-table">
            <div class="card-actions mt-3 ms-3">
                <div class="row g-2">
                    <div class="col-auto">
                        <label class="visually-hidden"
                            for="translation-key-search">{{ __('labels.search_key') }}</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <i class="ti ti-search"></i>
                            </span>
                            <input id="translation-key-search" type="search" class="form-control"
                                placeholder="{{ __('labels.search_key') }}" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-auto">
                        <label class="visually-hidden"
                            for="translation-status">{{ __('labels.status_filter') }}</label>
                        <select id="translation-status" class="form-select">
                            <option value="">{{ __('labels.all_statuses') }}</option>
                            <option value="missing">{{ __('labels.missing') }}</option>
                            <option value="translated">{{ __('labels.translated') }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row w-full p-3">
                <x-datatable id="language-translations-table" :columns="$translationColumns"
                    route="{{ $translationsDatatableRoute ?? route('admin.languages.translations.datatable', $language) }}"
                    class="table table-striped table-bordered table-vcenter w-100" :options="['order' => [[0, 'asc']], 'pageLength' => 25, 'lengthMenu' => [10, 25, 50, 100]]" />
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary" type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-device-floppy">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2" />
                    <path d="M10 14a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                    <path d="M14 4l0 4l-6 0l0 -4" />
                </svg>
                {{ __('labels.save_current_translation_page') }}
            </button>
        </div>
    </form>
</div>
