<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
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

    public function index(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_VIEW());

        $columns = [
            ['data' => 'preview', 'name' => 'preview', 'title' => __('labels.preview'), 'orderable' => false, 'searchable' => false],
            ['data' => 'caption', 'name' => 'caption', 'title' => __('labels.watch_buy_caption')],
            ['data' => 'profile', 'name' => 'profile.username', 'title' => __('labels.profile')],
            ['data' => 'source', 'name' => 'source', 'title' => __('labels.source')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'like_count', 'name' => 'like_count', 'title' => __('labels.watch_buy_likes'), 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.watch_buy.reels.index', [
            'columns' => $columns,
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_VIEW());

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start');
        $length = (int) ($request->get('length') ?: 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));

        $orderColumnIndex = (int) ($request->get('order')[0]['column'] ?? 6);
        $orderDirection = $request->get('order')[0]['dir'] === 'asc' ? 'asc' : 'desc';

        $columnMap = [
            0 => 'id',
            1 => 'caption',
            2 => 'profile_id',
            3 => 'source',
            4 => 'status',
            5 => 'like_count',
            6 => 'created_at',
            7 => 'id',
        ];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'id';

        $query = WatchBuyReel::with(['profile.seller.user', 'media']);

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('caption', 'like', "%{$searchValue}%")
                    ->orWhere('slug', 'like', "%{$searchValue}%")
                    ->orWhere('source', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%")
                    ->orWhereHas('profile', fn ($pq) => $pq->where('username', 'like', "%{$searchValue}%")
                        ->orWhereHas('seller.user', fn ($uq) => $uq->where('name', 'like', "%{$searchValue}%")));
            });
        }

        $totalRecords = WatchBuyReel::count();
        $filteredRecords = $query->count();

        $canEdit = auth()->user()?->can('watch_buy.reel.edit') ?? false;
        $canBlock = auth()->user()?->can('watch_buy.reel.block') ?? false;
        $canDelete = auth()->user()?->can('watch_buy.reel.delete') ?? false;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (WatchBuyReel $reel) use ($canEdit, $canBlock, $canDelete) {
                $previewHtml = view('watch_buy._reel_video_preview', [
                    'reel' => $reel,
                    'gallery' => 'admin-watch-buy-reels',
                ])->render();

                $sourceVal = is_object($reel->source) ? $reel->source->value : $reel->source;
                $statusVal = is_object($reel->status) ? $reel->status->value : $reel->status;

                return [
                    'preview' => $previewHtml,
                    'caption' => e(Str::limit($reel->caption, 90) ?: '—'),
                    'profile' => '@'.e($reel->profile?->username ?? '-'),
                    'source' => e(match ($sourceVal) {
                        WatchBuySourceEnum::SELLER_BEHALF() => __('labels.watch_buy_seller_behalf'),
                        WatchBuySourceEnum::ADMIN_DIRECT() => __('labels.watch_buy_admin_direct'),
                        default => __('labels.seller'),
                    }),
                    'status' => view('partials.status', ['status' => $statusVal])->render(),
                    'like_count' => number_format((int) $reel->like_count),
                    'created_at' => $reel->created_at?->format('Y-m-d') ?? '—',
                    'action' => view('admin.watch_buy.reels._actions', [
                        'reel' => $reel,
                        'editPermission' => $canEdit,
                        'blockPermission' => $canBlock,
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

    public function create(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_CREATE());

        return $this->form(new WatchBuyReel);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_VIEW()) || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_CREATE()) || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_EDIT()), 403);
        $sellerId = null;
        if ($request->input('source') === WatchBuySourceEnum::SELLER_BEHALF()) {
            $sellerId = WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::SELLER())
                ->where('status', WatchBuyProfileStatusEnum::ACTIVE())
                ->find($request->integer('profile_id'))?->seller_id;
            if (! $sellerId) {
                return response()->json([]);
            }
        }

        return response()->json($this->watchBuy->eligibleProductVariantOptions($sellerId, trim((string) $request->input('search')), (array) $request->input('find_in', [])));
    }

    public function store(WatchBuyReelRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_CREATE());
        $data = $request->validated();
        $this->watchBuy->saveReel(new WatchBuyReel, $this->resolveProfile($data), $data, $request->file('video'), $request->file('cover'), $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_created', 201);
    }

    public function edit(WatchBuyReel $reel): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());

        return $this->form($reel);
    }

    public function update(WatchBuyReelRequest $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $data = $request->validated();
        $this->watchBuy->saveReel($reel, $this->resolveProfile($data), $data, $request->file('video'), $request->file('cover'), $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_updated');
    }

    public function destroy(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_DELETE());
        $this->watchBuy->deleteReel($reel, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_deleted');
    }

    public function publish(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->watchBuy->setPublished($reel, true, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_published');
    }

    public function unpublish(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->watchBuy->setPublished($reel, false, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_unpublished');
    }

    public function block(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_BLOCK());
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $this->watchBuy->block($reel, $reason, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_blocked');
    }

    public function unblock(Request $request, WatchBuyReel $reel): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_BLOCK());
        $this->watchBuy->unblock($reel, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_reel_unblocked');
    }

    private function form(WatchBuyReel $reel): View
    {
        $selected = $this->watchBuy->reelProductVariantSelection($reel);

        return view('admin.watch_buy.reels.form', [
            'reel' => $reel,
            'config' => $this->watchBuy->config(),
            'profiles' => WatchBuyProfile::with('seller.user')->where('profile_type', WatchBuyProfileTypeEnum::SELLER())->where('status', WatchBuyProfileStatusEnum::ACTIVE())->get(),
            'selected' => $selected,
            'selectedOptions' => $this->watchBuy->eligibleProductVariantOptions(null, '', $selected['attachment_ids']),
        ]);
    }

    private function resolveProfile(array $data): WatchBuyProfile
    {
        if (($data['source'] ?? null) === WatchBuySourceEnum::ADMIN_DIRECT()) {
            return WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::PLATFORM())->firstOrFail();
        }

        return WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::SELLER())->where('status', WatchBuyProfileStatusEnum::ACTIVE())->findOrFail($data['profile_id'] ?? null);
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, __('labels.permission_denied'));
    }

    private function response(Request $request, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        $url = route('admin.watch-and-buy.reels.index');

        return $request->expectsJson() ? ApiResponseType::sendJsonResponse(true, $message, ['redirect_url' => $url], $status) : redirect($url)->with('success', __($message));
    }
}
