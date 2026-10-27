@php use App\Enums\Advertisement\AdCampaignStatusEnum; @endphp
<div class="d-flex justify-content-start align-items-center">
    @if($status === AdCampaignStatusEnum::RUNNING && ($pausePermission ?? false))
        <a href="javascript:void(0);" class="btn btn-outline-warning me-2 p-1 btn-pause-campaign"
           data-id="{{ $campaign->id }}"
           data-url="{{ route('seller.ads.campaigns.pause', $campaign->id) }}"
           title="{{ __('labels.ad_campaign_pause') }}"
           data-bs-toggle="tooltip" data-bs-placement="top">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-player-pause m-0">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M6 5m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v12a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z"/>
                <path d="M14 5m0 1a1 1 0 0 1 1 -1h2a1 1 0 0 1 1 1v12a1 1 0 0 1 -1 1h-2a1 1 0 0 1 -1 -1z"/>
            </svg>
        </a>
    @elseif($status === AdCampaignStatusEnum::PAUSED && ($pausePermission ?? false))
        <a href="javascript:void(0);" class="btn btn-outline-success me-2 p-1 btn-resume-campaign"
           data-id="{{ $campaign->id }}"
           data-url="{{ route('seller.ads.campaigns.resume', $campaign->id) }}"
           title="{{ __('labels.ad_campaign_resume') }}"
           data-bs-toggle="tooltip" data-bs-placement="top">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-player-play m-0">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M7 4v16l13 -8z"/>
            </svg>
        </a>
    @else
        <span class="text-muted small">—</span>
    @endif
</div>
