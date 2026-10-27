@if ($profile->photo_url)
    <a href="{{ $profile->photo_url }}" target="_blank" data-fslightbox="watch-buy-profiles">
        <span class="avatar avatar-md rounded-circle" style="background-image: url('{{ $profile->photo_url }}')"></span>
    </a>
@else
    <span class="avatar avatar-md rounded-circle bg-secondary-lt text-secondary">
        {{ strtoupper(substr($profile->username, 0, 1)) }}
    </span>
@endif
