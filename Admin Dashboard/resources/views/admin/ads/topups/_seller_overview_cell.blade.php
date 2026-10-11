<div class="d-flex flex-column">
    <span class="fw-semibold">{{ $seller->user?->name ?? 'N/A' }}</span>
    <span class="text-secondary small">{{ $seller->user?->email ?? 'N/A' }}</span>
    @if(! empty($seller->user?->mobile))
        <span class="text-secondary small">{{ $seller->user->mobile }}</span>
    @endif
</div>
