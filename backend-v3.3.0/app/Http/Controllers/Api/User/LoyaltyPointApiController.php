<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyPointTransactionResource;
use App\Models\LoyaltyPointTransaction;
use App\Services\LoyaltyPointService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyPointApiController extends Controller
{
    public function __construct(protected LoyaltyPointService $loyaltyService)
    {
    }

    /**
     * Get paginated user loyalty point transaction history with summary statistics.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type');
        $perPage = (int) $request->query('per_page', 15);

        // Expiry date filtering parameters
        $expiryFilter = $request->query('expiry_filter') ?? $request->query('expiry_status');
        $expiresInDays = $request->query('expires_in_days');
        $expiresFrom = $request->query('expires_from');
        $expiresTo = $request->query('expires_to');

        $settings = $this->loyaltyService->getSettings();
        $isLoyaltyEnabled = (bool) ($settings['is_enabled'] ?? false);
        $redemptionValue = (float) ($settings['redemption_value'] ?? 1.0);

        $pointsBalance = $this->loyaltyService->getActivePointsBalance($user->id);
        $equivalentMonetaryValue = round($pointsBalance * $redemptionValue, 2);
        $expiringIn30Days = $this->loyaltyService->getPointsExpiringSoon($user->id, 30);

        $query = LoyaltyPointTransaction::where('user_id', $user->id);

        if ($type) {
            $query->where('type', $type);
        }

        // Apply Expiry Date Filtering
        if ($expiresInDays !== null && $expiresInDays !== '') {
            $days = (int) $expiresInDays;
            $query->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays($days)]);
        } elseif ($expiryFilter) {
            if (in_array($expiryFilter, ['expiring', 'expiring_soon', 'expiring_30_days'], true)) {
                $query->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(30)]);
            } elseif ($expiryFilter === 'expired') {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            } elseif (in_array($expiryFilter, ['never_expires', 'no_expiry'], true)) {
                $query->whereNull('expires_at');
            }
        }

        if ($expiresFrom) {
            $query->where('expires_at', '>=', \Illuminate\Support\Carbon::parse($expiresFrom)->startOfDay());
        }
        if ($expiresTo) {
            $query->where('expires_at', '<=', \Illuminate\Support\Carbon::parse($expiresTo)->endOfDay());
        }

        $transactions = $query->orderByDesc('created_at')->paginate($perPage);

        return ApiResponseType::sendJsonResponse(true, 'labels.success', [
            'is_enabled' => $isLoyaltyEnabled,
            'points_balance' => $pointsBalance,
            'redemption_value' => $redemptionValue,
            'equivalent_monetary_value' => $equivalentMonetaryValue,
            'expiring_points_in_30_days' => $expiringIn30Days,
            'transactions' => LoyaltyPointTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
