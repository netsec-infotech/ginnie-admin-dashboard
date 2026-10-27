<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\Builder;
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

    public function index(Request $request): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_STATUS_VIEW());

        $search = trim((string) $request->input('search', ''));
        $profileId = $request->integer('profile_id');

        return view('admin.watch_buy.statuses.index', [
            'profiles' => WatchBuyProfile::query()
                ->with([
                    'seller.user',
                    'media',
                    'statuses' => fn ($query) => $query->active()->oldest('created_at')->with(['media', 'creator', 'products.variant.product']),
                ])
                ->whereHas('statuses', fn ($query) => $query->active())
                ->when($profileId > 0, fn (Builder $query) => $query->whereKey($profileId))
                ->when($profileId <= 0 && $search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($search): void {
                    $match->where('username', 'like', "%{$search}%")
                        ->orWhereHas('seller.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"));
                }))
                ->orderBy('profile_type')
                ->latest('watch_buy_profiles.id')
                ->paginate(15)
                ->withQueryString(),
            'sellerProfiles' => WatchBuyProfile::with('seller.user')->where('profile_type', WatchBuyProfileTypeEnum::SELLER())->where('status', WatchBuyProfileStatusEnum::ACTIVE())->get(),
            'selectedSearchProfile' => $profileId > 0
                ? WatchBuyProfile::with('seller.user')->where('profile_type', WatchBuyProfileTypeEnum::SELLER())->find($profileId)
                : null,
            'config' => $this->watchBuy->config(),
        ]);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_VIEW()) || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_CREATE()), 403);
        $sellerId = null;
        if ($request->input('source') === WatchBuySourceEnum::SELLER_BEHALF()) {
            $sellerId = WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::SELLER())->where('status', WatchBuyProfileStatusEnum::ACTIVE())->find($request->integer('profile_id'))?->seller_id;
            if (! $sellerId) {
                return response()->json([]);
            }
        }

        return response()->json($this->watchBuy->eligibleProductVariantOptions($sellerId, trim((string) $request->input('search')), (array) $request->input('find_in', [])));
    }

    public function store(WatchBuyStatusRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_STATUS_CREATE());
        $data = $request->validated();
        $profile = ($data['source'] ?? null) === WatchBuySourceEnum::ADMIN_DIRECT()
            ? WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::PLATFORM())->firstOrFail()
            : WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::SELLER())->where('status', WatchBuyProfileStatusEnum::ACTIVE())->findOrFail($data['profile_id'] ?? null);
        $status = $this->statuses->save($profile, $data, $request->file('media'), $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_created', ['status_id' => $status->id], 201)
            : back()->with('success', __('labels.watch_buy_status_created'));
    }

    public function destroy(Request $request, WatchBuyStatus $status): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_STATUS_DELETE());
        $this->statuses->delete($status);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_deleted')
            : back()->with('success', __('labels.watch_buy_status_deleted'));
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, __('labels.permission_denied'));
    }
}
