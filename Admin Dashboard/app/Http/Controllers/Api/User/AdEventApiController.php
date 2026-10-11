<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advertisement\BulkAdEventRequest;
use App\Services\AdEventService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group('Ads')]
class AdEventApiController extends Controller
{
    public function __construct(
        protected AdEventService $adEventService,
    ) {
    }

    /**
     * Record a batch of click events for ad campaigns.
     *
     * Accepts up to 100 events per request. Events are queued for async
     * processing — deduplication, CPC deduction, and stats aggregation
     * happen in the background. Returns 202 immediately.
     *
     * @return JsonResponse
     */
    public function bulkClicks(BulkAdEventRequest $request): JsonResponse
    {
        try {
            $this->adEventService->recordBulkClicks($request->validated('events'));

            return ApiResponseType::sendJsonResponse(true, 'labels.ad_clicks_accepted', [], 202);
        } catch (Throwable $e) {
            Log::error('AdEventApiController::bulkClicks failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    /**
     * Record a batch of impression events for ad campaigns.
     *
     * Accepts up to 100 events per request. Events are queued for async
     * processing — deduplication and stats aggregation happen in the
     * background. Returns 202 immediately.
     *
     * @return JsonResponse
     */
    public function bulkImpressions(BulkAdEventRequest $request): JsonResponse
    {
        try {
            $this->adEventService->recordBulkImpressions($request->validated('events'));

            return ApiResponseType::sendJsonResponse(true, 'labels.ad_impressions_accepted', [], 202);
        } catch (Throwable $e) {
            Log::error('AdEventApiController::bulkImpressions failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }
}
