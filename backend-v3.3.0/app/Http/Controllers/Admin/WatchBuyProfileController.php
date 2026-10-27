<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\WatchBuy\WatchBuyProfileStatusEnum;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyProfileRequest;
use App\Models\Seller;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WatchBuyProfileController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function index(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW());

        $columns = [
            ['data' => 'photo', 'name' => 'photo', 'title' => __('labels.watch_buy_photo'), 'orderable' => false, 'searchable' => false],
            ['data' => 'username', 'name' => 'username', 'title' => __('labels.watch_buy_username')],
            ['data' => 'profile_type', 'name' => 'profile_type', 'title' => __('labels.type')],
            ['data' => 'seller', 'name' => 'seller.user.name', 'title' => __('labels.seller')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.watch_buy.profiles', [
            'columns' => $columns,
            'editPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT()),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW());

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start');
        $length = (int) ($request->get('length') ?: 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));

        $orderColumnIndex = (int) ($request->get('order')[0]['column'] ?? 5);
        $orderDirection = $request->get('order')[0]['dir'] === 'asc' ? 'asc' : 'desc';

        $columnMap = [
            0 => 'id',
            1 => 'username',
            2 => 'profile_type',
            3 => 'seller_id',
            4 => 'status',
            5 => 'created_at',
            6 => 'id',
        ];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'id';

        $query = WatchBuyProfile::with(['seller.user', 'media']);

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('username', 'like', "%{$searchValue}%")
                    ->orWhere('bio', 'like', "%{$searchValue}%")
                    ->orWhere('profile_type', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%")
                    ->orWhereHas('seller.user', fn ($uq) => $uq->where('name', 'like', "%{$searchValue}%"));
            });
        }

        $totalRecords = WatchBuyProfile::count();
        $filteredRecords = $query->count();

        $canEdit = auth()->user()?->can('watch_buy.profile.edit') ?? false;
        $canSuspend = auth()->user()?->can('watch_buy.profile.suspend') ?? false;

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (WatchBuyProfile $profile) use ($canEdit, $canSuspend) {
                $typeVal = is_object($profile->profile_type) ? $profile->profile_type->value : $profile->profile_type;
                $statusVal = is_object($profile->status) ? $profile->status->value : $profile->status;

                $statusBadge = $statusVal === 'active'
                    ? '<span class="badge bg-success-lt">'.e($statusVal).'</span>'
                    : '<span class="badge bg-danger-lt">'.e($statusVal).'</span>';

                return [
                    'photo' => view('watch_buy._profile_photo', compact('profile'))->render(),
                    'username' => '@'.e($profile->username),
                    'profile_type' => e(ucfirst($typeVal)),
                    'seller' => e($profile->seller?->user?->name ?? __('labels.watch_buy_platform')),
                    'status' => $statusBadge,
                    'created_at' => $profile->created_at->format('Y-m-d'),
                    'action' => view('admin.watch_buy._profile_actions', [
                        'profile' => $profile,
                        'editPermission' => $canEdit,
                        'suspendPermission' => $canSuspend,
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

    public function options(Request $request): JsonResponse
    {
        abort_unless(
            $this->hasPermission(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_CREATE())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_VIEW()),
            403
        );
        $search = trim((string) $request->input('search'));
        $profiles = WatchBuyProfile::with('seller.user')
            ->where('profile_type', WatchBuyProfileTypeEnum::SELLER())
            ->where('status', WatchBuyProfileStatusEnum::ACTIVE())
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->where('username', 'like', "%{$search}%")
                    ->orWhereHas('seller.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            }))
            ->limit(30)->get()
            ->map(fn (WatchBuyProfile $profile) => ['id' => $profile->id, 'text' => '@'.$profile->username.' — '.($profile->seller?->user?->name ?? '-')]);

        return response()->json($profiles);
    }

    public function sellerOptions(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $findIn = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('find_in', [])))));
        $search = trim((string) $request->input('search', ''));

        $sellers = Seller::query()
            ->with('user')
            ->whereDoesntHave('watchBuyProfile')
            ->when($findIn !== [], fn ($query) => $query->whereKey($findIn))
            ->when($findIn === [] && $search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest('sellers.id')
            ->limit($findIn === [] ? 15 : 20)
            ->get();

        return response()->json($sellers->map(fn (Seller $seller) => [
            'value' => (string) $seller->id,
            'text' => $seller->user?->name ?: __('labels.watch_buy_seller_number', ['id' => $seller->id]),
            'subtitle' => Str::limit((string) $seller->user?->email, 60),
        ])->values());
    }

    public function store(WatchBuyProfileRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $validated = $request->validate(['seller_id' => ['required', 'exists:sellers,id', 'unique:watch_buy_profiles,seller_id']]) + $request->validated();
        $this->watchBuy->saveProfile(new WatchBuyProfile, [
            ...$validated,
            'profile_type' => WatchBuyProfileTypeEnum::SELLER(),
        ], $request->file('photo'), $request->user()->id);

        return $this->response($request, 'labels.watch_buy_profile_created', route('admin.watch-and-buy.profiles.index'), 201);
    }

    public function edit(WatchBuyProfile $profile): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());

        return view('admin.watch_buy.profile-edit', compact('profile'));
    }

    public function update(WatchBuyProfileRequest $request, WatchBuyProfile $profile): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $this->watchBuy->saveProfile($profile, $request->validated(), $request->file('photo'), $request->user()->id, true);

        return $this->response($request, 'labels.watch_buy_profile_saved', route('admin.watch-and-buy.profiles.index'));
    }

    public function official(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW());

        return view('admin.watch_buy.official-profile', ['profile' => WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::PLATFORM())->firstOrFail()]);
    }

    public function updateOfficial(WatchBuyProfileRequest $request): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $profile = WatchBuyProfile::where('profile_type', WatchBuyProfileTypeEnum::PLATFORM())->firstOrFail();
        $this->watchBuy->saveProfile($profile, $request->validated(), $request->file('photo'), $request->user()->id, true);

        return $this->response($request, 'labels.watch_buy_official_profile_updated', route('admin.watch-and-buy.official-profile.edit'));
    }

    public function suspend(Request $request, WatchBuyProfile $profile): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_SUSPEND());
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $this->watchBuy->suspend($profile, $reason, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_profile_suspended_success', route('admin.watch-and-buy.profiles.index'));
    }

    public function unsuspend(Request $request, WatchBuyProfile $profile): RedirectResponse|JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_SUSPEND());
        $this->watchBuy->unsuspend($profile, $request->user()->id);

        return $this->response($request, 'labels.watch_buy_profile_restored', route('admin.watch-and-buy.profiles.index'));
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, __('labels.permission_denied'));
    }

    private function response(Request $request, string $message, string $redirectUrl, int $status = 200): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, $message, ['redirect_url' => $redirectUrl], $status)
            : redirect($redirectUrl)->with('success', __($message));
    }
}
