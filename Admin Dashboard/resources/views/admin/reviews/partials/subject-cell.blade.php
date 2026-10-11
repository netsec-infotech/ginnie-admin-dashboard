<div class="review-subject-cell">
    @if($subject['link'])
        <a href="{{ $subject['link'] }}" class="review-subject-cell__title">{{ $subject['title'] }}</a>
    @else
        <div class="review-subject-cell__title">{{ $subject['title'] }}</div>
    @endif

    @if($subject['meta'])
        <div class="review-subject-cell__meta">{{ $subject['meta'] }}</div>
    @endif
</div>
