<div class="review-rating-cell">
    <div class="review-rating-cell__stars">
        @for($i = 1; $i <= 5; $i++)
            <i class="ti {{ $i <= $rating ? 'ti-star-filled text-warning' : 'ti-star text-secondary' }}"></i>
        @endfor
    </div>
    <span class="review-rating-cell__value">{{ $rating }}/5</span>
</div>
