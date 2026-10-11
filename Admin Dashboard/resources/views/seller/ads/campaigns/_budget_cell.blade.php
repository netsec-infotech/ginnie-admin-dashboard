@php
    $r = 14;
    $circ = round(2 * pi() * $r, 2);
    $offset = round($circ * (1 - $progress / 100), 2);
@endphp
<div class="d-flex align-items-center justify-content-center gap-2">
    <div class="donut-wrap">
        <svg class="donut-svg" width="40" height="40" viewBox="0 0 36 36">
            <circle class="donut-bg" cx="18" cy="18" r="{{ $r }}"/>
            <circle class="donut-fill" cx="18" cy="18" r="{{ $r }}"
                    stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}"/>
        </svg>
        <div class="donut-center">{{ $progress }}%</div>
    </div>
    <div class="text-start">
        <div class="fw-semibold small">
            {{ $systemSettings['currencySymbol'] ?? '' }}{{ number_format($campaign->budget, 2) }}
        </div>
        <div class="text-muted" style="font-size:.7rem;">
            {{ $systemSettings['currencySymbol'] ?? '' }}{{ number_format($campaign->spent, 2) }}
            {{ __('labels.spent') }}
        </div>
    </div>
</div>
