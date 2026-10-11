@props([
    'metadata' => null,
    'titleAttr' => 'metadata[seo_title]',
    'keywordsAttr' => 'metadata[seo_keywords]',
    'descriptionAttr' => 'metadata[seo_description]',
])

@php
    $seo = is_array($metadata) ? $metadata : [];
    $seoTitle = old('metadata.seo_title', $seo['seo_title'] ?? '');
    $seoDescription = old('metadata.seo_description', $seo['seo_description'] ?? '');

    $rawKeywords = old('metadata.seo_keywords', $seo['seo_keywords'] ?? []);
    if (is_string($rawKeywords)) {
        $rawKeywords = array_filter(array_map('trim', explode(',', $rawKeywords)));
    }
    $seoKeywords = is_array($rawKeywords) ? array_values(array_filter($rawKeywords, fn ($k) => $k !== null && $k !== '')) : [];
@endphp

<div class="row">
    <div class="col-12">
        <h4 class="mb-3 mt-2">{{ __('labels.seo_information') }}</h4>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">{{ __('labels.seo_title') }}</label>
            <input type="text" class="form-control" name="{{ $titleAttr }}"
                   value="{{ $seoTitle }}"
                   placeholder="{{ __('labels.seo_title_placeholder') }}">
            @error('metadata.seo_title')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">{{ __('labels.seo_keywords') }}</label>
            <select class="form-select seo-keywords-select" name="{{ $keywordsAttr }}[]" multiple
                    placeholder="{{ __('labels.seo_keywords_placeholder') }}">
                @foreach($seoKeywords as $keyword)
                    <option value="{{ $keyword }}" selected>{{ $keyword }}</option>
                @endforeach
            </select>
            @error('metadata.seo_keywords')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @error('metadata.seo_keywords.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12">
        <div class="mb-3">
            <label class="form-label">{{ __('labels.seo_description') }}</label>
            <textarea class="form-control" name="{{ $descriptionAttr }}" rows="3"
                      placeholder="{{ __('labels.seo_description_placeholder') }}">{{ $seoDescription }}</textarea>
            @error('metadata.seo_description')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
