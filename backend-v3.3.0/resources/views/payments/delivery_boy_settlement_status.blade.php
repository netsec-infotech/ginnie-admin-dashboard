@extends('layouts.no-head-foot')

@section('title', $paymentContext['title'] ?? 'Settlement Status')
@section('content')
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="." class="navbar-brand navbar-brand-autodark">
                <img src="{{ !empty($systemSettings['logo']) ? $systemSettings['logo'] : asset('logos/hyper-local-logo.png') }}"
                     alt="{{ $systemSettings['appName'] ?? 'Logo' }}"
                     style="max-width: 160px; max-height: 60px; object-fit: contain;">
            </a>
        </div>

        <div class="card card-md">
            <div class="card-body text-center">
                @if($settlement->status === 'completed')
                    <div class="mb-3">
                        <span class="avatar avatar-lg bg-success-lt">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M9 12l2 2l4 -4" /></svg>
                        </span>
                    </div>
                    <h3>Settlement Successful!</h3>
                    <p class="text-muted">Your cash collection balance has been updated successfully.</p>
                @elseif($settlement->status === 'failed')
                    <div class="mb-3">
                        <span class="avatar avatar-lg bg-danger-lt">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M10 10l4 4m0 -4l-4 4" /></svg>
                        </span>
                    </div>
                    <h3>Settlement Failed</h3>
                    <p class="text-muted">Something went wrong with the payment transaction. Please try again.</p>
                @else
                    <div class="mb-3">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <h3>Payment Verification Pending</h3>
                    <p class="text-muted">We are verifying your payment status. Please do not close this window.</p>
                @endif

                <div class="hr-text">Summary</div>

                @foreach(($paymentContext['lineItems'] ?? []) as $item)
                    <div class="d-flex align-items-center justify-content-between mb-2 {{ $item['bold'] ?? false ? 'fw-bold' : '' }}">
                        <span>{{ $item['label'] }}:</span>
                        <span>{{ $item['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    
    @if($settlement->status === 'pending')
        @push('scripts')
            <script>
                // Auto-refresh every 3 seconds to check if status gets updated by Webhook
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            </script>
        @endpush
    @endif
@endsection
