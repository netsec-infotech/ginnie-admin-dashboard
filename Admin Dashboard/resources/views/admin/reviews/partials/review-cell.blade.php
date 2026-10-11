<div class="review-copy-cell">
    <div class="fw-semibold">{{ $title ?: '-' }}</div>
    <div class="text-secondary small mt-1">{{ $body ? \Illuminate\Support\Str::limit($body, 110) : __('labels.no_review_message') }}</div>
</div>
