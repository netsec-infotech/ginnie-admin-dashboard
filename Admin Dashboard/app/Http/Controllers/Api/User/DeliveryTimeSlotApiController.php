<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\CartService;
use App\Services\DeliveryScheduleService;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

#[Group('Delivery Time Slots')]
class DeliveryTimeSlotApiController extends Controller
{
    protected DeliveryScheduleService $deliveryScheduleService;

    protected CartService $cartService;

    public function __construct(DeliveryScheduleService $deliveryScheduleService, CartService $cartService)
    {
        $this->deliveryScheduleService = $deliveryScheduleService;
        $this->cartService = $cartService;
    }

    /**
     * Retrieve available time slots for a specific store on a target date.
     */
    public function index(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        if ($validator->fails()) {
            return ApiResponseType::sendJsonResponse(
                false,
                $validator->errors()->first(),
                [],
                422
            );
        }

        $cart = Auth::check() ? $this->cartService->getUserCart(Auth::user()) : null;
        $result = $this->deliveryScheduleService->getAvailableSlotsForStore($store, $request->input('date'));
        $result['slots'] = $this->filterSlotsByCartLeadTime($result['slots'] ?? [], $request->input('date'), $store, $cart);
        if (empty($result['slots'])) {
            $result['message'] = 'No available delivery time slots found for this store.';
        }

        if (! $result['is_enabled']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $result['message'] ?? __('messages.scheduled_delivery_disabled_for_store'),
                ['is_enabled' => false, 'slots' => []],
                400
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            $result['message'] ?? __('messages.slots_retrieved_successfully') ?? 'Delivery time slots retrieved successfully.',
            $result
        );
    }

    /**
     * Retrieve available dates for a specific store based on advance booking window and operating hours.
     */
    public function getDatesForStore(Store $store): JsonResponse
    {
        $result = $this->deliveryScheduleService->getAvailableDatesForStore($store);

        if (! $result['is_enabled']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $result['message'] ?? __('messages.scheduled_delivery_disabled_for_store'),
                ['is_enabled' => false, 'dates' => []],
                422
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('messages.dates_retrieved_successfully') ?? 'Delivery schedule dates retrieved successfully.',
            $result
        );
    }

    /**
     * Retrieve available dates for the authenticated user's current cart.
     * Enforces single-store cart validation before returning dates.
     */
    public function getDatesForCart(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                [],
                401
            );
        }

        $cart = $this->cartService->getUserCart($user);
        if (! $cart) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.cart_is_empty') ?? 'Cart is empty.',
                [],
                400
            );
        }

        $validation = $this->deliveryScheduleService->validateCartForScheduledDelivery($cart);
        if (! $validation['is_valid']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $validation['message'],
                [],
                422
            );
        }

        $store = Store::find($validation['store_id']);
        if (! $store) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.store_not_found') ?? 'Store not found.',
                [],
                404
            );
        }

        $result = $this->deliveryScheduleService->getAvailableDatesForStore($store);

        if (! $result['is_enabled']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $result['message'] ?? __('messages.scheduled_delivery_disabled_for_store'),
                ['is_enabled' => false, 'dates' => []],
                400
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('messages.dates_retrieved_successfully') ?? 'Delivery schedule dates retrieved successfully.',
            $result
        );
    }

    /**
     * Retrieve available time slots for the authenticated user's current cart.
     * Enforces single-store cart validation before discovering slots.
     */
    public function getForCart(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                [],
                401
            );
        }

        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        if ($validator->fails()) {
            return ApiResponseType::sendJsonResponse(
                false,
                $validator->errors()->first(),
                [],
                422
            );
        }

        $cart = $this->cartService->getUserCart($user);
        if (! $cart) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.cart_is_empty') ?? 'Cart is empty.',
                [],
                400
            );
        }

        // Validate that cart contains items from exactly 1 store
        $validation = $this->deliveryScheduleService->validateCartForScheduledDelivery($cart);
        if (! $validation['is_valid']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $validation['message'],
                [],
                422
            );
        }

        $store = Store::find($validation['store_id']);
        if (! $store) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.store_not_found') ?? 'Store not found.',
                [],
                404
            );
        }

        $result = $this->deliveryScheduleService->getAvailableSlotsForStore($store, $request->input('date'));

        if (! $result['is_enabled']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $result['message'] ?? __('messages.scheduled_delivery_disabled_for_store'),
                ['is_enabled' => false, 'slots' => []],
                400
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            $result['message'] ?? __('messages.slots_retrieved_successfully') ?? 'Delivery time slots retrieved successfully.',
            $result
        );
    }

    private function filterSlotsByCartLeadTime(array $slots, string $date, Store $store, $cart): array
    {
        $tz = $store->timezone ?? config('app.timezone', 'UTC');
        $targetDate = Carbon::parse($date, $tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();

        if (! $targetDate->isSameDay($today)) {
            return $slots;
        }

        $cutoff = Carbon::now($tz)->addMinutes(
            $this->deliveryScheduleService->getCartSlotLeadTimeMinutes($cart, $store)
        );

        return array_values(array_filter($slots, function ($slot) use ($date, $tz, $cutoff) {
            if (empty($slot['start_time'])) {
                return false;
            }

            return Carbon::parse($date.' '.$slot['start_time'], $tz)->gt($cutoff);
        }));
    }
}
