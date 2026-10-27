<?php

namespace App\Services;

use App\Enums\Order\OrderItemStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\PosRefund;
use App\Models\PosRefundLine;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Models\User;
use App\Support\Currency\CurrencyWriteGuard;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * POS refunds -- cashier reverses (some/all of) a recent sale.
 *
 * What it does
 *  - Writes a pos_refunds + pos_refund_lines audit row.
 *  - Refunds the amount the customer actually paid: the gross line subtotal is
 *    scaled by the order-level discount ratio (cashier discount + promo), so a
 *    sale that was discounted refunds the discounted price, not the original.
 *  - Restores variant + addon stock atomically (incrementing the same
 *    counters PosOrderService decremented at sale time).
 *  - Flips order_items.status to REFUNDED when the line is fully refunded.
 *
 * What it does NOT do
 *  - Does not call payment-gateway refund APIs.
 *  - Does not mutate the original order's totals.
 *  - Does not touch seller_statements.
 */
class PosRefundService
{
    public function __construct(
        protected DatabaseManager $db,
    ) {}

    /**
     * Refundable preview for the UI.
     *
     * @return array{
     *   refundable: array<int, array{order_item_id:int,title:string,variant_title:?string,quantity:int,already_refunded:int,max_refundable:int,per_unit_amount:float,line_total:float,addon_summary:?string}>,
     *   already_refunded_total: float,
     *   currency_code: string,
     * }
     */
    public function preview(Order $order, int $sellerId): array
    {
        $items = OrderItem::where('order_id', $order->id)
            ->whereHas('store', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['addons.addonItem:id,title'])
            ->get();

        $discountRatio = $this->discountRatio($order);

        $alreadyRefundedByItem = PosRefundLine::whereIn('order_item_id', $items->pluck('id'))
            ->selectRaw('order_item_id, SUM(quantity) as qty, SUM(amount) as amt')
            ->groupBy('order_item_id')
            ->get()
            ->keyBy('order_item_id');

        $rows = [];
        $alreadyTotal = 0.0;
        foreach ($items as $item) {
            $alreadyQty = (int) ($alreadyRefundedByItem[$item->id]->qty ?? 0);
            $alreadyAmt = (float) ($alreadyRefundedByItem[$item->id]->amt ?? 0);
            $alreadyTotal += $alreadyAmt;

            $maxRefundable = max(0, (int) $item->quantity - $alreadyQty);
            if ($maxRefundable <= 0) {
                continue;
            }

            $perUnit = (int) $item->quantity > 0
                ? round(((float) $item->subtotal * $discountRatio) / (int) $item->quantity, 2)
                : 0.0;

            $addonSummary = $item->addons->isNotEmpty()
                ? $item->addons->pluck('addonItem.title')->filter()->implode(', ') ?: null
                : null;

            $rows[] = [
                'order_item_id' => $item->id,
                'title' => $item->title,
                'variant_title' => $item->variant_title,
                'quantity' => (int) $item->quantity,
                'already_refunded' => $alreadyQty,
                'max_refundable' => $maxRefundable,
                'per_unit_amount' => $perUnit,
                'line_total' => round($perUnit * $maxRefundable, 2),
                'addon_summary' => $addonSummary,
            ];
        }

        return [
            'refundable' => $rows,
            'already_refunded_total' => round($alreadyTotal, 2),
            'currency_code' => app(CurrencyWriteGuard::class)->orderCurrency($order),
        ];
    }

    /**
     * Persist a refund event for the given order.
     *
     * @param  array<int,int>  $lineQtys  order_item_id => qty_to_refund
     * @param  string  $method  refund method code (see PosRefund::METHODS)
     *
     * @throws \InvalidArgumentException on empty input or invalid qty / method
     */
    public function create(
        Order $order,
        array $lineQtys,
        ?string $reason,
        User $by,
        int $sellerId,
        string $method = 'cash',
        ?array $methodMeta = null,
    ): PosRefund {
        $lineQtys = array_filter($lineQtys, fn ($q) => (int) $q > 0);
        if (empty($lineQtys)) {
            throw new \InvalidArgumentException(__('labels.pos_no_items_selected_for_refund'));
        }
        if (! in_array($method, PosRefund::METHODS, true)) {
            throw new \InvalidArgumentException(__('labels.pos_unknown_refund_method'));
        }

        return $this->db->transaction(function () use ($order, $lineQtys, $reason, $by, $sellerId, $method, $methodMeta) {
            $items = OrderItem::whereIn('id', array_keys($lineQtys))
                ->where('order_id', $order->id)
                ->whereHas('store', fn ($q) => $q->where('seller_id', $sellerId))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($lineQtys)) {
                throw new \InvalidArgumentException(__('labels.pos_order_items_not_owned'));
            }

            $discountRatio = $this->discountRatio($order);

            $alreadyByItem = PosRefundLine::whereIn('order_item_id', $items->pluck('id'))
                ->selectRaw('order_item_id, SUM(quantity) as qty')
                ->groupBy('order_item_id')
                ->pluck('qty', 'order_item_id');

            $storeId = (int) $items->first()->store_id;
            $totalAmt = 0.0;
            $linePlan = [];

            foreach ($lineQtys as $itemId => $qty) {
                $qty = (int) $qty;
                $item = $items[$itemId];
                $already = (int) ($alreadyByItem[$itemId] ?? 0);
                $remaining = max(0, (int) $item->quantity - $already);

                if ($qty > $remaining) {
                    throw new \InvalidArgumentException(__('labels.pos_refund_qty_exceeds_remaining'));
                }

                $perUnit = (int) $item->quantity > 0
                    ? round(((float) $item->subtotal * $discountRatio) / (int) $item->quantity, 2)
                    : 0.0;
                $amount = round($perUnit * $qty, 2);
                $totalAmt += $amount;

                $linePlan[] = [
                    'item' => $item,
                    'qty' => $qty,
                    'amount' => $amount,
                    'will_be_full' => ($already + $qty) === (int) $item->quantity,
                ];
            }

            $refund = PosRefund::create([
                'order_id' => $order->id,
                'store_id' => $storeId,
                'refunded_by_user_id' => $by->id,
                'total_amount' => round($totalAmt, 2),
                // Phase 2 snapshot: POS refunds now carry explicit currency
                // for new rows while old rows remain untouched.
                'currency_code' => app(CurrencyWriteGuard::class)->orderCurrency($order),
                'refund_method' => $method,
                'refund_method_meta' => $methodMeta,
                'reason' => $reason ? trim($reason) : null,
            ]);

            foreach ($linePlan as $plan) {
                /** @var OrderItem $item */
                $item = $plan['item'];

                PosRefundLine::create([
                    'pos_refund_id' => $refund->id,
                    'order_item_id' => $item->id,
                    'quantity' => $plan['qty'],
                    'amount' => $plan['amount'],
                ]);

                $this->restoreVariantStock($item, $plan['qty']);
                $this->restoreAddonStock($item, $plan['qty']);

                if ($plan['will_be_full']) {
                    $item->update(['status' => OrderItemStatusEnum::REFUNDED()]);
                }
            }

            return $refund->fresh('lines');
        });
    }

    /**
     * Ratio of what the customer actually paid for goods to the gross line
     * subtotal, so refunds honor any cashier discount / promo applied at sale
     * time instead of reversing the original (pre-discount) price.
     *
     * order.subtotal is the gross tax-inclusive line subtotal; order.promo_discount
     * holds the combined cashier discount + instant promo discount. Wallet is a
     * tender, not a discount, so it is intentionally excluded here.
     */
    protected function discountRatio(Order $order): float
    {
        $gross = (float) $order->subtotal;
        if ($gross <= 0) {
            return 1.0;
        }

        $charged = max(0.0, $gross - (float) $order->promo_discount);

        return min(1.0, $charged / $gross);
    }

    /**
     * Increment the StoreProductVariant stock by qty for the variant the
     * order item was sold from.
     */
    protected function restoreVariantStock(OrderItem $item, int $qty): void
    {
        $sv = StoreProductVariant::where('store_id', $item->store_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->first();
        if (! $sv) {
            Log::warning('POS refund: store variant not found for stock restore', [
                'order_item_id' => $item->id,
                'store_id' => $item->store_id,
                'product_variant_id' => $item->product_variant_id,
            ]);

            return;
        }
        $sv->increment('stock', $qty);
    }

    /**
     * Increment store_addon_items.stock by qty for each addon on this line.
     */
    protected function restoreAddonStock(OrderItem $item, int $qty): void
    {
        $addons = OrderItemAddon::where('order_item_id', $item->id)->get();
        foreach ($addons as $addon) {
            $sa = StoreAddonItem::where('store_id', $item->store_id)
                ->where('addon_item_id', $addon->addon_item_id)
                ->first();
            if (! $sa) {
                Log::warning('POS refund: store addon item not found for stock restore', [
                    'order_item_id' => $item->id,
                    'addon_item_id' => $addon->addon_item_id,
                ]);

                continue;
            }
            $sa->increment('stock', $qty);
        }
    }
}
