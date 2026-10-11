<div class="d-flex align-items-center gap-2">

    @if($campaign->product?->getFirstMediaUrl('main_image'))
        <span class="avatar avatar-sm rounded"
              style="background-image:url('{{ $campaign->product->getFirstMediaUrl('main_image') }}')"></span>
    @endif
    <div class="fw-medium"><a
            href="{{route('admin.products.show', ['id' => $campaign->product?->id])}}">{{ $campaign->product?->title ?? '—' }}</a>
    </div>
    <div class="text-muted small">#{{ $campaign->id }}</div>
</div>
