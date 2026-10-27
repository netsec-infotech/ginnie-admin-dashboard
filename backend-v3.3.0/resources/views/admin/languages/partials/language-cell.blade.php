<div class="d-flex align-items-center">
    @if(!empty($language->country_code))
        <span class="flag flag-xs flag-country-{{ strtolower($language->country_code) }} me-2 flex-shrink-0"></span>
    @endif
    <div>
        <div class="fw-bold" dir="{{ $language->direction }}">{{ $language->native_name }}</div>
        <div class="small text-muted">{{ $language->name }}</div>
    </div>
</div>

