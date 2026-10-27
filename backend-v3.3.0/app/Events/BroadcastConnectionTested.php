<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastConnectionTested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $adminId,
        public string $testId,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("broadcast.test.{$this->adminId}.{$this->testId}")];
    }

    public function broadcastAs(): string
    {
        return 'broadcast.connection.test';
    }

    public function broadcastWith(): array
    {
        return [
            'test_id' => $this->testId,
            'driver' => config('broadcasting.default'),
            'sent_at' => now()->toISOString(),
        ];
    }
}
