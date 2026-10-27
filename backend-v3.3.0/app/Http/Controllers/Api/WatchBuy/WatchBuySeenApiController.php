<?php

namespace App\Http\Controllers\Api\WatchBuy;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WatchBuy\WatchBuyBulkSeenRequest;
use App\Http\Resources\WatchBuy\Customer\WatchBuyStatusSeenResource;
use App\Services\WatchBuyStatusService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class WatchBuySeenApiController extends Controller
{
    public function __construct(
        private readonly WatchBuyStatusService $service,
    ) {}

    public function sync(WatchBuyBulkSeenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        // if ($user->access_panel !== GuardNameEnum::WEB) {
        //     return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        // }

        $results = $this->service->markSeen($validated['status_ids'], $user);

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.watch_buy_statuses_seen',
            WatchBuyStatusSeenResource::collection($results),
        );
    }
}
