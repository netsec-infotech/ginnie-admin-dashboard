@php
    $sellerName = $transaction->user?->name ?? 'N/A';
    $walletBalance = $metrics['wallet_balance'] ?? 0;
    $totalSpent = $metrics['total_spent'] ?? 0;
    $runningAds = $metrics['running_ads'] ?? 0;
@endphp

<div class="d-flex flex-column gap-1">
    <div class="fw-semibold">{{ e($sellerName) }}</div>
    <div class="text-muted small">{{ __('labels.ad_wallet_balance') }}: {{ $systemSettings['currencySymbol'] ?? '' }}{{ number_format((float) $walletBalance, 2) }}</div>
    <div class="text-muted small">{{ __('labels.spent') }}: {{ $systemSettings['currencySymbol'] ?? '' }}{{ number_format((float) $totalSpent, 2) }}</div>
    <div class="text-muted small">{{ __('labels.running_ads') }}: {{ (int) $runningAds }}</div>
</div>
