@php
    $deadline = $deadline ?? null;
    $status = $status ?? null;
    $deadlineCarbon = $deadline ? \Carbon\Carbon::parse($deadline) : null;
    $nowForDeadline = $deadlineCarbon ? \Carbon\Carbon::now($deadlineCarbon->timezone) : null;
    $isExpired = $deadlineCarbon && $nowForDeadline->gte($deadlineCarbon);
@endphp

@if($deadlineCarbon)
    {{-- Seller-facing SLA hint; backend cron remains the source of enforcement. --}}
    <span class="order-acceptance-deadline {{ $isExpired ? 'is-expired' : '' }}"
          data-acceptance-deadline="{{ $deadlineCarbon->toIso8601String() }}"
          data-active-label="{{ __('labels.accept_within') }}"
          data-expired-label="{{ __('labels.acceptance_window_expired') }}"
          data-expired-text="{{ __('labels.expired') }}"
          title="{{ __('labels.accept_before') }} {{ $deadlineCarbon->format('M d, Y h:i A') }}">
        <i class="ti ti-hourglass-low me-1"></i>
        <span class="order-acceptance-deadline__label">
            {{ $isExpired ? __('labels.acceptance_window_expired') : __('labels.accept_within') }}
        </span>
        <span class="order-acceptance-deadline__time">
            {{ $isExpired ? __('labels.expired') : $deadlineCarbon->diffForHumans($nowForDeadline, true) }}
        </span>
    </span>
@endif
