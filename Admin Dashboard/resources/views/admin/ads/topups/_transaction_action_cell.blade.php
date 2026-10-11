@if((string) ($transaction->status ?? '') === \App\Enums\Wallet\WalletTransactionStatusEnum::PENDING())
    @include('admin.ads.topups._actions', [
        'id' => $transaction->id,
        'sellerName' => $transaction->user?->name ?? 'N/A',
        'amount' => $amount,
    ])
@else
    <span class="text-muted small">-</span>
@endif
