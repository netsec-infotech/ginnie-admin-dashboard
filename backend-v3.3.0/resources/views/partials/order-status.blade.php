@php
    $statusString = strtolower((string) $status);
    $badgeColor = match (true) {
        str_contains($statusString, 'cancelled') || str_contains($statusString, 'rejected') || str_contains($statusString, 'no refund') || str_contains($statusString, 'failed') || str_contains($statusString, 'expired') => 'bg-danger-lt text-danger',
        str_contains($statusString, 'requested') || str_contains($statusString, 'pending') || str_contains($statusString, 'unassigned') || str_contains($statusString, 'awaiting_store_response') || str_contains($statusString, 'under admin review') || str_contains($statusString, 'due today') || str_contains($statusString, 'awaiting inspection') => 'bg-warning-lt text-warning',
        str_contains($statusString, 'assigned') || str_contains($statusString, 'out for pickup') || str_contains($statusString, 'picked up') || str_contains($statusString, 'pickup approved') || str_contains($statusString, 'delivered to seller') => 'bg-info-lt text-info',
        default => 'bg-success-lt text-success',
    };
@endphp
<div>
    <span class="badge {{ $badgeColor }} text-uppercase">{{ Str::replace("_", " ", $status) }}</span>
</div>
