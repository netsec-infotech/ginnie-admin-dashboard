@props([
    'wrapperClass' => 'nav-item dropdown d-flex me-3',
    'buttonClass' => 'nav-link px-0',
])

@if(($availableLanguages ?? collect())->isNotEmpty())
    <div class="{{ $wrapperClass }}">
        <a href="#" class="{{ $buttonClass }}" data-bs-toggle="dropdown" tabindex="-1"
            aria-label="{{ __('labels.select_language') }}">
            <i class="ti ti-language fs-2"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            @foreach($availableLanguages as $language)
                <a href="{{ route('set.language', $language->code) }}"
                    class="dropdown-item {{ app()->getLocale() === $language->code ? 'active' : '' }}"
                    dir="{{ $language->direction }}">{{ $language->native_name }}</a>
            @endforeach
        </div>
    </div>
@endif
