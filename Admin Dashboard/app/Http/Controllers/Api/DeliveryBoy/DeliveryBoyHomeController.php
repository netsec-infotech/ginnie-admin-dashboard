<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Enums\DeliveryBoy\DeliveryBoyAssignmentStatusEnum;
use App\Enums\DeliveryBoy\DeliveryBoyAssignmentTypeEnum;
use App\Enums\Store\StoreStatusEnum;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryFeedback;
use App\Models\Order;
use App\Models\Store;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('DeliveryBoy Home')]
class DeliveryBoyHomeController extends Controller
{
    /**
     * Delivery Boy Dashboard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get the authenticated delivery boy
            $deliveryBoy = $request->user()->deliveryBoy?->loadMissing('deliveryZone');

            if (!$deliveryBoy) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.delivery_boy_not_found'),
                    data: []
                );
            }

            // Get profile data
            $profileData = $this->getProfileData($deliveryBoy);

            // Get summary data
            $summaryData = $this->getSummaryData($deliveryBoy->id);

            // Get performance metrics
            $performanceMetrics = $this->getPerformanceMetrics($deliveryBoy->id);

            // Get today's progress
            $todayProgress = $this->getTodayProgress($deliveryBoy->id);

            // Get earnings analytics
            $earningsAnalytics = $this->getEarningsAnalytics($deliveryBoy->id);

            $data = [
                [
                    'key' => 'profile',
                    'value' => [
                        'deliveryBoy' => $profileData
                    ]
                ],
                [
                    'key' => 'summary',
                    'value' => $summaryData
                ],
                [
                    'key' => 'performanceMetrics',
                    'value' => $performanceMetrics
                ],
                [
                    'key' => 'todayProgress',
                    'value' => $todayProgress
                ],
                [
                    'key' => 'earningsAnalytics',
                    'value' => $earningsAnalytics
                ]
            ];

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.home_page_fetched_successfully'),
                data: $data
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_fetching_dashboard_data'),
                data: []
            );
        }
    }

    /**
     * Get profile data for delivery boy
     *
     * @param $deliveryBoy
     * @return array
     */
    private function getProfileData($deliveryBoy): array
    {
        $user = $deliveryBoy->user;

        // Get total deliveries count
        $totalDeliveries = Order::whereHas('deliveryBoyAssignments', function ($query) use ($deliveryBoy) {
            $query->where('delivery_boy_id', $deliveryBoy->id)
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED());
        })->count();

        // Get average rating
        $averageRating = DeliveryFeedback::where('delivery_boy_id', $deliveryBoy->id)
            ->avg('rating') ?? 0;

        // Get profile image URL
        $profileImage = $user?->getFirstMediaUrl('profile_image') ?? null;

        $stores = $this->getStoresInZone($deliveryBoy);

        return [
            'id' => $deliveryBoy->id,
            'fullName' => $deliveryBoy->full_name ?? $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profileImage' => $profileImage,
            'status' => $deliveryBoy->status ?? 'inactive',
            'rating' => round($averageRating, 1),
            'totalDeliveries' => $totalDeliveries,
            'deliveryZone' => $deliveryBoy->deliveryZone ? [
                'id' => $deliveryBoy->deliveryZone->id,
                'name' => $deliveryBoy->deliveryZone->name,
                'centerLatitude' => $deliveryBoy->deliveryZone->center_latitude,
                'centerLongitude' => $deliveryBoy->deliveryZone->center_longitude,
                'boundaryJson' => $deliveryBoy->deliveryZone->boundary_json,
            ] : [],
            'stores' => $stores,
        ];
    }

    /**
     * Get active stores assigned to the delivery boy's delivery zone.
     *
     * @param  mixed  $deliveryBoy
     * @return array<int, array<string, mixed>>
     */
    private function getStoresInZone($deliveryBoy): array
    {
        if (! $deliveryBoy->delivery_zone_id) {
            return [];
        }

        return Store::query()
            ->whereHas('zones', function ($query) use ($deliveryBoy) {
                $query->where('delivery_zones.id', $deliveryBoy->delivery_zone_id);
            })
            ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
            ->where('status', StoreStatusEnum::ONLINE())
            ->orderBy('name')
            ->get()
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'address' => $store->address,
                'city' => $store->city,
                'state' => $store->state,
                'zipcode' => $store->zipcode,
                'country' => $store->country,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'contact_number' => $store->contact_number,
                'contact_email' => $store->contact_email,
                'logo' => $store->store_logo,
                'banner' => $store->store_banner,
                'status' => $store->checkStoreStatus(),
                'order_preparation_time' => $store->order_preparation_time,
            ])
            ->values()
            ->all();
    }

    /**
     * Get summary data for different time periods
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getSummaryData(int $deliveryBoyId): array
    {
        $now = Carbon::now();

        // Today's data
        $todayData = $this->getDataForPeriod($deliveryBoyId, $now->copy()->startOfDay(), $now->copy()->endOfDay());

        // This week's data
        $weekData = $this->getDataForPeriod($deliveryBoyId, $now->copy()->startOfWeek(), $now->copy()->endOfWeek());

        // This month's data
        $monthData = $this->getDataForPeriod($deliveryBoyId, $now->copy()->startOfMonth(), $now->copy()->endOfMonth());

        // Total data
        $totalData = $this->getDataForPeriod($deliveryBoyId, null, null);

        return [
            'today' => $todayData,
            'thisWeek' => $weekData,
            'thisMonth' => $monthData,
            'total' => $totalData
        ];
    }

    /**
     * Get data for a specific time period
     *
     * @param int $deliveryBoyId
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array
     */
    private function getDataForPeriod(int $deliveryBoyId, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $query = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoyId)
            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
            ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
            ->whereHas('order');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $assignments = $query->get();

        $earnings = $assignments->sum('total_earnings') ?? 0;
        $orders = $assignments->count();

        // Calculate average rating for the period
        $ratingQuery = DeliveryFeedback::where('delivery_boy_id', $deliveryBoyId);
        if ($startDate && $endDate) {
            $ratingQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $rating = $ratingQuery->avg('rating') ?? 0;

        return [
            'earnings' => (float)$earnings,
            'orders' => $orders,
            'rating' => round($rating, 1)
        ];
    }

    /**
     * Get performance metrics
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getPerformanceMetrics(int $deliveryBoyId): array
    {
        $ordersDelivered = Order::whereHas('deliveryBoyAssignments', function ($query) use ($deliveryBoyId) {
            $query->where('delivery_boy_id', $deliveryBoyId)
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED());
        })->count();
        $averageRating = DeliveryFeedback::where('delivery_boy_id', $deliveryBoyId)->avg('rating') ?? 0;

        return [
            'ordersDelivered' => $ordersDelivered,
            'averageRating' => round($averageRating, 1),
        ];
    }

    /**
     * Get today's progress
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getTodayProgress(int $deliveryBoyId): array
    {
        $today = Carbon::today();
        $todayAssignments = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoyId)
            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
            ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
            ->whereHas('order')
            ->whereDate('created_at', $today)
            ->get();

        $earnings = $todayAssignments->sum('total_earnings') ?? 0;
        $trips = $todayAssignments->count();

        // Placeholder for sessions - you can implement actual session tracking
        $sessions = '00:00';

        // Gigs could be the same as trips or different based on your business logic
        $gigs = $trips;

        return [
            'earnings' => (float)$earnings,
            'trips' => $trips,
            'sessions' => $sessions,
            'gigs' => $gigs
        ];
    }

    /**
     * Get earnings analytics with charts data
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getEarningsAnalytics(int $deliveryBoyId): array
    {
        // Get summary data
        $totalAssignments = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoyId)
            ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
            ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
            ->whereHas('order')
            ->get();

        $totalEarnings = $totalAssignments->sum('total_earnings') ?? 0;
        $totalOrders = Order::whereHas('deliveryBoyAssignments', function ($query) use ($deliveryBoyId) {
            $query->where('delivery_boy_id', $deliveryBoyId)
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED());
        })->count();
        $averageEarnings = $totalOrders > 0 ? $totalEarnings / $totalOrders : 0;
        $averageRating = DeliveryFeedback::where('delivery_boy_id', $deliveryBoyId)->avg('rating') ?? 0;

        // Get weekly data
        $weeklyData = $this->getWeeklyEarningsData($deliveryBoyId);

        // Get monthly data
        $monthlyData = $this->getMonthlyEarningsData($deliveryBoyId);

        // Get yearly data
        $yearlyData = $this->getYearlyEarningsData($deliveryBoyId);

        return [
            'summary' => [
                'totalEarnings' => (float)$totalEarnings,
                'averageEarnings' => round($averageEarnings, 2),
                'totalOrders' => $totalOrders,
                'averageRating' => round($averageRating, 1)
            ],
            'charts' => [
                'weekly' => [
                    'period' => 'Week',
                    'data' => $weeklyData
                ],
                'monthly' => [
                    'period' => 'Month',
                    'data' => $monthlyData
                ],
                'yearly' => [
                    'period' => 'Year',
                    'data' => $yearlyData
                ]
            ]
        ];
    }

    /**
     * Get weekly earnings data
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getWeeklyEarningsData(int $deliveryBoyId): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $data = [];

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $dayAssignments = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoyId)
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
                ->whereHas('order')
                ->whereDate('created_at', $date)
                ->get();

            $data[] = [
                'day' => $days[$i],
                'earnings' => (float)($dayAssignments->sum('total_earnings') ?? 0),
                'orders' => $dayAssignments->count()
            ];
        }

        return $data;
    }

    /**
     * Get monthly earnings data
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getMonthlyEarningsData(int $deliveryBoyId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth()->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // Keep a stable 4-bucket shape for the app, but cover the full month:
        // Week 1: days 1-7, Week 2: 8-14, Week 3: 15-21, Week 4: 22-endOfMonth.
        $ranges = [
            [1, 7],
            [8, 14],
            [15, 21],
            [22, null],
        ];

        $data = [];

        foreach ($ranges as $index => [$fromDay, $toDay]) {
            $weekStart = $startOfMonth->copy()->day($fromDay)->startOfDay();
            $weekEnd = $toDay
                ? $startOfMonth->copy()->day($toDay)->endOfDay()
                : $endOfMonth;

            // Clamp just in case we are in a short month (e.g. Feb).
            $weekStart = $weekStart->greaterThan($endOfMonth) ? $endOfMonth->copy() : $weekStart;
            $weekEnd = $weekEnd->greaterThan($endOfMonth) ? $endOfMonth->copy() : $weekEnd;

            $weekAssignments = DeliveryBoyAssignment::query()
                ->where('delivery_boy_id', $deliveryBoyId)
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
                ->whereHas('order')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->get();

            $data[] = [
                'week' => 'Week ' . ($index + 1),
                'earnings' => (float) ($weekAssignments->sum('total_earnings') ?? 0),
                'orders' => $weekAssignments->count(),
            ];
        }

        return $data;
    }

    /**
     * Get yearly earnings data
     *
     * @param int $deliveryBoyId
     * @return array
     */
    private function getYearlyEarningsData(int $deliveryBoyId): array
    {
        $currentYear = Carbon::now()->year;
        $data = [];

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        for ($month = 1; $month <= 12; $month++) {
            $monthStart = Carbon::create($currentYear, $month, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $monthAssignments = DeliveryBoyAssignment::where('delivery_boy_id', $deliveryBoyId)
                ->where('assignment_type', DeliveryBoyAssignmentTypeEnum::DELIVERY())
                ->where('status', DeliveryBoyAssignmentStatusEnum::COMPLETED())
                ->whereHas('order')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->get();

            $earnings = (float)($monthAssignments->sum('total_earnings') ?? 0);
            $orders = $monthAssignments->count();

            // Calculate percentage (placeholder logic)
            $percentage = $earnings > 0 ? min(100, ($earnings / 1000) * 10) : 0;

            $data[] = [
                'month' => $months[$month - 1],
                'earnings' => $earnings,
                'orders' => $orders,
                'percentage' => round($percentage, 0)
            ];
        }

        return $data;
    }
}
