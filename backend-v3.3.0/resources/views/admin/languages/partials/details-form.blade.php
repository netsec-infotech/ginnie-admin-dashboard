<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ $editing ? __('labels.edit_language') : __('labels.add_language') }}</h3>
            <x-breadcrumb :items="$breadcrumbs"/>
        </div>
    </div>
    <form
        action="{{ isset($surface) ? ($editing ? route('admin.languages.clients.update', [$surface->value, $language]) : route('admin.languages.clients.store', $surface->value)) : ($editing ? route('admin.languages.update', $language) : route('admin.languages.store')) }}"
        method="POST"
        class="form-submit"
        data-language-details-form
    >
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label required" for="language-code">{{ __('labels.code') }}</label>
                    <input
                        id="language-code"
                        class="form-control @error('code') is-invalid @enderror"
                        name="code"
                        value="{{ old('code', $language->code) }}"
                        maxlength="20"
                        pattern="[a-z]{2,3}(-[A-Za-z0-9]{2,8})*"
                        placeholder="en, pt-BR"
                        dir="ltr"
                        @readonly($editing)
                        required
                    >
                    <small class="text-muted">{{ __('labels.language_code_hint') }}</small>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="language-name">{{ __('labels.name') }}</label>
                    <input
                        id="language-name"
                        class="form-control @error('name') is-invalid @enderror"
                        name="name"
                        value="{{ old('name', $language->name) }}"
                        maxlength="80"
                        dir="auto"
                        required
                    >
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="language-native-name">{{ __('labels.native_name') }}</label>
                    <input
                        id="language-native-name"
                        class="form-control @error('native_name') is-invalid @enderror"
                        name="native_name"
                        value="{{ old('native_name', $language->native_name) }}"
                        maxlength="80"
                        dir="auto"
                        required
                    >
                    @error('native_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="language-country-code">{{ __('labels.country_flag') }}</label>
                    <select
                        id="language-country-code"
                        class="form-select @error('country_code') is-invalid @enderror"
                        name="country_code"
                        required
                    >
                        <option value="">{{ __('labels.select_country') }}</option>
                        @foreach($countries ?? [] as $country)
                            @php
                                $cCode = strtolower($country->iso2);
                                $isSelected = strtolower((string) old('country_code', $language->country_code)) === $cCode;
                            @endphp
                            <option value="{{ $cCode }}" @selected($isSelected)>
                                {{ $country->name }} ({{ strtoupper($country->iso2) }})
                            </option>
                        @endforeach
                    </select>
                    @error('country_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="language-direction">{{ __('labels.direction') }}</label>
                    <select
                        id="language-direction"
                        class="form-select @error('direction') is-invalid @enderror"
                        name="direction"
                        @disabled($language->code === 'en')
                        required
                    >
                        <option value="ltr" @selected(old('direction', $language->direction) === 'ltr')>LTR</option>
                        <option value="rtl" @selected(old('direction', $language->direction) === 'rtl')>RTL</option>
                    </select>
                    @if($language->code === 'en')
                        <input type="hidden" name="direction" value="ltr">
                    @endif
                    @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="language-sort-order">{{ __('labels.sort_order') }}</label>
                    <input
                        id="language-sort-order"
                        type="number"
                        min="0"
                        max="100000"
                        class="form-control @error('sort_order') is-invalid @enderror"
                        name="sort_order"
                        value="{{ old('sort_order', $language->sort_order) }}"
                        required
                    >
                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex align-items-center pt-3">
                    <div>
                        <label class="form-check form-switch mb-0">
                            @if($language->is_default)
                                <input type="hidden" name="is_default" value="1">
                                <input class="form-check-input" type="checkbox" checked disabled>
                            @else
                                <input type="hidden" name="is_default" value="0">
                                <input
                                    class="form-check-input @error('is_default') is-invalid @enderror"
                                    type="checkbox"
                                    name="is_default"
                                    value="1"
                                    @checked(old('is_default'))
                                >
                            @endif
                            <span class="form-check-label">{{ __('labels.default_language') }}</span>
                        </label>
                        <small class="text-muted d-block mt-1">{{ __('labels.default_language_hint') }}</small>
                        @error('is_default')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="{{ isset($surface) ? route('admin.languages.clients.show', $surface->value) : route('admin.languages.panel') }}" class="btn">{{ __('labels.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('labels.save') }}</button>
        </div>
    </form>
</div>

