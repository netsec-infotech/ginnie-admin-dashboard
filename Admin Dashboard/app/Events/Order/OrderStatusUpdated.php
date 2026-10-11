<?php

namespace App\Events\Order;

use App\Models\OrderItem;
use App\Models\SellerOrderItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OrderItem $orderItem;
    public string $oldStatus;
    public string $newStatus;
    /**
     * Who triggered the transition. Drives role-aware notification copy
     * (e.g. "cancelled by the seller" vs "cancelled by the customer").
     * One of: 'customer', 'seller', 'rider', 'admin', 'system'. Default 'system'
     * keeps existing callers safe.
     */
    public string $source;

    public function __construct(OrderItem $orderItem, string $oldStatus, string $newStatus, string $source = 'system')
    {
        $this->orderItem = $orderItem;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->source = $source;
    }
}
