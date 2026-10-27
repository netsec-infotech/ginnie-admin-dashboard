<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchBuyStatusController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyStatusService $statuses,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW());
        $profile = WatchBuyProfile::where('seller_id', $this->sellerId($request))->first();
        if (! $profile) {
            return redirect()->route('seller.watch-and-buy.profile.edit')->with('watch_buy_profile_required', __('labels.watch_buy_profile_required'));
        }

        return view('seller.watch_buy.statuses.index', [
            'profile' => $profile,
            'statuses' => $profile->statuses()->active()->with(['media', 'creator', 'products.variant.product'])->latest()->paginate(15),
            'config' => $this->watchBuy->config(),
        ]);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless($this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW()) || $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE()), 403);

        return response()->json($this->watchBuy->eligibleProductVariantOptions($this->sellerId($request), trim((string) $request->input('search')), (array) $request->input('find_in', [])));
    }

    public function store(WatchBuyStatusRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE());
        abort_unless($this->watchBuy->statusesEnabled(), 404);
        $profile = WatchBuyProfile::where('seller_id', $this->sellerId($request))->firstOrFail();
        $status = $this->statuses->save($profile, [...$request->validated(), 'source' => WatchBuySourceEnum::SELLER()], $request->file('media'), $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_created', ['status_id' => $status->id], 201)
            : back()->with('success', __('labels.watch_buy_status_created'));
    }

    public function destroy(Request $request, WatchBuyStatus $status): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_DELETE());
        abort_unless($this->watchBuy->statusesEnabled(), 404);
        abort_unless($status->profile?->seller_id === $this->sellerId($request), 404);
        abort_if($status->profile->status === WatchBuyProfileStatusEnum::SUSPENDED, 403);
        $this->statuses->delete($status);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_deleted')
            : back()->with('success', __('labels.watch_buy_status_deleted'));
    }

    private function allow(string $permission): void
    {
        abort_unless($this->permitted($permission), 403, __('labels.permission_denied'));
    }

    private function permitted(string $permission): bool
    {
        return (bool) (auth()->user()?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }

    private function sellerId(Request $request): int
    {
        $seller = $request->user()->seller();
        abort_unless($seller, 404);

        return $seller->id;
    }
}
