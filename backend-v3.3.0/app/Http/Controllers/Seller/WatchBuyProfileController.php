<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyProfileRequest;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchBuyProfileController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function edit(Request $request): View
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_PROFILE_VIEW());
        $seller = $request->user()->seller();
        abort_unless($seller, 404);
        $profile = WatchBuyProfile::where('seller_id', $seller->id)->first();
        $config = $this->watchBuy->config();
        $statusViewPermission = $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW());

        return view('seller.watch_buy.profile', [
            'profile' => $profile,
            'enabled' => (bool) $config['enabled'],
            'config' => $config,
            'statusViewPermission' => $statusViewPermission,
            'statusCreatePermission' => $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE()),
            'statusDeletePermission' => $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_DELETE()),
            'statuses' => $profile && $statusViewPermission
                ? $profile->statuses()->active()->with(['media', 'creator', 'products.variant.product'])->latest()->paginate(15, ['*'], 'statuses_page')
                : null,
        ]);
    }

    public function update(WatchBuyProfileRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        abort_unless($this->watchBuy->enabled(), 404);
        $seller = $request->user()->seller();
        abort_unless($seller, 404);
        $profile = WatchBuyProfile::firstOrNew(['seller_id' => $seller->id]);
        $this->watchBuy->saveProfile($profile, [
            ...$request->validated(),
            'profile_type' => WatchBuyProfileTypeEnum::SELLER(),
            'seller_id' => $seller->id,
        ], $request->file('photo'), $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_saved')
            : back()->with('success', __('labels.watch_buy_profile_saved'));
    }

    private function allow(string $permission): void
    {
        abort_unless($this->permitted($permission), 403, __('labels.permission_denied'));
    }

    private function permitted(string $permission): bool
    {
        return (bool) (auth()->user()?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }
}
