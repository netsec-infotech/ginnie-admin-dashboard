<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Http\Resources\WatchBuy\WatchBuyStatusResource;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWatchBuyStatusApiController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyStatusService $statuses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW());
        $profile = WatchBuyProfile::where('seller_id', $this->sellerId($request))->first();
        $statuses = $profile?->statuses()->active()->with(['profile.media', 'products.product.media', 'products.variant.media'])->latest()->get() ?? collect();

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_statuses_retrieved', WatchBuyStatusResource::collection($statuses));
    }

    public function store(WatchBuyStatusRequest $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE());
        $profile = WatchBuyProfile::where('seller_id', $this->sellerId($request))->firstOrFail();
        $status = $this->statuses->save($profile, [...$request->validated(), 'source' => WatchBuySourceEnum::SELLER()], $request->file('media'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_created', new WatchBuyStatusResource($status->load(['profile.media', 'products.product.media', 'products.variant.media'])), 201);
    }

    public function destroy(Request $request, WatchBuyStatus $status): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_DELETE());
        abort_unless($status->profile?->seller_id === $this->sellerId($request), 404);
        abort_if($status->profile->status === WatchBuyProfileStatusEnum::SUSPENDED, 403);
        $this->statuses->delete($status);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_deleted');
    }

    private function allow(string $permission): void
    {
        $user = request()->user();
        abort_unless($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission), 403, __('labels.permission_denied'));
    }

    private function sellerId(Request $request): int
    {
        $seller = $request->user()->seller();
        abort_unless($seller, 404);

        return $seller->id;
    }
}
