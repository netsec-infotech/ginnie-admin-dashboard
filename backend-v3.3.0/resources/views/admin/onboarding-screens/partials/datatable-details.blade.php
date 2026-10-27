<div class="onboarding-row-details">
    @if ($screen->title)
        <p class="m-0 fw-medium text-primary">{{ $screen->title }}</p>
    @endif
    @if ($screen->subtitle)
        <p class="m-0 text-secondary">{{ \Illuminate\Support\Str::limit($screen->subtitle, 120) }}</p>
    @endif
    <p class="m-0 text-muted small">{{ __('labels.app_type') }}: {{ ucfirst($screen->app_type) }}</p>
</div>
