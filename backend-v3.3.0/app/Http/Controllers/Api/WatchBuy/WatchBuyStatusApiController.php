<?php

namespace App\Http\Controllers\Api\WatchBuy;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WatchBuy\WatchBuyProfileStatusesRequest;
use App\Http\Requests\Api\WatchBuy\WatchBuyStatusFeedRequest;
use App\Http\Resources\WatchBuy\Customer\CustomerWatchBuyProfileStatusGroupResource;
use App\Http\Resources\WatchBuy\Customer\CustomerWatchBuyStatusResource;
use App\Http\Resources\WatchBuy\Customer\WatchBuyProfileResource;
use App\Models\User;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyStatusService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchBuyStatusApiController extends Controller
{
    public function __construct(
        private readonly WatchBuyStatusService $service,
    ) {}

    public function feed(WatchBuyStatusFeedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $feed = $this->service->getStatusTray($validated, $this->customer($request));

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_statuses_retrieved', [
            'items' => CustomerWatchBuyProfileStatusGroupResource::collection($feed['data']),
            'meta' => [
                'session_id' => $feed['session_id'],
                'next_cursor' => $feed['next_cursor'],
                'per_page' => (int) ($validated['per_page'] ?? 20),
            ],
        ]);
    }

    public function profile(string $username, WatchBuyProfileStatusesRequest $request): JsonResponse
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
        $lat = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $lng = isset($validated['longitude']) ? (float) $validated['longitude'] : null;
        $cursor = $validated['cursor'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $result = $this->service->getProfileStatuses(
            $profile,
            $lat,
            $lng,
            $cursor,
            $perPage,
            $this->customer($request)
        );
        $paginator = $result['statuses'];

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_statuses_retrieved', [
            'profile' => new WatchBuyProfileResource($profile),
            'items' => CustomerWatchBuyStatusResource::collection($paginator->items()),
            'pagination' => [
                'has_more_pages' => $paginator->hasMorePages(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'previous_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $paginator->perPage(),
                'total' => $result['total'],
            ],
        ]);
    }

    private function customer(Request $request): ?User
    {
        $user = $request->user('sanctum');

        return $user?->access_panel === GuardNameEnum::WEB ? $user : null;
    }
}
