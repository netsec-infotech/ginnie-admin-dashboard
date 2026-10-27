<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyReelStatusEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyReelRequest;
use App\Http\Resources\WatchBuy\WatchBuyReelResource;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Services\WatchBuyService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWatchBuyReelApiController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function eligibleProducts(Request $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_products_retrieved', $this->watchBuy->eligibleProductVariantOptions($this->sellerId($request), trim((string) $request->input('search')), (array) $request->input('find_in', [])));
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
        $sellerId = $this->sellerId($request);
        $reels = WatchBuyReel::whereHas('profile', fn ($profile) => $profile->where('seller_id', $sellerId))
            ->with(['profile.media', 'products'])->latest()->paginate(min(max($request->integer('per_page', 15), 1), 50));

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reels_retrieved', [
            'items' => WatchBuyReelResource::collection($reels->getCollection()),
            'pagination' => ['current_page' => $reels->currentPage(), 'last_page' => $reels->lastPage(), 'per_page' => $reels->perPage(), 'total' => $reels->total()],
        ]);
    }

    public function show(Request $request, WatchBuyReel $reel): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
        $this->owned($request, $reel);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_retrieved', new WatchBuyReelResource($reel->load(['profile.media', 'products'])));
    }

    public function store(WatchBuyReelRequest $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_CREATE());
        abort_unless($this->watchBuy->enabled(), 404);
        $profile = WatchBuyProfile::where('seller_id', $this->sellerId($request))->firstOrFail();
        $reel = $this->watchBuy->saveReel(new WatchBuyReel, $profile, [...$request->validated(), 'source' => WatchBuySourceEnum::SELLER()], $request->file('video'), $request->file('cover'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_created', new WatchBuyReelResource($reel->load(['profile.media', 'products'])), 201);
    }

    public function update(WatchBuyReelRequest $request, WatchBuyReel $reel): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->owned($request, $reel);
        abort_if($reel->status === WatchBuyReelStatusEnum::BLOCKED, 403);
        $reel = $this->watchBuy->saveReel($reel, $reel->profile, [...$request->validated(), 'source' => WatchBuySourceEnum::SELLER()], $request->file('video'), $request->file('cover'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_updated', new WatchBuyReelResource($reel->load(['profile.media', 'products'])));
    }

    public function destroy(Request $request, WatchBuyReel $reel): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_DELETE());
        $this->owned($request, $reel);
        abort_if($reel->status === WatchBuyReelStatusEnum::BLOCKED || $reel->profile->status === WatchBuyProfileStatusEnum::SUSPENDED, 403);
        $this->watchBuy->deleteReel($reel, $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_deleted');
    }

    public function publish(Request $request, WatchBuyReel $reel): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
        $this->owned($request, $reel);
        $validated = $request->validate(['published' => ['required', 'boolean']]);
        $reel = $this->watchBuy->setPublished($reel, (bool) $validated['published'], $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, $validated['published'] ? 'labels.watch_buy_reel_published' : 'labels.watch_buy_reel_unpublished', new WatchBuyReelResource($reel->load(['profile.media', 'products'])));
    }

    private function owned(Request $request, WatchBuyReel $reel): void
    {
        abort_unless($reel->profile?->seller_id === $this->sellerId($request), 404);
    }

    private function sellerId(Request $request): int
    {
        $seller = $request->user()->seller();
        abort_unless($seller, 404);

        return $seller->id;
    }

    private function allow(string $permission): void
    {
        abort_unless($this->permitted($permission), 403, __('labels.permission_denied'));
    }

    private function permitted(string $permission): bool
    {
        $user = request()->user();

        return (bool) ($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }
}
