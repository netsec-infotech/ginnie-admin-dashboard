<?php

namespace App\Http\Controllers;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Services\CodPaymentService;
use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public COD Doorstep Online Payment Controller.
 *
 * Customer (no auth, opens via QR or link from delivery boy):
 * payment page, init gateway, verify, return from gateway, public status polling.
 */
class CodPaymentController extends Controller
{
    public function __construct(
        protected CodPaymentService $codPaymentService,
    ) {
    }

    /**
     * Show public COD online payment page.
     *
     * @param string $token
     * @return Factory|View
     */
    public function showPaymentPage(string $token): Factory|View
    {
        try {
            $order = Order::with(['items', 'deliveryZone'])->where('uuid', $token)->first();
            if (!$order) {
                throw new NotFoundHttpException();
            }

            return view('cod.payment-page', [
                'order'     => $order,
                'gateways'  => $this->codPaymentService->availableGateways($order),
                'verifyUrl' => route('cod.public.payment.verify', ['token' => $token]),
                'statusUrl' => route('cod.public.payment.status', ['token' => $token]),
                'initUrlT'  => route('cod.public.payment.init', ['token' => $token, 'gateway' => '__GW__']),
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            abort(500);
        }
    }

    /**
     * Public status polling endpoint for customer page or rider app.
     *
     * @param string $token
     * @return JsonResponse
     */
    public function publicStatus(string $token): JsonResponse
    {
        try {
            $order = Order::where('uuid', $token)->first();
            if (!$order) {
                throw new NotFoundHttpException();
            }

            return ApiResponseType::sendJsonResponse(true, __('labels.success'), [
                'status'             => $order->payment_status,
                'is_cod_paid_online' => (bool) $order->is_cod_paid_online,
                'cod_online_gateway' => $order->cod_online_gateway,
                'amount'             => (float) $order->total_payable,
                'currency_code'      => $order->currency_code,
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), null, 500);
        }
    }

    /**
     * Customer clicks a gateway button on the COD payment page.
     *
     * @param Request $request
     * @param string $token
     * @param string $gateway
     * @return JsonResponse
     */
    public function initGateway(Request $request, string $token, string $gateway): JsonResponse
    {
        try {
            $order = Order::where('uuid', $token)->first();
            if (!$order) {
                throw new NotFoundHttpException();
            }

            $data = $this->codPaymentService->initGateway($order, $gateway);

            return ApiResponseType::sendJsonResponse(true, __('labels.success'), $data);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
        }
    }

    /**
     * Generic verify endpoint for inline-checkout (Razorpay).
     *
     * @param Request $request
     * @param string $token
     * @return JsonResponse
     */
    public function verify(Request $request, string $token): JsonResponse
    {
        try {
            $order = Order::where('uuid', $token)->first();
            if (!$order) {
                throw new NotFoundHttpException();
            }

            $gateway = $order->cod_online_gateway ?: $request->input('gateway');
            if (!$gateway) {
                return ApiResponseType::sendJsonResponse(false, __('labels.no_gateway_selected'), null, 422);
            }

            $orderId = $this->codPaymentService->verify($order, $gateway, $request->all());

            return ApiResponseType::sendJsonResponse(true, __('labels.payment_confirmed'), [
                'order_id' => $orderId,
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
        }
    }

    /**
     * Redirect-back endpoint for hosted-page gateways (Stripe, Paystack, Flutterwave).
     *
     * @param Request $request
     * @param string $token
     * @param string $gateway
     * @return RedirectResponse
     */
    public function returnFromGateway(
        Request $request,
        string $token,
        string $gateway,
    ): RedirectResponse {
        try {
            $order = Order::where('uuid', $token)->first();
            if (!$order) {
                throw new NotFoundHttpException();
            }

            $payload = match ($gateway) {
                'stripe'      => ['stripe_session_id' => $request->query('stripe_session_id', '')],
                'paystack'    => ['reference' => $request->query('reference', $request->query('trxref', ''))],
                'flutterwave' => [
                    'transaction_id' => $request->query('transaction_id', ''),
                    'tx_ref'         => $request->query('tx_ref', ''),
                    'status'         => $request->query('status', ''),
                ],
                'xendit'      => [],
                default => [],
            };

            if ($gateway === 'flutterwave' && ($payload['status'] ?? '') === 'cancelled') {
                return redirect()->route('cod.public.payment.show', ['token' => $token]);
            }

            try {
                $this->codPaymentService->verify($order, $gateway, $payload);
            } catch (\Throwable $e) {
                Log::error("Gateway return verification failed for COD order", [
                    'order_uuid' => $token,
                    'gateway'    => $gateway,
                    'error'      => $e->getMessage(),
                ]);
            }

            return redirect()->route('cod.public.payment.show', ['token' => $token]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('cod.public.payment.show', ['token' => $token]);
        }
    }
}

