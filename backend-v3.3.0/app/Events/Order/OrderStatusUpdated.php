<?php

namespace App\Events\Order;

use App\Models\OrderItem;
use App\Models\SellerOrderItem;
use Carbon\CarbonInterface;
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
     * Acceptance deadline is only set for automatic timeout rejections so
     * notifications can explain why the system rejected the order item.
     */
    public ?CarbonInterface $acceptanceDeadline;

    /**
     * Who triggered the transition. Drives role-aware notification copy
     * (e.g. "cancelled by the seller" vs "cancelled by the customer").
     * One of: 'customer', 'seller', 'rider', 'admin', 'system'. Default 'system'
     * keeps existing callers safe.
     */
    public string $source;

    public function __construct(
        OrderItem $orderItem,
        string $oldStatus,
        string $newStatus,
        string $source = 'system',
        ?CarbonInterface $acceptanceDeadline = null
    ) {
        $this->orderItem = $orderItem;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->source = $source;
        $this->acceptanceDeadline = $acceptanceDeadline;
    }
}
