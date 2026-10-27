<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyReelStatusEnum;
use App\Enums\WatchBuy\WatchBuySourceEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyReelRequest;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Services\WatchBuyService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WatchBuyReelController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function index(Request $request): View
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
        $sellerId = $this->sellerId($request);

        $columns = [
            ['data' => 'preview', 'name' => 'preview', 'title' => __('labels.preview'), 'orderable' => false, 'searchable' => false],
            ['data' => 'caption', 'name' => 'caption', 'title' => __('labels.watch_buy_caption')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'like_count', 'name' => 'like_count', 'title' => __('labels.watch_buy_likes'), 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('seller.watch_buy.reels.index', [
            'profile' => WatchBuyProfile::where('seller_id', $sellerId)->first(),
            'columns' => $columns,
            'enabled' => $this->watchBuy->enabled(),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
        $sellerId = $this->sellerId($request);
        $profile = WatchBuyProfile::where('seller_id', $sellerId)->first();
        $enabled = $this->watchBuy->enabled();

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start');
        $length = (int) ($request->get('length') ?: 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));

        $orderColumnIndex = (int) ($request->get('order')[0]['column'] ?? 4);
        $orderDirection = $request->get('order')[0]['dir'] === 'asc' ? 'asc' : 'desc';

        $columnMap = [
            0 => 'id',
            1 => 'caption',
            2 => 'status',
            3 => 'like_count',
            4 => 'created_at',
            5 => 'id',
        ];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'id';

        $query = WatchBuyReel::whereHas('profile', fn ($p) => $p->where('seller_id', $sellerId))
            ->with(['profile', 'media']);

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('caption', 'like', "%{$searchValue}%")
                    ->orWhere('slug', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%");
            });
        }

        $totalRecords = WatchBuyReel::whereHas('profile', fn ($p) => $p->where('seller_id', $sellerId))->count();
        $filteredRecords = $query->count();

        $user = auth()->user();
        $canEdit = $user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $user?->can('watch_buy.reel.edit');
        $canPublish = $user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $user?->can('watch_buy.reel.publish');
        $canDelete = $user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $user?->can('watch_buy.reel.delete');

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (WatchBuyReel $reel) use ($enabled, $profile, $canEdit, $canPublish, $canDelete) {
                $previewHtml = view('watch_buy._reel_video_preview', [
                    'reel' => $reel,
                    'gallery' => 'seller-watch-buy-reels',
                ])->render();

                $statusVal = is_object($reel->status) ? $reel->status->value : $reel->status;
                $profileStatusVal = is_object($profile?->status) ? $profile->status->value : $profile?->status;

                return [
                    'preview' => $previewHtml,
                    'caption' => e(Str::limit($reel->caption, 100) ?: '—'),
                    'status' => view('partials.status', ['status' => $statusVal])->render(),
                    'like_count' => number_format((int) $reel->like_count),
                    'created_at' => $reel->created_at?->format('Y-m-d') ?? '—',
                    'action' => view('seller.watch_buy.reels._actions', [
                        'reel' => $reel,
                        'enabled' => $enabled,
                        'profileStatus' => $profileStatusVal,
                        'editPermission' => $canEdit,
                        'publishPermission' => $canPublish,
                        'deletePermission' => $canDelete,
                    ])->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_CREATE());
        if (! WatchBuyProfile::where('seller_id', $this->sellerId($request))->exists()) {
            return redirect()->route('seller.watch-and-buy.profile.edit')->with('watch_buy_profile_required', __('labels.watch_buy_profile_required'));
        }

        return $this->form($request, new WatchBuyReel);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless($this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_VIEW()) || $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_CREATE()) || $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_EDIT()), 403);

        return response()->json($this->watchBuy->eligibleProductVariantOptions($this->sellerId($request), trim((string) $request->input('search')), (array) $request->input('find_in', [])));
    }

    public function store(WatchBuyReelRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_CREATE());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->watchBuy->saveReel(new WatchBuyReel, $this->profile($request), [...$request->validated(), 'source' => WatchBuySourceEnum::SELLER()], $request->file('video'), $request->file('cover'), $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_created', 201);
    }

    public function edit(Request $request, WatchBuyReel $reel): View
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->owned($request, $reel);
        abort_if($reel->status === WatchBuyReelStatusEnum::BLOCKED, 403);

        return $this->form($request, $reel);
    }

    public function update(WatchBuyReelRequest $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        abort_if($reel->status === WatchBuyReelStatusEnum::BLOCKED, 403);
        $this->watchBuy->saveReel($reel, $reel->profile, [...$request->validated(), 'source' => WatchBuySourceEnum::SELLER()], $request->file('video'), $request->file('cover'), $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_updated');
    }

    public function destroy(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_DELETE());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        abort_if($reel->status === WatchBuyReelStatusEnum::BLOCKED || $reel->profile->status === WatchBuyProfileStatusEnum::SUSPENDED, 403);
        $this->watchBuy->deleteReel($reel, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_deleted');
    }

    public function publish(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        $this->watchBuy->setPublished($reel, true, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_published');
    }

    public function unpublish(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        $this->watchBuy->setPublished($reel, false, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_unpublished');
    }

    private function form(Request $request, WatchBuyReel $reel): View
    {
        $selected = $this->watchBuy->reelProductVariantSelection($reel);

        $sellerId = $this->sellerId($request);

        return view('seller.watch_buy.reels.form', [
            'reel' => $reel,
            'config' => $this->watchBuy->config(),
            'selected' => $selected,
            'selectedOptions' => $this->watchBuy->eligibleProductVariantOptions($sellerId, '', $selected['attachment_ids']),
        ]);
    }

    private function profile(Request $request): WatchBuyProfile
    {
        return WatchBuyProfile::where('seller_id', $this->sellerId($request))->firstOrFail();
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
        return (bool) (auth()->user()?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }

    private function response(Request $request, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        $url = route('seller.watch-and-buy.reels.index');

        return $request->expectsJson() ? ApiResponseType::sendJsonResponse(true, $message, ['redirect_url' => $url], $status) : redirect($url)->with('success', __($message));
    }
}
