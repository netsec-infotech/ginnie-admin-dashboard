<?php

namespace App\Services;

use App\Models\Cart;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class CartActivityService
{
    public function record(Cart|int $cart): void
    {
        $cartId = $cart instanceof Cart ? $cart->getKey() : $cart;
        $hasActiveItems = DB::table('cart_items')
            ->where('cart_id', $cartId)
            ->where('save_for_later', '0')
            ->exists();

        $this->updateCycle($cartId, $hasActiveItems ? now() : null);
    }

    public function invalidate(Cart|int $cart): void
    {
        $cartId = $cart instanceof Cart ? $cart->getKey() : $cart;

        $this->updateCycle($cartId, null);
    }

    private function updateCycle(int $cartId, ?DateTimeInterface $lastActivityAt): void
    {
        Cart::query()
            ->whereKey($cartId)
            ->update([
                'last_activity_at' => $lastActivityAt,
                'activity_version' => DB::raw('activity_version + 1'),
                'abandoned_cart_notified_at' => null,
            ]);
    }
}
