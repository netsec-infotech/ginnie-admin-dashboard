<?php

namespace App\Http\Controllers\Api\WatchBuy;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WatchBuy\WatchBuyBulkLikeRequest;
use App\Http\Resources\WatchBuy\Customer\WatchBuyLikeResource;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class WatchBuyLikeApiController extends Controller
{
    public function __construct(
        private readonly WatchBuyService $service,
    ) {}

    public function sync(WatchBuyBulkLikeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        // if ($user->access_panel !== GuardNameEnum::WEB) {
        //     return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        // }

        $results = $this->service->syncLikes(
            $validated['items'],
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $user
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.watch_buy_reel_likes_updated',
            WatchBuyLikeResource::collection($results),
        );
    }
}
