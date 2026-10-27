<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyProfileRequest;
use App\Http\Resources\WatchBuy\WatchBuyProfileResource;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use App\Traits\ChecksPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWatchBuyProfileApiController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function show(Request $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_PROFILE_VIEW());
        $seller = $request->user()->seller();
        abort_unless($seller, 404);
        $profile = WatchBuyProfile::where('seller_id', $seller->id)->first();

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_retrieved', $profile ? new WatchBuyProfileResource($profile) : null);
    }

    public function store(WatchBuyProfileRequest $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        abort_unless($this->watchBuy->enabled(), 404);
        $seller = $request->user()->seller();
        abort_unless($seller, 404);
        $profile = WatchBuyProfile::firstOrNew(['seller_id' => $seller->id]);
        $profile = $this->watchBuy->saveProfile($profile, [
            ...$request->validated(),
            'profile_type' => WatchBuyProfileTypeEnum::SELLER(),
            'seller_id' => $seller->id,
        ], $request->file('photo'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_saved', new WatchBuyProfileResource($profile), $profile->wasRecentlyCreated ? 201 : 200);
    }

    private function allow(string $permission): void
    {
        $user = request()->user();
        abort_unless($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission), 403, __('labels.permission_denied'));
    }
}
