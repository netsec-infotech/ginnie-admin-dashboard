@php
    $clientLanguages = $catalogLanguages;
    $defaultLocale = \App\Helpers\TranslationHelper::getDefaultLocale();
@endphp

@if (($createPermission ?? false) || ($editPermission ?? false))
    <div class="modal modal-blur fade" id="brand-modal" tabindex="-1" role="dialog" aria-hidden="true"
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <form class="form-submit" action="{{ route('admin.brands.store') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="brand_id" id="brand-id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('labels.create_brand') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('partials._visibility-inspector')

                        <div class="mb-3">
                            <label class="form-label required">
                                {{ __('labels.scope_type') }}
                                <x-form-info-tooltip :title="__('messages.brand_scope_type_info_message')" />
                            </label>
                            <select class="form-select" name="scope_type" id="scopeType">
                                <option value="">{{ __('labels.select_scope_type') }}</option>
                                @foreach (\App\Enums\HomePageScopeEnum::forBrands() as $scopeType)
                                    <option value="{{ $scopeType }}"
                                        {{ old('scope_type', 'global') == $scopeType ? 'selected' : '' }}>
                                        {{ ucfirst($scopeType) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('scope_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3" id="scopeCategoryField" style="display: none;">
                            <label class="form-label">{{ __('labels.scope_category') }}</label>
                            <select class="form-select" name="scope_id" id="select-root-category">
                                <option value="">{{ __('labels.select_category') }}</option>
                                @if (!empty($scopeCategory))
                                    <option value="{{ $scopeCategory->id }}" selected>{{ $scopeCategory->getTranslation('title', 'en', false) }}
                                    </option>
                                @endif
                            </select>
                            @error('scope_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <hr>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0 fw-bold">
                                {{ __('labels.brand_information') }}
                            </label>
                            @if(($aiTranslationEnabled ?? false) && $clientLanguages->count() > 1)
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary shadow-sm"
                                        id="brand-ai-translate-btn"
                                        data-source-required="{{ __('labels.ai_translation_source_required') }}"
                                        data-translating="{{ __('labels.ai_translating') }}"
                                        data-success="{{ __('labels.ai_translations_populated') }}"
                                        data-failed="{{ __('labels.ai_translation_request_failed') }}"
                                        data-idle="{{ __('labels.translate_with_ai') }}"
                                        title="{{ __('labels.translate_with_ai') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-sparkles me-1 text-primary">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z" />
                                    </svg>
                                    <span class="btn-text">{{ __('labels.translate_with_ai') }}</span>
                                    <span class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                                </button>
                            @endif
                        </div>

                        @if($clientLanguages->count() > 1)
                            <ul class="nav nav-tabs nav-fill mb-3" id="brand-language-tabs" role="tablist">
                                @foreach($clientLanguages as $lang)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $lang->code === $defaultLocale ? 'active fw-bold' : '' }}"
                                                id="brand-tab-{{ $lang->code }}"
                                                data-bs-toggle="tab"
                                                data-bs-target="#brand-pane-{{ $lang->code }}"
                                                type="button"
                                                role="tab"
                                                aria-controls="brand-pane-{{ $lang->code }}"
                                                aria-selected="{{ $lang->code === $defaultLocale ? 'true' : 'false' }}">
                                            @if(!empty($lang->flag_url))
                                                <img src="{{ $lang->flag_url }}" alt="" class="avatar avatar-xs rounded me-1" style="width: 16px; height: 12px; object-fit: cover;">
                                            @endif
                                            {{ $lang->name }}
                                            @if($lang->code === $defaultLocale)
                                                <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 0.65rem;">{{ __('labels.default') }}</span>
                                            @endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="tab-content" id="brand-language-tab-content">
                            @foreach($clientLanguages as $lang)
                                <div class="tab-pane fade {{ $lang->code === $defaultLocale ? 'show active' : '' }}"
                                     id="brand-pane-{{ $lang->code }}"
                                     role="tabpanel"
                                     aria-labelledby="brand-tab-{{ $lang->code }}">

                                    <div class="mb-3">
                                        <label class="form-label {{ $lang->code === $defaultLocale ? 'required' : '' }}">
                                            {{ __('labels.brand_name') }} ({{ $lang->name }})
                                        </label>
                                        <input type="text"
                                               class="form-control brand-title-input"
                                               name="title[{{ $lang->code }}]"
                                               data-locale="{{ $lang->code }}"
                                               dir="{{ $lang->direction ?? 'ltr' }}"
                                               placeholder="{{ __('labels.enter_brand_name') }}"
                                               maxlength="255"
                                               {{ $lang->code === $defaultLocale ? 'required' : '' }} />
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label {{ $lang->code === $defaultLocale ? 'required' : '' }}">
                                            {{ __('labels.description') }} ({{ $lang->name }})
                                        </label>
                                        <textarea class="form-control brand-desc-input"
                                                  name="description[{{ $lang->code }}]"
                                                  data-locale="{{ $lang->code }}"
                                                  dir="{{ $lang->direction ?? 'ltr' }}"
                                                  rows="3"
                                                  maxlength="255"
                                                  {{ $lang->code === $defaultLocale ? 'required' : '' }}
                                                  placeholder="{{ __('labels.enter_description') }}"></textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">{{ __('labels.logo') }}</label>
                            <input type="file" class="form-control" id="logo-upload" name="logo"
                                data-logo-url="" />
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="status" id="status-switch"
                                        value="active" checked>
                                    <label class="form-check-label"
                                        for="status-switch">{{ __('labels.status') }}</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <x-seo-fields />
                    </div>

                    <div class="modal-footer">
                        <a href="#" class="btn" data-bs-dismiss="modal">{{ __('labels.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="icon icon-2">
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg>
                            {{ __('labels.create_new_brand') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
