<div class="review-user-cell">
    <div class="fw-semibold">{{ $review->user?->name ?? 'N/A' }}</div>
    <div class="text-secondary small">{{ $review->user?->email ?? '-' }}</div>
    @if($review->user?->mobile)
        <div class="text-secondary small">{{ $review->user->mobile }}</div>
    @endif
</div>
