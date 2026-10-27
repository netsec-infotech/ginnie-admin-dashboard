@php
    /** @var \Illuminate\Support\Collection $zones */
    $zones = $zones ?? collect();
@endphp
<div class="d-flex flex-wrap gap-1">
    @if($zones->isEmpty())
        <span class="badge bg-blue-lt text-uppercase">{{ __('labels.available_in_all_zones') }}</span>
    @else
        @foreach($zones as $zone)
            <span class="badge bg-indigo-lt">{{ $zone->name }}</span>
        @endforeach
    @endif
</div>
