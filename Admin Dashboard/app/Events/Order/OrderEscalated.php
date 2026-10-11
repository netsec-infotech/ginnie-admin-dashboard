<?php

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 3C — fired by OrderEscalationService when it flags an order with one
 * or more new SLA-violation reasons. Listened to by NotifyAdminOnEscalation.
 */
class OrderEscalated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    /**
     * Reason codes newly added on this flag pass (e.g. ['seller_unresponsive']).
     *
     * @var array<int, string>
     */
    public array $newReasons;

    public function __construct(Order $order, array $newReasons)
    {
        $this->order = $order;
        $this->newReasons = $newReasons;
    }
}
