<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyPointTransaction;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CurrencyService;
use App\Services\CustomerService;
use App\Services\LoyaltyPointService;
use App\Services\SettingService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    protected SettingService $settingService;
    protected CurrencyService $currencyService;
    protected CustomerService $customerService;

    public function __construct(
        SettingService $settingService,
        CurrencyService $currencyService,
        CustomerService $customerService
    ) {
        $this->settingService = $settingService;
        $this->currencyService = $currencyService;
        $this->customerService = $customerService;
    }

    protected function isDemoModeEnabled(): bool
    {
        try {
            $resource = $this->settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
            $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];
            return (bool)($settings['demoMode'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(): View
    {
        // Customer listing is controlled by explicit permission, not by the SystemUser policy
        if (!$this->hasPermission(AdminPermissionEnum::CUSTOMER_VIEW())) {
            abort(403, trans('labels.permission_denied'));
        }
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
            ['data' => 'details', 'name' => 'details', 'title' => __('labels.details')],
            ['data' => 'wallet_balance', 'name' => 'wallet_balance', 'title' => __('labels.wallet_balance')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];

        return view('admin.customers.index', compact('columns'));
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!$this->hasPermission(AdminPermissionEnum::CUSTOMER_VIEW())) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }

        $draw = $request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'name', 'email', 'mobile', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        // Customers: users that have role 'customer' and do not have admin/seller access panel
        $query = User::query()->with('wallet')
            ->where(function ($q) {
                $q->whereNull('access_panel')
                    ->orWhere('access_panel', 'web');
            });

        $totalRecords = $query->count();

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('email', 'like', "%{$searchValue}%")
                    ->orWhere('mobile', 'like', "%{$searchValue}%");
            });
        }

        $filteredRecords = $query->count();

        $demo = $this->isDemoModeEnabled();
        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($user) use ($demo) {
                $email = $user->email ?? '';
                $mobile = $user->mobile ?? '';
                $showUrl = route('admin.customers.show', $user->id);
                return [
                    'id' => $user->id,
                    'name' => '<a href="' . $showUrl . '" class="fw-bold">' . e($user->name) . '</a>',
                    'details' => $demo
                        ? Str::mask($email, '****', 3, 4) . ' / ' . Str::mask($mobile, '****', 3, 4)
                        : $email . ' / ' . $mobile,
                    'wallet_balance' => $this->currencyService->format($user?->wallet->balance ?? 0),
                    'created_at' => $user->created_at?->format('Y-m-d'),
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Display customer detail page.
     */
    public function show(string $id): View
    {
        if (!$this->hasPermission(AdminPermissionEnum::CUSTOMER_VIEW())) {
            abort(403, trans('labels.permission_denied'));
        }

        $customer = User::with(['wallet'])->find($id);

        if (!$customer) {
            abort(404);
        }

        $orderStats = $this->customerService->getOrderStats($customer);
        $recentOrders = $this->customerService->getRecentOrders($customer);
        $addresses = $this->customerService->getAddresses($customer);
        $recentNotifications = $this->customerService->getRecentNotifications($customer);

        $profileImageUrl = $customer->profile_image ?: asset('assets/images/user-placeholder.png');

        $orderColumns = [
            ['data' => 'id', 'name' => 'id', 'title' => '#', 'label' => '#'],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'payment_status', 'name' => 'payment_status', 'title' => __('labels.payment_status'), 'label' => __('labels.payment_status')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'label' => __('labels.payment_method')],
            ['data' => 'total', 'name' => 'total', 'title' => __('labels.total'), 'label' => __('labels.total')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $walletColumns = [
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
            ['data' => 'transaction_type', 'name' => 'transaction_type', 'title' => __('labels.type'), 'label' => __('labels.type')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount'), 'label' => __('labels.amount')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method'), 'label' => __('labels.payment_method')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description')],
        ];

        $notificationColumns = [
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
            ['data' => 'message', 'name' => 'message', 'title' => __('labels.message'), 'label' => __('labels.message')],
            ['data' => 'type', 'name' => 'type', 'title' => __('labels.type'), 'label' => __('labels.type')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $loyaltyService = app(LoyaltyPointService::class);
        $loyaltyPointsBalance = $loyaltyService->getActivePointsBalance($customer->id);

        $loyaltyColumns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id'), 'label' => __('labels.id')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
            ['data' => 'type', 'name' => 'type', 'title' => __('labels.type'), 'label' => __('labels.type')],
            ['data' => 'points', 'name' => 'points', 'title' => __('labels.points_amount'), 'label' => __('labels.points_amount')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'expires_at', 'name' => 'expires_at', 'title' => __('labels.expiry_days'), 'label' => __('labels.expiry_days')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description')],
        ];

        return view('admin.customers.view', compact(
            'customer',
            'orderStats',
            'recentOrders',
            'addresses',
            'recentNotifications',
            'profileImageUrl',
            'orderColumns',
            'walletColumns',
            'notificationColumns',
            'loyaltyColumns',
            'loyaltyPointsBalance',
        ));
    }

    /**
     * Server-side DataTable for customer orders.
     */
    public function ordersDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $customer = User::findOrFail($id);

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = Order::where('user_id', $customer->id);
            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('uuid', 'like', "%{$searchValue}%")
                        ->orWhere('status', 'like', "%{$searchValue}%")
                        ->orWhere('payment_method', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $orders = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $orders->map(function ($order) {
                $statusClass = match ($order->status) {
                    'delivered' => 'bg-success-lt',
                    'cancelled', 'failed' => 'bg-danger-lt',
                    'pending' => 'bg-warning-lt',
                    default => 'bg-info-lt',
                };

                $paymentClass = match ($order->payment_status) {
                    'paid', 'completed' => 'bg-success-lt',
                    'failed', 'refunded' => 'bg-danger-lt',
                    default => 'bg-warning-lt',
                };

                return [
                    'id' => '#' . $order->id,
                    'status' => '<span class="badge ' . $statusClass . '">' . e(ucfirst(str_replace('_', ' ', $order->status))) . '</span>',
                    'payment_status' => '<span class="badge ' . $paymentClass . '">' . e(ucfirst(str_replace('_', ' ', $order->payment_status ?? 'N/A'))) . '</span>',
                    'payment_method' => e(ucfirst(str_replace('_', ' ', $order->payment_method ?? 'N/A'))),
                    'total' =>getCurrencySymbol() . number_format($order->final_total, 2),
                    'created_at' => $order->created_at?->format('d M Y H:i'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Customer orders datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Server-side DataTable for customer wallet transactions.
     */
    public function walletDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $customer = User::with('wallet')->findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $walletId = $customer->wallet?->id;
            if (!$walletId) {
                return response()->json([
                    'draw' => intval($draw),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                ]);
            }

            $query = WalletTransaction::where('wallet_id', $walletId);
            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('transaction_type', 'like', "%{$searchValue}%")
                        ->orWhere('payment_method', 'like', "%{$searchValue}%")
                        ->orWhere('status', 'like', "%{$searchValue}%")
                        ->orWhere('description', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $transactions = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $transactions->map(function ($transaction) {
                $typeBadge = '<span class="badge bg-azure-lt">' . e(ucfirst(str_replace('_', ' ', $transaction->transaction_type ?? ''))) . '</span>';

                $statusClass = match ($transaction->status) {
                    'completed', 'paid' => 'bg-success-lt',
                    'failed', 'refunded' => 'bg-danger-lt',
                    default => 'bg-warning-lt',
                };

                $statusBadge = '<span class="badge ' . $statusClass . '">' . e(ucfirst(str_replace('_', ' ', $transaction->status ?? ''))) . '</span>';

                return [
                    'created_at' => $transaction->created_at?->format('d M Y H:i'),
                    'transaction_type' => $typeBadge,
                    'amount' => getCurrencySymbol() . number_format((float) $transaction->amount, 2),
                    'payment_method' => e(ucfirst(str_replace('_', ' ', $transaction->payment_method ?? 'N/A'))),
                    'status' => $statusBadge,
                    'description' => e(Str::limit($transaction->description, 150)),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Customer wallet datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Server-side DataTable for customer notifications.
     */
    public function notificationsDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $customer = User::findOrFail($id);

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = Notification::where('notifiable_id', $customer->id)
                ->where('notifiable_type', User::class)
                ->roleType(NotificationRoleTypeEnum::CUSTOMER);

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
                $isRead = !is_null($notification->read_at);
                $statusBadge = $isRead
                    ? '<span class="badge bg-success-lt">' . __('labels.read') . '</span>'
                    : '<span class="badge bg-warning-lt">' . __('labels.unread') . '</span>';

                $type = $notification->data['type'] ?? 'general';
                $typeBadge = '<span class="badge bg-azure-lt">' . e(ucfirst(str_replace('_', ' ', $type))) . '</span>';

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
            Log::error('Customer notifications datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Export customers as CSV
     */
    public function export(Request $request)
    {
        if (!$this->hasPermission(AdminPermissionEnum::CUSTOMER_EXPORT())) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }

        $filename = 'customers_' . now()->format('Y_m_d_H_i_s') . '.csv';

        $callback = function () {
            $handle = fopen('php://output', 'w');
            // CSV Header
            fputcsv($handle, ['ID', 'Name', 'Email', 'Mobile', 'Created At']);

            User::query()
                ->where(function ($q) {
                    $q->whereNull('access_panel')
                        ->orWhere('access_panel', 'web');
                })
                ->orderBy('id', 'desc')
                ->chunk(500, function ($users) use ($handle) {
                    foreach ($users as $user) {
                        fputcsv($handle, [
                            $user->id,
                            $user->name,
                            $user->email,
                            $user->mobile,
                            optional($user->created_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        };

        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Server-side DataTable for customer loyalty points transactions.
     */
    public function loyaltyDatatable(Request $request, string $id): JsonResponse
    {
        try {
            $customer = User::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = LoyaltyPointTransaction::where('user_id', $customer->id);
            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('type', 'like', "%{$searchValue}%")
                        ->orWhere('status', 'like', "%{$searchValue}%")
                        ->orWhere('description', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $transactions = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $transactions->map(function ($transaction) {
                $typeValue = (string) (is_callable($transaction->type) ? ($transaction->type)() : ($transaction->type->value ?? $transaction->type));
                $statusValue = (string) (is_callable($transaction->status) ? ($transaction->status)() : ($transaction->status->value ?? $transaction->status));

                $typeClass = match ($typeValue) {
                    'earned', 'refunded' => 'bg-success-lt',
                    'redeemed', 'expired' => 'bg-danger-lt',
                    default => 'bg-info-lt',
                };
                $typeBadge = '<span class="badge ' . $typeClass . '">' . e(ucfirst(str_replace('_', ' ', $typeValue))) . '</span>';

                $statusClass = match ($statusValue) {
                    'credited' => 'bg-success-lt',
                    'debited' => 'bg-danger-lt',
                    'cancelled' => 'bg-secondary-lt',
                    default => 'bg-warning-lt',
                };
                $statusBadge = '<span class="badge ' . $statusClass . '">' . e(ucfirst(str_replace('_', ' ', $statusValue))) . '</span>';

                $pointsFormatted = ($transaction->points > 0 ? '+' : '') . $transaction->points;

                return [
                    'id' => '#' . $transaction->id,
                    'created_at' => $transaction->created_at?->format('d M Y H:i'),
                    'type' => $typeBadge,
                    'points' => '<span class="fw-bold ' . ($transaction->points > 0 ? 'text-success' : 'text-danger') . '">' . $pointsFormatted . '</span>',
                    'status' => $statusBadge,
                    'expires_at' => $transaction->expires_at ? $transaction->expires_at->format('d M Y') : '-',
                    'description' => e(Str::limit($transaction->description ?? '', 150)),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Customer loyalty datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Admin manual loyalty points adjustment.
     */
    public function adjustLoyaltyPoints(Request $request, string $id): JsonResponse
    {
        if (! $this->hasPermission(AdminPermissionEnum::CUSTOMER_MANAGE())) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }

        $request->validate([
            'point_action' => 'nullable|in:credit,debit',
            'action' => 'nullable|in:credit,debit',
            'points' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            $customer = User::findOrFail($id);
            $action = $request->input('point_action') ?? $request->input('action') ?? 'credit';
            $points = (int) $request->input('points');
            $note = $request->input('note') ?? ($action === 'credit' ? 'Admin manual credit' : 'Admin manual debit');

            $adjustPoints = ($action === 'credit') ? $points : -$points;
            $status = ($action === 'credit')
                ? \App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum::CREDITED()
                : \App\Enums\LoyaltyPoint\LoyaltyPointTransactionStatusEnum::DEBITED();

            $loyaltyService = app(LoyaltyPointService::class);
            $transaction = $loyaltyService->adjustBalance(
                userId: $customer->id,
                points: $adjustPoints,
                type: \App\Enums\LoyaltyPoint\LoyaltyPointTransactionTypeEnum::ADMIN_ADJUSTMENT(),
                description: $note,
                status: $status
            );

            $newBalance = $loyaltyService->getActivePointsBalance($customer->id);

            return ApiResponseType::sendJsonResponse(true, 'labels.loyalty_points_adjusted_successfully', [
                'points_balance' => $newBalance,
                'transaction' => $transaction,
            ]);
        } catch (Exception $e) {
            Log::error('Admin adjust loyalty points error: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }
}
