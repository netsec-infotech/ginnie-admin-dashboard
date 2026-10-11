<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Enums\DateRangeFilterEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\EarningPaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryBoyAssignment;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('DeliveryBoy Earnings')]
class DeliveryBoyEarningApiController extends Controller
{
    /**
     * Apply date range filter based on DateRangeFilterEnum
     *
     * @param Builder $query
     * @param string|null $dateRangeFilter
     * @return Builder
     */
    private function applyDateRangeFilter(Builder $query, ?string $dateRangeFilter): Builder
    {
        if (!$dateRangeFilter) {
            return $query;
        }

        $now = Carbon::now();

        switch ($dateRangeFilter) {
            case DateRangeFilterEnum::LAST_30_MINUTES():
                return $query->where('created_at', '>=', $now->copy()->subMinutes(30));
            case DateRangeFilterEnum::LAST_1_HOUR():
                return $query->where('created_at', '>=', $now->copy()->subHour());
            case DateRangeFilterEnum::LAST_5_HOURS():
                return $query->where('created_at', '>=', $now->copy()->subHours(5));
            case DateRangeFilterEnum::LAST_1_DAY():
                return $query->where('created_at', '>=', $now->copy()->subDay());
            case DateRangeFilterEnum::LAST_7_DAYS():
                return $query->where('created_at', '>=', $now->copy()->subDays(7));
            case DateRangeFilterEnum::LAST_30_DAYS():
                return $query->where('created_at', '>=', $now->copy()->subDays(30));
            case DateRangeFilterEnum::LAST_365_DAYS():
                return $query->where('created_at', '>=', $now->copy()->subDays(365));
            default:
                return $query;
        }
    }

    /**
     * Get earnings for the authenticated delivery boy with filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[QueryParameter('date_range', description: 'date range', type: 'string', example: "last_30_days")]
    #[QueryParameter('payment_status', description: 'Payment status', type: 'string', example: "pending,paid")]
    public function getEarnings(Request $request): JsonResponse
    {
        try {
            // Get the authenticated delivery boy
            $deliveryBoy = $request->user()->deliveryBoy;

            // Build the query
            $query = DeliveryBoyAssignment::query()
                ->with(['order'])
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED());

            // Apply date range filter if provided
            if ($request->has('date_range')) {
                if ($request->date_range && !DateRangeFilterEnum::tryFrom($request->date_range)) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.invalid_date_range'),
                        data: []
                    );
                }
                $this->applyDateRangeFilter($query, $request->date_range);
            }

            // Apply payment status filter if provided
            if ($request->has('payment_status')) {
                $paymentStatus = $request->payment_status;
                if (in_array($paymentStatus, [EarningPaymentStatusEnum::PENDING(), EarningPaymentStatusEnum::PAID()])) {
                    $query->where('payment_status', $paymentStatus);
                }
            }

            // Get overall (allover) total earnings for the delivery boy (filter wise)
            $totalEarnings = (float) $query->clone()->sum('total_earnings');

            // [CHANGE] Calculate remaining cash amount delivery boy can collect
            $unsettledCash = $deliveryBoy->unsettled_cash;
            $settingService = app(\App\Services\SettingService::class);
            $settings = $settingService->getSettingByVariable(\App\Enums\SettingTypeEnum::DELIVERY_BOY());
            $status = (bool) ($settings->value['cashCollectionLimitStatus'] ?? false);
            $cashLimit = $status ? (float) ($settings->value['cashCollectionLimitAmount'] ?? 0) : 0;
            $remainingCashAmount = max(0, $cashLimit - $unsettledCash);

            // Get paginated results
            $perPage = $request->get('per_page', 15);
            $earnings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Format the response data
            $formattedEarnings = $earnings->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'order_id' => $assignment->order_id,
                    'order_item_id' => $assignment->order_item_id,
                    'assignment_type' => $assignment->assignment_type,
                    'status' => $assignment->status,
                    'order_date' => $assignment->order->created_at->format('Y-m-d'),
                    'earnings' => [
                        'base_fee' => $assignment->base_fee,
                        'per_store_pickup_fee' => $assignment->per_store_pickup_fee,
                        'distance_based_fee' => $assignment->distance_based_fee,
                        'per_order_incentive' => $assignment->per_order_incentive,
                        'total' => $assignment->total_earnings,
                    ],
                    'payment_status' => $assignment->payment_status,
                    'paid_at' => $assignment->paid_at ?? null,
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.data_retrieved_successfully'),
                data: [
                    'total_earnings' => $totalEarnings,
                    'remaining_cash_amount' => $remainingCashAmount,
                    'is_cashlimit_enabled' => $status,
                    'total' => $earnings->total(),
                    'per_page' => $earnings->perPage(),
                    'current_page' => $earnings->currentPage(),
                    'last_page' => $earnings->lastPage(),
                    'earnings' => $formattedEarnings,
                ]
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_occurred'),
                data: [],
            );
        }
    }

    /**
     * Get earnings statistics for the authenticated delivery boy
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[QueryParameter('date_range', description: 'date range', type: 'string', example: "last_30_days")]
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            // Get the authenticated delivery boy
            $deliveryBoy = $request->user()->deliveryBoy;

            // Build the base query
            $query = DeliveryBoyAssignment::query()
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED());

            // Apply date range filter if provided
            if ($request->has('date_range')) {
                if ($request->date_range && !DateRangeFilterEnum::tryFrom($request->date_range)) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.invalid_date_range'),
                        data: []
                    );
                }
                $this->applyDateRangeFilter($query, $request->date_range);
            }

            // [CHANGE] Calculate remaining cash amount delivery boy can collect
            $unsettledCash = $deliveryBoy->unsettled_cash;
            $settingService = app(\App\Services\SettingService::class);
            $settings = $settingService->getSettingByVariable(\App\Enums\SettingTypeEnum::DELIVERY_BOY());
            $status = (bool) ($settings->value['cashCollectionLimitStatus'] ?? false);
            $cashLimit = $status ? (float) ($settings->value['cashCollectionLimitAmount'] ?? 0) : 0;
            $remainingCashAmount = max(0, $cashLimit - $unsettledCash);

            // Get statistics
            $statistics = [
                'total_earnings' => $query->sum('total_earnings'),
                'pending_earnings' => $query->clone()->where('payment_status', EarningPaymentStatusEnum::PENDING())->sum('total_earnings'),
                'paid_earnings' => $query->clone()->where('payment_status', EarningPaymentStatusEnum::PAID())->sum('total_earnings'),
                'remaining_cash_amount' => $remainingCashAmount,
                'is_cashlimit_enabled' => $status,
                'total_orders' => $query->count(),
                'earnings_breakdown' => [
                    'base_fee' => $query->sum('base_fee'),
                    'per_store_pickup_fee' => $query->sum('per_store_pickup_fee'),
                    'distance_based_fee' => $query->sum('distance_based_fee'),
                    'per_order_incentive' => $query->sum('per_order_incentive'),
                ],
            ];

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.data_retrieved_successfully'),
                data: $statistics
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_occurred'),
                data: [],
            );
        }
    }

    /**
     * Get available date ranges for earnings filtering
     *
     * @return JsonResponse
     */
    public function getEarningsByDateRange(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.date_range_retrieved_successfully'),
            data: DateRangeFilterEnum::cases(),
        );
    }

}
