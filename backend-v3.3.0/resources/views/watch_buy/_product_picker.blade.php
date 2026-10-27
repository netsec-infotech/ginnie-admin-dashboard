@php
    $pickerId = $pickerId ?? 'watch-buy-products';
    $primaryPickerId = $pickerId.'-primary';
    $selectedAttachments = array_map(
        'intval',
        old('attachment_ids', array_column($selectedOptions ?? [], 'id'))
    );
    $primaryAttachment = (int) old(
        'primary_attachment_id',
        $selected['primary_attachment_id'] ?? 0
    );
@endphp

<div class="mt-3">
    <h4>{{ __('labels.watch_buy_product_attachments') }}</h4>
    <p class="text-secondary">{{ __('labels.watch_buy_product_attachments_hint') }}</p>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <label class="form-label {{ $required ? 'required' : '' }}">{{ __('labels.products') }}</label>
            <select class="form-select watch-buy-reel-products" name="attachment_ids[]"
                id="{{ $pickerId }}"
                data-endpoint="{{ $endpoint }}"
                data-selected='@json($selectedAttachments)'
                data-placeholder="{{ __('labels.watch_buy_search_eligible_products') }}"
                data-preload-limit="15"
                data-max-items="{{ (int) ($maxItems ?? ($config['maxProductsPerReel'] ?? 1)) }}"
                data-primary-select="#{{ $primaryPickerId }}"
                @if(!empty($sourceSelector)) data-source-selector="{{ $sourceSelector }}" @endif
                @if(!empty($profileSelector)) data-profile-selector="{{ $profileSelector }}" @endif
                multiple @required($required)></select>
            <small class="form-hint">{{ __('labels.watch_buy_product_search_hint') }}</small>
            @error('attachment_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('attachment_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        @if($primary)
            <div class="col-lg-4 mb-3">
                <label class="form-label required">{{ __('labels.watch_buy_primary_product') }}</label>
                <select class="form-select" name="primary_attachment_id" id="{{ $primaryPickerId }}"
                    data-selected="{{ $primaryAttachment ?: '' }}"
                    data-placeholder="{{ __('labels.watch_buy_select_primary_product') }}" required>
                    <option value="">{{ __('labels.watch_buy_select_primary_product') }}</option>
                </select>
                <small class="form-hint">{{ __('labels.watch_buy_primary_product_hint') }}</small>
                @error('primary_attachment_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        @endif
    </div>
</div>

@once
    @push('scripts')
        <script src="{{ hyperAsset('assets/js/watch-buy-product-picker.js') }}" defer></script>
    @endpush
@endonce
