<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Pos\PosPaymentSessionStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosPaymentSessionRequest;
use App\Models\AddonItem;
use App\Models\PosPaymentSession;
use App\Models\Store;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Services\PosOrderService;
use App\Services\PosPaymentSessionService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POS Online Payment QR controller.
 *
 * Cashier (session-authed seller): create / poll-status / cancel session.
 * Customer (no auth, opens via scanned QR): payment page, init gateway,
 * verify, return from gateway, public status.
 */
class PosPaymentSessionController extends Controller
{
    use ChecksPermissions;

    protected bool $paymentSessionPermission = false;

    public function __construct(
        protected PosPaymentSessionService $sessions,
    ) {
        $user = auth()->user();
        if ($user) {
            $this->paymentSessionPermission = $this->hasPermission(SellerPermissionEnum::POS_PAYMENT_SESSION())
                || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        }
    }

    // ── Cashier API ────────────────────────────────────────────────

    public function create(StorePosPaymentSessionRequest $request): JsonResponse
    {
        try {
            if (!$this->paymentSessionPermission) {
                return $this->unauthorizedResponse();
            }

            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $store = Store::where('id', (int) $request->input('store_id'))
                ->where('seller_id', $seller->id)
                ->first();
            if (!$store) {
                return ApiResponseType::sendJsonResponse(false, 'labels.store_not_found', null, 404);
            }

            $session = $this->sessions->create($seller, $store, $request->validated());

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.pos_payment_session_created',
                data: ['session' => $this->presentSession($session)],
                status: 201
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    public function status(Request $request, string $token): JsonResponse
    {
        try {
            if (!$this->paymentSessionPermission) {
                return $this->unauthorizedResponse();
            }

            $session = $this->ownedSessionOr404($token);
            $session = $this->sessions->expireIfDue($session);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.pos_success',
                data: ['session' => $this->presentSession($session)]
            );
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    public function cancel(Request $request, string $token): JsonResponse
    {
        try {
            if (!$this->paymentSessionPermission) {
                return $this->unauthorizedResponse();
            }

            $session = $this->ownedSessionOr404($token);
            $session = $this->sessions->cancel($session, __('labels.pos_cashier_abandoned_session'));

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_session_cancelled', [
                'session' => $this->presentSession($session),
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    // ── Public payment page (no auth) ──────────────────────────────

    public function showPaymentPage(string $token): Factory|View
    {
        try {
            $session = PosPaymentSession::where('token', $token)->first();
            if (!$session) {
                throw new NotFoundHttpException();
            }

            $session = $this->sessions->expireIfDue($session);
            $store   = Store::find($session->store_id);

            return view('pos.payment-page', [
                'session'   => $session,
                'store'     => $store,
                'lineItems' => $this->buildLineItems($session),
                'breakdown' => $this->breakdownForSession($session),
                'gateways'  => $this->sessions->availableGateways($session),
                'verifyUrl' => route('pos.public.payment.verify', ['token' => $token]),
                'statusUrl' => route('pos.public.payment.status', ['token' => $token]),
                'initUrlT'  => route('pos.public.payment.init', ['token' => $token, 'gateway' => '__GW__']),
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            abort(500);
        }
    }

    /**
     * Pre-computed pricing breakdown for the public payment page.
     */
    private function breakdownForSession(PosPaymentSession $session): array
    {
        $payload = $session->payload ?? [];
        $items   = $payload['items'] ?? [];
        if (empty($items)) {
            return [
                'subtotal'      => 0,
                'subtotalExTax' => 0,
                'tax'           => 0,
                'taxByRate'     => [],
                'savings'       => 0,
                'discount'      => 0,
                'total'         => (float) $session->amount,
            ];
        }

        $svIds = collect($items)->pluck('store_product_variant_id')->unique()->all();
        $svs = StoreProductVariant::with(['productVariant.product.taxClasses.taxRates'])
            ->whereIn('id', $svIds)->get()->keyBy('id');

        $addonIds = collect($items)
            ->flatMap(fn($it) => collect($it['addons'] ?? [])->pluck('addon_item_id'))
            ->unique()
            ->all();
        $addonStore = $addonIds
            ? StoreAddonItem::where('store_id', $session->store_id)
                ->whereIn('addon_item_id', $addonIds)->get()->keyBy('addon_item_id')
            : collect();

        $subtotal = 0.0;
        $tax = 0.0;
        $savings = 0.0;
        $taxByRate = [];

        foreach ($items as $it) {
            $sv = $svs->get($it['store_product_variant_id'] ?? 0);
            if (!$sv) {
                continue;
            }
            $product = $sv->productVariant?->product;
            $taxPct  = (float) ($product?->taxClasses?->flatMap->taxRates?->sum('rate') ?? 0);
            $isInc   = (string) ($product?->is_inclusive_tax ?? '1') === '1';
            $qty     = (int) ($it['quantity'] ?? 1);

            $regularStored   = (float) $sv->price;
            $effectiveStored = (float) ($sv->special_price > 0 && $sv->special_price < $sv->price
                ? $sv->special_price
                : $sv->price);

            if ($isInc) {
                $unitIncl = $effectiveStored;
                $unitExcl = $taxPct > 0 ? $unitIncl / (1 + $taxPct / 100) : $unitIncl;
                $regIncl  = $regularStored;
            } else {
                $unitExcl = $effectiveStored;
                $unitIncl = $taxPct > 0 ? $unitExcl * (1 + $taxPct / 100) : $unitExcl;
                $regIncl  = $taxPct > 0 ? $regularStored * (1 + $taxPct / 100) : $regularStored;
            }
            $unitTax = $unitIncl - $unitExcl;

            $addonInclTotal = 0.0;
            $addonExclTotal = 0.0;
            foreach (($it['addons'] ?? []) as $a) {
                $sa = $addonStore->get($a['addon_item_id'] ?? 0);
                $stored = $sa ? (float) $sa->price : 0.0;
                if ($isInc) {
                    $addonInclTotal += $stored;
                    $addonExclTotal += $taxPct > 0 ? $stored / (1 + $taxPct / 100) : $stored;
                } else {
                    $addonExclTotal += $stored;
                    $addonInclTotal += $taxPct > 0 ? $stored * (1 + $taxPct / 100) : $stored;
                }
            }

            $subtotal += ($unitIncl + $addonInclTotal) * $qty;
            $lineTax   = ($unitTax + ($addonInclTotal - $addonExclTotal)) * $qty;
            $tax      += $lineTax;
            $savings  += max(0.0, $regIncl - $unitIncl) * $qty;

            if ($taxPct > 0 && $lineTax > 0) {
                $rateKey = ($taxPct == (int) $taxPct)
                    ? (string) (int) $taxPct
                    : number_format($taxPct, 2, '.', '');
                $taxByRate[$rateKey] = ($taxByRate[$rateKey] ?? 0) + $lineTax;
            }
        }

        $discountType  = $payload['discount_type'] ?? null;
        $discountValue = (float) ($payload['discount_value'] ?? 0);
        $discount      = 0.0;
        if ($discountType === 'percent' && $discountValue > 0) {
            $discount = round($subtotal * min(100, max(0, $discountValue)) / 100, 2);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $discount = round(min($subtotal, max(0.0, $discountValue)), 2);
        }

        ksort($taxByRate, SORT_NUMERIC);
        $taxByRate = array_map(fn($a) => round((float) $a, 2), $taxByRate);
        $taxRound  = round($tax, 2);
        $subRound  = round($subtotal, 2);

        return [
            'subtotal'      => $subRound,
            'subtotalExTax' => round(max(0.0, $subtotal - $tax), 2),
            'tax'           => $taxRound,
            'taxByRate'     => $taxByRate,
            'savings'       => round($savings, 2),
            'discount'      => $discount,
            'total'         => round(max(0, $subtotal - $discount), 2),
        ];
    }

    public function publicStatus(string $token): JsonResponse
    {
        try {
            $session = PosPaymentSession::where('token', $token)->first();
            if (!$session) {
                throw new NotFoundHttpException();
            }

            $session = $this->sessions->expireIfDue($session);

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', [
                'status' => $session->status,
                'amount' => (float) $session->amount,
            ]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Customer clicks a gateway button on the payment page.
     */
    public function initGateway(Request $request, string $token, string $gateway): JsonResponse
    {
        try {
            $session = PosPaymentSession::where('token', $token)->first();
            if (!$session) {
                throw new NotFoundHttpException();
            }

            $data = $this->sessions->initGateway($session, $gateway);

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_success', $data);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 422);
        }
    }

    /**
     * Generic verify endpoint for the inline-checkout path (currently Razorpay).
     */
    public function verify(Request $request, string $token, PosOrderService $orderService): JsonResponse
    {
        try {
            $session = PosPaymentSession::where('token', $token)->first();
            if (!$session) {
                throw new NotFoundHttpException();
            }

            $gateway = $session->gateway ?: $request->input('gateway');
            if (!$gateway) {
                return ApiResponseType::sendJsonResponse(false, 'labels.pos_no_gateway_selected', null, 422);
            }

            $orderId = $this->sessions->verify($session, $gateway, $request->all(), $orderService);

            return ApiResponseType::sendJsonResponse(true, 'labels.pos_payment_confirmed', [
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
     * Redirect-back endpoint for hosted-page gateways.
     */
    public function returnFromGateway(
        Request $request,
        string $token,
        string $gateway,
        PosOrderService $orderService,
    ): RedirectResponse {
        try {
            $session = PosPaymentSession::where('token', $token)->first();
            if (!$session) {
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
                return redirect()->route('pos.public.payment.show', ['token' => $token]);
            }

            try {
                $this->sessions->verify($session, $gateway, $payload, $orderService);
            } catch (\Throwable $e) {
                if ($session->fresh()->status === PosPaymentSessionStatusEnum::PENDING()) {
                    $session->update([
                        'status'         => PosPaymentSessionStatusEnum::FAILED(),
                        'failure_reason' => $e->getMessage(),
                    ]);
                }
            }

            return redirect()->route('pos.public.payment.show', ['token' => $token]);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('pos.public.payment.show', ['token' => $token]);
        }
    }

    // ── helpers ────────────────────────────────────────────────────

    private function ownedSessionOr404(string $token): PosPaymentSession
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            throw new NotFoundHttpException();
        }

        $session = PosPaymentSession::where('token', $token)
            ->where('seller_id', $seller->id)
            ->first();
        if (!$session) {
            throw new NotFoundHttpException();
        }

        return $session;
    }

    private function buildLineItems(PosPaymentSession $session): array
    {
        $items = $session->payload['items'] ?? [];
        if (empty($items)) {
            return [];
        }

        $svIds = collect($items)->pluck('store_product_variant_id')->unique()->all();
        $svs = StoreProductVariant::with(['productVariant.product'])
            ->whereIn('id', $svIds)
            ->get()
            ->keyBy('id');

        $addonIds = collect($items)
            ->flatMap(fn($it) => collect($it['addons'] ?? [])->pluck('addon_item_id'))
            ->unique()
            ->all();
        $addons = $addonIds
            ? AddonItem::whereIn('id', $addonIds)->get()->keyBy('id')
            : collect();

        $rows = [];
        foreach ($items as $it) {
            $sv = $svs->get($it['store_product_variant_id'] ?? 0);
            if (!$sv) {
                $rows[] = [
                    'title'   => __('labels.item'),
                    'variant' => null,
                    'addons'  => [],
                    'qty'     => (int) ($it['quantity'] ?? 1),
                ];
                continue;
            }
            $product      = $sv->productVariant?->product;
            $variantTitle = $sv->productVariant?->title;
            $addonTitles  = collect($it['addons'] ?? [])
                ->map(fn($a) => $addons->get($a['addon_item_id'] ?? 0)?->title)
                ->filter()
                ->values()
                ->all();

            $rows[] = [
                'title'   => $product?->title ?? __('labels.item'),
                'variant' => $variantTitle && strcasecmp($variantTitle, 'Default') !== 0
                    ? $variantTitle
                    : null,
                'addons'  => $addonTitles,
                'qty'     => (int) ($it['quantity'] ?? 1),
            ];
        }

        return $rows;
    }

    private function presentSession(PosPaymentSession $session): array
    {
        return [
            'token'            => $session->token,
            'status'           => $session->status,
            'amount'           => (float) $session->amount,
            'currency_code'    => $session->currency_code,
            'gateway'          => $session->gateway,
            'gateway_order_id' => $session->gateway_order_id,
            'order_id'         => $session->order_id,
            'expires_at'       => $session->expires_at?->toIso8601String(),
            'failure_reason'   => $session->failure_reason,
            'payment_url'      => route('pos.public.payment.show', ['token' => $session->token]),
        ];
    }
}
