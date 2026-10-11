@php
    use App\Enums\Wallet\WalletTransactionStatusEnum;
    $rawStatus = $transaction->payment_status ?? $transaction->status ?? '-';
    $txnStatus = is_callable($rawStatus) ? $rawStatus() : ($rawStatus->value ?? (string) $rawStatus);

    $rawCompleted = $completedStatus ?? 'completed';
    $completedVal = is_callable($rawCompleted) ? $rawCompleted() : ($rawCompleted->value ?? (string) $rawCompleted);

    $rawFailed = $failedStatus ?? 'failed';
    $failedVal = is_callable($rawFailed) ? $rawFailed() : ($rawFailed->value ?? (string) $rawFailed);

    $displayTitle = $paymentContext['title'] ?? 'Payment Summary';
    if (\Illuminate\Support\Str::startsWith($displayTitle, 'labels.')) {
        $displayTitle = \Illuminate\Support\Facades\Lang::has($displayTitle) ? __($displayTitle) : ucwords(str_replace(['labels.', '_'], ['', ' '], $displayTitle));
    }
@endphp
@extends('layouts.no-head-foot')

@section('title', $displayTitle)
@section('content')
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="." class="navbar-brand navbar-brand-autodark">
                @if(($systemSettings['demoMode'] ?? false))
                    <img
                        src="{{asset('logos/hyper-local-logo.png')}}"
                        alt="{{$systemSettings['appName'] ?? ""}}" width="150px">
                @else
                    <img
                        src="{{!empty($systemSettings['logo'])?$systemSettings['logo'] : asset('logos/hyper-local-logo.png')}}"
                        alt="{{$systemSettings['appName'] ?? ""}}" width="150px">
                @endif
            </a>
        </div>

        <div class="card card-md">
            <div class="card-body">
                <div class="text-center" style="font-size: 50px;">
                    @if(strtolower($txnStatus) === strtolower($completedVal))
                        <i class="ti ti-circle-check text-success"></i>
                    @elseif(strtolower($txnStatus) === strtolower($failedVal))
                        <i class="ti ti-circle-x text-danger"></i>
                    @else
                        <i class="ti ti-cancel text-warning"></i>
                    @endif
                    <h3 class="text-capitalize">{{ $txnStatus }}</h3>
                </div>
                <h2>{{ $displayTitle }}</h2>

                @foreach(($paymentContext['lineItems'] ?? []) as $item)
                    @php
                        $itemLabel = $item['label'] ?? '';
                        if (\Illuminate\Support\Str::startsWith($itemLabel, 'labels.')) {
                            $itemLabel = \Illuminate\Support\Facades\Lang::has($itemLabel) ? __($itemLabel) : ucwords(str_replace(['labels.', '_'], ['', ' '], $itemLabel));
                        }
                    @endphp
                    <div class="d-flex align-items-center justify-content-between mb-2 {{ $item['bold'] ?? false ? 'fw-bold' : '' }}">
                        <span>{{ $itemLabel }}:</span>
                        <span>{{ $item['value'] }}</span>
                    </div>
                @endforeach

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span>{{ \Illuminate\Support\Facades\Lang::has('labels.system_transaction_id') ? __('labels.system_transaction_id') : 'System Transaction ID' }}:</span>
                    <span>#{{ $transaction->id }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span>{{ \Illuminate\Support\Facades\Lang::has('labels.payment_id') ? __('labels.payment_id') : 'Payment ID' }}:</span>
                    <span>{{ $transaction->transaction_id ?? $transaction->transaction_reference ?? '-' }}</span>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span>{{ \Illuminate\Support\Facades\Lang::has('labels.status') ? __('labels.status') : 'Status' }}:</span>
                    <span class="fw-bold text-capitalize">{{ $txnStatus }}</span>
                </div>

                <hr class="my-3"/>

                <div class="d-flex align-items-center justify-content-between h3">
                    <span>{{ \Illuminate\Support\Facades\Lang::has('labels.amount') ? __('labels.amount') : 'Amount' }}</span>
                    <span>
                        {{ number_format((float)($transaction->amount ?? 0), 2) }}
                        {{ $systemSettings['currency'] ?? ($transaction->currency_code ?? $transaction->currency ?? '') }}
                    </span>
                </div>
                <div class="d-flex align-items-center justify-content-center">
                    <a href="{{ $paymentContext['backUrl'] ?? '#' }}" class="btn btn-primary">
                        {{ $paymentContext['backLabel'] ?? __('labels.go_back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
