<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CreatePosOrderRequest;
use App\Models\Order;
use App\Models\Store;
use App\Services\SettingService;
use App\Models\User;
use App\Services\PosOrderService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

#[Group('Seller POS')]
class SellerPosOrderApiController extends Controller
{
    public function __construct(private readonly PosOrderService $service)
    {
    }

    /**
     * Create a POS order.
     *
     * @return JsonResponse
     */
    public function store(CreatePosOrderRequest $request): JsonResponse
    {
        try {
            $user   = $request->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $result = $this->service->create($seller, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? null,
                status: $result['success'] ? 201 : 422
            );
        } catch (Throwable $e) {
            Log::error('POS order store error', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Get receipt HTML for a POS order.
     *
     * @param int $orderId Order ID.
     * @return View
     */
    public function receipt(Request $request, int $orderId): View
    {
        $user   = $request->user();
        $seller = $user?->seller();
        if (!$seller) {
            throw new NotFoundHttpException();
        }

        $order = Order::with(['items'])->find($orderId);
        if (!$order) {
            throw new NotFoundHttpException();
        }

        if (!method_exists($order, 'isPosOrder') || !$order->isPosOrder()) {
            throw new NotFoundHttpException();
        }

        $firstItem = $order->items->first();
        $store = $firstItem
            ? Store::where('id', $firstItem->store_id)->where('seller_id', $seller->id)->first()
            : null;
        if (!$store) {
            throw new NotFoundHttpException();
        }

        $customerName   = __('labels.pos_walkin_customer');
        $customerMobile = null;

        if (!empty($order->walkin_customer_name)) {
            $customerName   = $order->walkin_customer_name;
            $customerMobile = $order->walkin_customer_mobile;
        } elseif (!$order->isAttachedToWalkinPlaceholder()) {
            $orderUser = User::find($order->user_id);
            if ($orderUser) {
                $customerName   = $orderUser->name ?? __('labels.pos_customer');
                $customerMobile = $orderUser->mobile;
            }
        }

        $footerNote = $this->resolveFooterNote($store);

        return view('seller.pos.receipt', [
            'order'          => $order,
            'store'          => $store,
            'customerName'   => $customerName,
            'customerMobile' => $customerMobile,
            'footerNote'     => $footerNote,
        ]);
    }

    /**
     * Resolve receipt footer: per-store override > admin default > translated fallback.
     */
    private function resolveFooterNote(Store $store): string
    {
        $perStore = $store->receipt_template['footer_note'] ?? null;
        if (is_string($perStore) && $perStore !== '') {
            return $perStore;
        }

        $values = app(SettingService::class)->getSettingValues('pos_settings');
        $defaultTemplate = $values['default_receipt_template'] ?? null;
        if (is_array($defaultTemplate) && !empty($defaultTemplate['footer_note'])) {
            return (string) $defaultTemplate['footer_note'];
        }

        return __('labels.pos_thank_you_for_purchase');
    }
}
