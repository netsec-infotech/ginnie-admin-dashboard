<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Events\Seller\SellerStatusUpdated;
use App\Events\Seller\SellerUpdated;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Http\Requests\Seller\UpdateSellerRequest;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\SellerStatement;
use App\Models\Setting;
use App\Models\User;
use App\Services\SellerService;
use App\Services\SettingService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $createPermission = false;

    protected $sellerService;

    protected SettingService $settingService;

    public function __construct(SellerService $sellerService, SettingService $settingService)
    {
        $this->sellerService = $sellerService;
        $this->settingService = $settingService;
        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::SELLER_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::SELLER_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::SELLER_CREATE());
        }
        if (Setting::isSystemVendorTypeSingle()) {
            $this->createPermission = false;
        }
    }

    private function isDemoModeEnabled(): bool
    {
        $resource = $this->settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
        $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

        return (bool) ($settings['demoMode'] ?? false);
    }

    private function maskMobile(?string $mobile): string
    {
        if (! $mobile) {
            return '';
        }
        // Keep last 3 digits visible, mask the rest
        $len = strlen($mobile);
        if ($len <= 3) {
            return str_repeat('*', $len);
        }
        $visible = substr($mobile, -3);

        return str_repeat('*', $len - 3).$visible;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Seller::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller')],
            ['data' => 'email', 'name' => 'email', 'title' => __('labels.email')],
            ['data' => 'mobile', 'name' => 'mobile', 'title' => __('labels.mobile')],
            ['data' => 'verification_status', 'name' => 'verification_status', 'title' => __('labels.verification_status')],
            ['data' => 'visibility_status', 'name' => 'visibility_status', 'title' => __('labels.visibility_status')],
            ['data' => 'stores', 'name' => 'stores', 'title' => __('labels.stores')],
            ['data' => 'plan', 'name' => 'plan', 'title' => __('labels.plan')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;

        $sellerInsights = [
            'total' => Seller::count(),
            'approved' => Seller::where('verification_status', 'approved')->count(),
            'not_approved' => Seller::where(function ($q) {
                $q->where('verification_status', 'not_approved')
                    ->orWhereNull('verification_status')
                    ->orWhere('verification_status', '');
            })->count(),
        ];

        return view($this->panelView('sellers.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission', 'sellerInsights'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @throws AuthorizationException
     */
    public function create(): View
    {
        $this->authorize('create', Seller::class);

        return view($this->panelView('sellers.form'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSellerRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Seller::class);

            if (Setting::isSystemVendorTypeSingle()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_registration_disabled'),
                    status: 403
                );
            }

            $seller = $this->sellerService->createSeller(
                $request->validated(),
                $request->allFiles()
            );

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.seller_created_successfully',
                $seller,
                201
            );
        } catch (Exception $e) {
            $statusCode = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            $statusCode = in_array($statusCode, [404, 422, 500]) ? $statusCode : 500;

            $message = match ($statusCode) {
                404 => 'labels.user_not_found',
                422 => $e->getMessage() === 'Seller already exists for this user'
                    ? 'labels.seller_already_exists'
                    : 'labels.validation_failed',
                default => 'labels.error_occurred',
            };

            return ApiResponseType::sendJsonResponse(
                false,
                $message,
                ['error' => $e->getMessage()],
                $statusCode
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): View
    {
        $seller = Seller::with([
            'user',
            'stores',
            'wallet',
            'countryDetails',
            'activeSubscription.plan',
        ])->find($id);

        if (! $seller) {
            abort(404);
        }

        $this->authorize('viewAny', $seller);

        $orderStats = $this->sellerService->getOrderStats($seller);
        $earningsSummary = $this->sellerService->getEarningsSummary($seller);
        $recentNotifications = $this->sellerService->getRecentNotifications($seller);
        $fcmTokens = $this->sellerService->getFcmTokens($seller);

        $reviewData = SellerFeedback::getSellerFeedbackStatistics($seller->id);

        $profileImageUrl = $seller->user?->getFirstMediaUrl('profile_image')
            ?: asset('assets/images/user-placeholder.png');

        $feedbackColumns = [
            ['data' => 'customer', 'name' => 'customer', 'title' => __('labels.customer'), 'label' => __('labels.customer')],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'label' => __('labels.store')],
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order')],
            ['data' => 'rating', 'name' => 'rating', 'title' => __('labels.rating'), 'label' => __('labels.rating')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $statementColumns = [
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
            ['data' => 'entry_type', 'name' => 'entry_type', 'title' => __('labels.entry_type'), 'label' => __('labels.entry_type')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount'), 'label' => __('labels.amount')],
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order')],
            ['data' => 'settlement_status', 'name' => 'settlement_status', 'title' => __('labels.settlement_status'), 'label' => __('labels.settlement_status')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description')],
        ];

        $notificationColumns = [
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
            ['data' => 'message', 'name' => 'message', 'title' => __('labels.message'), 'label' => __('labels.message')],
            ['data' => 'type', 'name' => 'type', 'title' => __('labels.type'), 'label' => __('labels.type')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        return view('admin.sellers.view', compact(
            'seller',
            'orderStats',
            'earningsSummary',
            'recentNotifications',
            'fcmTokens',
            'reviewData',
            'profileImageUrl',
            'feedbackColumns',
            'statementColumns',
            'notificationColumns',
            'editPermission',
            'deletePermission',
        ));
    }

    /**
     * Server-side DataTable for seller wallet/settlement statements.
     */
    public function statementsDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = SellerStatement::where('seller_id', $seller->id)
                ->with('order:id');

            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('entry_type', 'like', "%{$searchValue}%")
                        ->orWhere('settlement_status', 'like', "%{$searchValue}%")
                        ->orWhere('description', 'like', "%{$searchValue}%")
                        ->orWhereHas('order', function ($orderQuery) use ($searchValue) {
                            $orderQuery->where('id', 'like', "%{$searchValue}%");
                        });
                });
            }

            $filteredRecords = $query->count();
            $statements = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $statements->map(function ($statement) {
                $entryTypeBadge = '<span class="badge bg-azure-lt">'.e(ucfirst(str_replace('_', ' ', $statement->entry_type ?? ''))).'</span>';

                $settlementBadge = $statement->settlement_status === 'settled'
                    ? '<span class="badge bg-success-lt">'.e(__('labels.settled')).'</span>'
                    : '<span class="badge bg-warning-lt">'.e(__('labels.pending')).'</span>';

                return [
                    'created_at' => $statement->created_at?->format('d M Y'),
                    'entry_type' => $entryTypeBadge,
                    'amount' => getCurrencySymbol().number_format((float) $statement->amount, 2),
                    'order' => $statement->order ? '#'.$statement->order->id : 'N/A',
                    'settlement_status' => $settlementBadge,
                    'description' => e(Str::limit($statement->description, 50)),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller statements datatable error: '.$e->getMessage());

            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $seller = Seller::with(['user', 'countryDetails'])->find($id);

        if (! $seller) {
            abort(404);
        }
        $this->authorize('update', $seller);
        $seller->business_license = $seller->getFirstMediaUrl(SpatieMediaCollectionName::BUSINESS_LICENSE()) ?? null;
        $seller->articles_of_incorporation = $seller->getFirstMediaUrl(SpatieMediaCollectionName::ARTICLES_OF_INCORPORATION()) ?? null;
        $seller->national_identity_card = $seller->getFirstMediaUrl(SpatieMediaCollectionName::NATIONAL_IDENTITY_CARD()) ?? null;
        $seller->authorized_signature = $seller->getFirstMediaUrl(SpatieMediaCollectionName::AUTHORIZED_SIGNATURE()) ?? null;

        //        dd($seller);
        return view($this->panelView('sellers.form'), compact('seller'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSellerRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $seller = Seller::find($id);
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
            }
            $this->authorize('update', $seller);
            $request->validated();
            $currentVerificationStatus = $seller->verification_status;
            $user = $seller->user;
            if (! $user->hasRole(DefaultSystemRolesEnum::SELLER())) {
                $user->assignRole('seller');
            }
            $user->update($request->only(['name']));

            $validated = $request->safe()->except(['business_license', 'articles_of_incorporation', 'national_identity_card', 'authorized_signature']);

            // Unset image fields before updating the Seller
            $imageFields = [
                'business_license',
                'articles_of_incorporation',
                'national_identity_card',
                'authorized_signature',
            ];

            $seller->update($validated);

            // Handle image uploads via Spatie
            foreach ($imageFields as $field) {
                if ($request->hasFile($field)) {
                    $seller->addMediaFromRequest($field)->toMediaCollection($field);
                }
            }

            DB::commit();
            if ($request->verification_status !== $currentVerificationStatus) {
                event(new SellerStatusUpdated($seller, $user));
            }
            event(new SellerUpdated($seller, $user));

            return ApiResponseType::sendJsonResponse(true, 'labels.seller_updated_successfully', $seller);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'labels.validation_failed', $e->errors(), 422);
        } catch (Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, 'labels.error_occurred', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $seller = Seller::find($id);
            if (! $seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
            }
            $this->authorize('delete', $seller);
            $seller->delete();
            $seller->media()->each(function ($media) {
                $media->delete();
            });

            return ApiResponseType::sendJsonResponse(true, 'labels.seller_deleted_successfully');
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.error_occurred', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Server-side DataTable for seller feedback.
     */
    public function feedbackDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = SellerFeedback::where('seller_id', $seller->id)
                ->with(['user', 'order', 'store']);

            $totalRecords = SellerFeedback::where('seller_id', $seller->id)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('title', 'like', "%{$searchValue}%")
                        ->orWhere('description', 'like', "%{$searchValue}%")
                        ->orWhereHas('user', function ($uq) use ($searchValue) {
                            $uq->where('name', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('mobile', 'like', "%{$searchValue}%");
                        });
                });
            }

            $filteredRecords = $query->count();
            $feedbacks = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $feedbacks->map(function ($feedback) {
                $user = $feedback->user;
                $customerHtml = '<div class="d-flex align-items-center">'
                    .'<div>'
                    .'<div class="fw-bold">'.e($user->name ?? 'N/A').'</div>'
                    .'<div class="text-secondary small">'.e($user->email ?? '').'</div>'
                    .'<div class="text-secondary small">'.e($user->mobile ?? '').'</div>'
                    .'</div></div>';

                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    $stars .= $i <= $feedback->rating
                        ? '<i class="ti ti-star-filled text-warning"></i>'
                        : '<i class="ti ti-star text-muted"></i>';
                }

                return [
                    'customer' => $customerHtml,
                    'store' => e($feedback->store->name ?? 'N/A'),
                    'order' => $feedback->order ? '#'.$feedback->order->id : 'N/A',
                    'rating' => $stars,
                    'title' => e($feedback->title ?? ''),
                    'description' => e(Str::limit($feedback->description, 80)),
                    'created_at' => $feedback->created_at?->format('d M Y'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller feedback datatable error: '.$e->getMessage());

            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Server-side DataTable for seller notifications.
     */
    public function notificationsDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $seller = Seller::with('user')->findOrFail($id);

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = Notification::where('notifiable_id', $seller->user_id)
                ->where('notifiable_type', User::class)
                ->roleType(NotificationRoleTypeEnum::SELLER);

            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('data->title', 'like', "%{$searchValue}%")
                        ->orWhere('data->message', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $notifications = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $notifications->map(function ($notification) {
                $isRead = ! is_null($notification->read_at);
                $statusBadge = $isRead
                    ? '<span class="badge bg-success-lt">'.__('labels.read').'</span>'
                    : '<span class="badge bg-warning-lt">'.__('labels.unread').'</span>';

                $type = $notification->data['type'] ?? 'general';
                $typeBadge = '<span class="badge bg-azure-lt">'.e(ucfirst(str_replace('_', ' ', $type))).'</span>';

                return [
                    'title' => e($notification->title ?? ''),
                    'message' => e(Str::limit($notification->message ?? '', 150)),
                    'type' => $typeBadge,
                    'status' => $statusBadge,
                    'created_at' => $notification->created_at?->format('d M Y H:i'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller notifications datatable error: '.$e->getMessage());

            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function getSellers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Seller::class);

        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->input('search.value', '');

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columns = ['id', 'seller', 'email', 'mobile', 'verification_status', 'visibility_status', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = Seller::with(['user', 'stores', 'activeSubscription.plan'])->whereHas('user');
        $totalRecords = Seller::count();
        $filteredRecords = $totalRecords;

        // Search filter
        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->whereHas('user', function ($uq) use ($searchValue) {
                    $uq->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('email', 'like', "%{$searchValue}%")
                        ->orWhere('mobile', 'like', "%{$searchValue}%");
                })
                    ->orWhere('verification_status', 'like', "%{$searchValue}%")
                    ->orWhere('visibility_status', 'like', "%{$searchValue}%");
            });
            $filteredRecords = $query->count();
        }

        if (in_array($orderColumn, ['seller', 'email', 'mobile'])) {
            $query->join('users', 'sellers.user_id', '=', 'users.id');
            if ($orderColumn === 'seller') {
                $query->orderBy('users.name', $orderDirection);
            } elseif ($orderColumn === 'email') {
                $query->orderBy('users.email', $orderDirection);
            } else {
                $query->orderBy('users.mobile', $orderDirection);
            }
            $query->select('sellers.*');
        } else {
            $query->orderBy($orderColumn, $orderDirection);
        }

        $sellers = $query->skip($start)->take($length)->get();

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        $demo = $this->isDemoModeEnabled();
        $data = $sellers->map(function ($seller) use ($editPermission, $deletePermission, $demo) {
            $stores_count = count($seller->stores);
            $email = $seller->user->email ?? '';
            $mobile = $seller->user->mobile ?? '';

            return [
                'id' => $seller->id,
                'seller' => '<a href="'.route('admin.sellers.show', ['id' => $seller->id]).'" title="'.__('labels.view').'">'.($seller->user->name ?? '').'</i></a>',
                'email' => $demo ? Str::mask($email, '****', 3, 4) : $email,
                'mobile' => $demo ? Str::mask($mobile, '****', 3, 4) : $mobile,
                'verification_status' => view('partials.status', ['status' => $seller->verification_status])->render(),
                'visibility_status' => view('partials.status', ['status' => $seller->visibility_status])->render(),
                'stores' => '<a href="'.route('admin.sellers.store.show.index', ['id' => $seller->id]).'" class="'.($stores_count <= 0 ? 'pointer-events-none' : '').'"><span class="badge bg-info-lt text-uppercase">'.$stores_count.' '.($stores_count > 1 ? __('labels.stores') : __('labels.store')).'</span></a>',
                'plan' => $seller->activeSubscription?->plan?->name ?? 'N/A',
                'created_at' => $seller->created_at->toDateTimeString(),
                'action' => view('partials.actions', [
                    'modelName' => 'seller',
                    'id' => $seller->id,
                    'title' => $seller->user->name,
                    'mode' => 'page_view',
                    'route' => route('admin.sellers.edit', ['id' => $seller->id]),
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission,
                ])->render(),
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('search', '');
        if (! empty($request->input('find'))) {
            $sellers = Seller::whereHas('user', function ($q) use ($request) {
                $q->where('name', $request->input('find'));
            })->with('user')->get();
        } else {
            $sellers = Seller::whereHas('user', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%")
                        ->orWhere('mobile', 'like', "%$query%");
                });
            })
                ->with('user')
                ->limit(20)
                ->get();
        }
        // Format for TomSelect
        $demo = $this->isDemoModeEnabled();
        $results = $sellers->map(function ($seller) use ($demo) {
            $email = $seller->user->email ?? '';
            $mobile = $seller->user->mobile ?? '';
            $maskedEmail = $demo ? Str::mask($email, '****', 3, 4) : $email;
            $maskedMobile = $demo ? Str::mask($mobile, '****', 3, 4) : $mobile;

            return [
                'id' => $seller->id,
                'value' => $seller->id,
                'text' => $seller->user->name,
                'email' => $maskedEmail,
                'mobile' => $maskedMobile,
                'customProperties' => '<span class="badge bg-info-lt">'.e($maskedEmail).'</span>',
            ];
        });

        return response()->json($results);
    }
}
