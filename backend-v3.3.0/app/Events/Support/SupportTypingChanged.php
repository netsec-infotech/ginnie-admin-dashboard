<?php

namespace App\Events\Support;

use App\Models\SupportThread;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTypingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $threadId,
        public int $userId,
        public string $role,
        public bool $typing,
    ) {}

    public function broadcastOn(): array
    {
        $thread = SupportThread::findOrFail($this->threadId);

        $channels = [new PrivateChannel('support.thread.'.$thread->uuid)];

        if ($this->role === 'customer') {
            $channels[] = new PrivateChannel('support.admin.inbox');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'support.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'thread_uuid' => SupportThread::findOrFail($this->threadId)->uuid,
            'user_id' => $this->userId,
            'role' => $this->role,
            'typing' => $this->typing,
        ];
    }
}
