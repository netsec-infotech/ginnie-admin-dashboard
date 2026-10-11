<span class="badge bg-{{ $status->badgeClass() }}-lt">
    {{ $status->label() }}
</span>
@if($status === \App\Enums\Advertisement\AdCampaignStatusEnum::PENDING_APPROVAL)
    <div class="text-muted" style="font-size:.7rem;">
        {{ __('labels.ad_campaign_pending_note') }}
    </div>
@endif
@if($status === \App\Enums\Advertisement\AdCampaignStatusEnum::REJECTED && $campaign->rejection_reason)
    <div class="text-danger" style="font-size:.7rem;" title="{{ $campaign->rejection_reason }}">
        {{ \Illuminate\Support\Str::limit($campaign->rejection_reason, 40) }}
    </div>
@endif
@if($status === \App\Enums\Advertisement\AdCampaignStatusEnum::FORCE_STOPPED && $campaign->force_stop_reason)
    <div class="text-danger" style="font-size:.7rem;" title="{{ $campaign->force_stop_reason }}">
        {{ \Illuminate\Support\Str::limit($campaign->force_stop_reason, 40) }}
    </div>
@endif
