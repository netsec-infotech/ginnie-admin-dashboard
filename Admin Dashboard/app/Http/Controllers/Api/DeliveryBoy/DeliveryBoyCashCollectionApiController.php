<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Enums\DateRangeFilterEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryBoyAssignment;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('DeliveryBoy Cash Collection')]
class DeliveryBoyCashCollectionApiController extends Controller
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
     * Get cash collection history for the authenticated delivery boy with filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[QueryParameter('date_range', description: 'date range', type: 'string', example: "last_30_days")]
    #[QueryParameter('submission_status', description: 'Submission status', type: 'string', example: "pending,partially_submitted,submitted")]
    public function getCashCollections(Request $request): JsonResponse
    {
        try {
            // Get the authenticated delivery boy
            $deliveryBoy = $request->user()->deliveryBoy;

            // Build the query
            $query = DeliveryBoyAssignment::query()
                ->with(['order'])
                ->where('delivery_boy_id', $deliveryBoy->id)
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
                ->where('cod_cash_collected', '>', 0);

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

            // Apply submission status filter if provided
            if ($request->has('submission_status')) {
                $submissionStatus = $request->submission_status;
                if (in_array($submissionStatus, ['pending', 'partially_submitted', 'submitted'])) {
                    $query->where('cod_submission_status', $submissionStatus);
                }
            }

            // Get paginated results
            $perPage = $request->get('per_page', 15);
            $cashCollections = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Format the response data
            $formattedCashCollections = $cashCollections->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'order_id' => $assignment->order_id,
                    'order_date' => $assignment->order->created_at->format('Y-m-d'),
                    'cash_collected' => $assignment->cod_cash_collected,
                    'cash_submitted' => $assignment->cod_cash_submitted,
                    'remaining_amount' => $assignment->cod_cash_collected - $assignment->cod_cash_submitted,
                    'submission_status' => $assignment->cod_submission_status,
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.data_retrieved_successfully'),
                data: [
                    'total' => $cashCollections->total(),
                    'per_page' => $cashCollections->perPage(),
                    'current_page' => $cashCollections->currentPage(),
                    'last_page' => $cashCollections->lastPage(),
                    'cash_collections' => $formattedCashCollections,
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
     * Get cash collection statistics for the authenticated delivery boy
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
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
                ->where('cod_cash_collected', '>', 0);

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

            // Get statistics
            $statistics = [
                'total_cash_collected' => $query->sum('cod_cash_collected'),
                'total_cash_submitted' => $query->sum('cod_cash_submitted'),
                'total_remaining' => $query->sum('cod_cash_collected') - $query->sum('cod_cash_submitted'),
                'total_orders' => $query->count(),
                'status_breakdown' => [
                    'pending' => $query->clone()->where('cod_submission_status', 'pending')->count(),
                    'partially_submitted' => $query->clone()->where('cod_submission_status', 'partially_submitted')->count(),
                    'submitted' => $query->clone()->where('cod_submission_status', 'submitted')->count(),
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
     * Get available date ranges for cash collection filtering
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

    /**
     * Initiate online settlement for one or more COD assignments.
     * 
     * [CHANGE] Added endpoint to initiate online settlement for single or bulk assignments
     */
    public function settleOnline(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'delivery_boy_assignment_ids' => 'required|array|min:1',
                'delivery_boy_assignment_ids.*' => 'required|integer|exists:delivery_boy_assignments,id',
            ]);

            $deliveryBoy = $request->user()->deliveryBoy;
            $assignmentIds = $request->input('delivery_boy_assignment_ids');

            // Find valid completed COD assignments with remaining cash for this rider
            $assignments = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoy->id)
                ->whereIn('id', $assignmentIds)
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
                ->whereIn('cod_submission_status', ['pending', 'partially_submitted'])
                ->where('cod_cash_collected', '>', 0)
                ->get();

            if ($assignments->isEmpty()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'No pending COD assignments found for the provided IDs.',
                    data: []
                );
            }

            // Calculate total sum of remaining cash to be paid
            $totalAmount = 0.0;
            $allocations = [];

            foreach ($assignments as $assignment) {
                $remaining = (float) ($assignment->cod_cash_collected - $assignment->cod_cash_submitted);
                if ($remaining > 0) {
                    $totalAmount += $remaining;
                    $allocations[$assignment->id] = $remaining;
                }
            }

            if ($totalAmount <= 0) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'Selected orders are already settled.',
                    data: []
                );
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            // Create online settlement session
            $settlement = \App\Models\DeliveryBoyOnlineSettlement::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'delivery_boy_id' => $deliveryBoy->id,
                'amount' => $totalAmount,
                'status' => 'pending',
            ]);

            // Create pivot records
            foreach ($allocations as $assignmentId => $amount) {
                $settlement->assignments()->attach($assignmentId, ['amount' => $amount]);
            }

            \Illuminate\Support\Facades\DB::commit();

            // Resolve the correct absolute URL for the payment link
            $paymentUrl = route('cash-collection-payment.pay', ['uuid' => $settlement->uuid]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'Online cash collection settlement initiated successfully.',
                data: [
                    'settlement_uuid' => $settlement->uuid,
                    'amount' => $totalAmount,
                    'payment_link' => $paymentUrl,
                ]
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

}
