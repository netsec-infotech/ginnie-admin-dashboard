@php use App\Enums\Advertisement\AdCampaignStatusEnum; @endphp
<div class="d-flex justify-content-start align-items-center">
    @if($status === AdCampaignStatusEnum::PENDING_APPROVAL)
        @if($approvePermission ?? false)
            <a href="javascript:void(0);" class="btn btn-outline-success me-2 p-1 btn-campaign-approve"
               data-id="{{ $campaign->id }}"
               data-url="{{ route('admin.ads.campaigns.action', $campaign->id) }}"
               title="{{ __('labels.ad_campaign_approve') }}"
               data-bs-toggle="tooltip" data-bs-placement="top">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="icon icon-tabler icons-tabler-outline icon-tabler-check m-0">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M5 12l5 5l10 -10"/>
                </svg>
            </a>
        @endif
        @if($rejectPermission ?? false)
            <a href="javascript:void(0);" class="btn btn-outline-danger me-2 p-1 btn-campaign-reject"
               data-id="{{ $campaign->id }}"
               data-url="{{ route('admin.ads.campaigns.action', $campaign->id) }}"
               data-bs-toggle="modal" data-bs-target="#rejectModal"
               title="{{ __('labels.ad_campaign_reject') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="icon icon-tabler icons-tabler-outline icon-tabler-x m-0">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M18 6l-12 12"/>
                    <path d="M6 6l12 12"/>
                </svg>
            </a>
        @endif
    @endif

    @if(!in_array($status, [AdCampaignStatusEnum::REJECTED, AdCampaignStatusEnum::COMPLETED, AdCampaignStatusEnum::FORCE_STOPPED]) && ($forceStopPermission ?? false))
        <a href="javascript:void(0);" class="btn btn-outline-red me-2 p-1 btn-campaign-force-stop"
           data-id="{{ $campaign->id }}"
           data-url="{{ route('admin.ads.campaigns.action', $campaign->id) }}"
           data-bs-toggle="modal" data-bs-target="#forceStopModal"
           title="{{ __('labels.ad_campaign_force_stop') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="icon icon-tabler icons-tabler-outline icon-tabler-hand-stop m-0">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M8 13v-7.5a1.5 1.5 0 0 1 3 0v6.5"/>
                <path d="M11 5.5v-2a1.5 1.5 0 1 1 3 0v8.5"/>
                <path d="M14 5.5a1.5 1.5 0 0 1 3 0v6.5"/>
                <path d="M17 7.5a1.5 1.5 0 0 1 3 0v8.5a6 6 0 0 1 -6 6h-2h.208a6 6 0 0 1 -5.012 -2.7a69.74 69.74 0 0 1 -.196 -.3c-.312 -.479 -1.407 -2.388 -3.286 -5.728a1.5 1.5 0 0 1 .536 -2.022a1.867 1.867 0 0 1 2.28 .28l1.47 1.47"/>
            </svg>
        </a>
    @endif

    @php
        $hasAnyAction = false;
        if ($status === AdCampaignStatusEnum::PENDING_APPROVAL) {
            $hasAnyAction = ($approvePermission ?? false) || ($rejectPermission ?? false) || ($forceStopPermission ?? false);
        } elseif (!in_array($status, [AdCampaignStatusEnum::REJECTED, AdCampaignStatusEnum::COMPLETED, AdCampaignStatusEnum::FORCE_STOPPED])) {
            $hasAnyAction = ($forceStopPermission ?? false);
        }
    @endphp
    @unless($hasAnyAction)
        <span class="text-muted small">—</span>
    @endunless
</div>
