<span class="badge bg-{{ $status->badgeClass() }}-lt">
    {{ $status->label() }}
</span>
@if($campaign->rejection_reason)
    <div class="text-muted" style="font-size:.7rem;" title="{{ $campaign->rejection_reason }}">
        {{ \Illuminate\Support\Str::limit($campaign->rejection_reason, 35) }}
    </div>
@endif
@if($campaign->force_stop_reason)
    <div class="text-danger" style="font-size:.7rem;" title="{{ $campaign->force_stop_reason }}">
        {{ \Illuminate\Support\Str::limit($campaign->force_stop_reason, 35) }}
    </div>
@endif
