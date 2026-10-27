<?php

namespace App\Http\Controllers\Api\WatchBuy;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WatchBuy\WatchBuyProfileReelsRequest;
use App\Http\Requests\Api\WatchBuy\WatchBuyReelFeedRequest;
use App\Http\Resources\WatchBuy\Customer\CustomerWatchBuyReelResource;
use App\Http\Resources\WatchBuy\Customer\WatchBuyProfileResource;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchBuyReelApiController extends Controller
{
    public function __construct(
        private readonly WatchBuyService $service,
    ) {}

    public function feed(WatchBuyReelFeedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $feed = $this->service->getReelFeed($validated, $this->customer($request));

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reels_retrieved', [
            'items' => CustomerWatchBuyReelResource::collection($feed['data']),
            'meta' => [
                'session_id' => $feed['session_id'],
                'next_cursor' => $feed['next_cursor'],
                'per_page' => (int) ($validated['per_page'] ?? 10),
                'slug_not_found' => $feed['slug_not_found'],
            ],
        ]);
    }

    public function profile(string $username, WatchBuyProfileReelsRequest $request): JsonResponse
    {
        $decodedUsername = urldecode($username);
        $profile = WatchBuyProfile::where('username', strtolower($decodedUsername))
            ->orWhere('username', $decodedUsername)
            ->orWhereHas('seller.stores', function ($query) use ($decodedUsername) {
                $query->where('name', $decodedUsername)
                    ->orWhere('slug', strtolower($decodedUsername));
            })
            ->first();

        if (! $profile) {
            abort(404);
        }

        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $result = $this->service->getProfileReels(
            $profile,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            $page,
            $perPage,
            $this->customer($request)
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_retrieved', [
            'profile' => new WatchBuyProfileResource($profile),
            'reels' => CustomerWatchBuyReelResource::collection($result['data']),
            'pagination' => [
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
            ],
        ]);
    }

    private function customer(Request $request): ?\App\Models\User
    {
        $user = $request->user('sanctum');

        return $user?->access_panel === GuardNameEnum::WEB ? $user : null;
    }

    // For Individual reels
    public function show(string $reel, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $lat = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $lng = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

        $reelModel = $this->service->getSingleReel(
            $reel,
            $lat,
            $lng,
            $this->customer($request)
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.watch_buy_reel_retrieved',
            new CustomerWatchBuyReelResource($reelModel)
        );
    }
}
