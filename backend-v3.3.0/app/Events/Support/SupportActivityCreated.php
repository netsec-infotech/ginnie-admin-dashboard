<?php

namespace App\Events\Support;

use App\Models\SupportThread;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Support\SupportPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportActivityCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $threadId,
        public int $ticketId,
        public string $activity,
        public ?int $messageId = null,
    ) {}

    public function broadcastOn(): array
    {
        $thread = SupportThread::findOrFail($this->threadId);

        return [
            new PrivateChannel('support.thread.'.$thread->uuid),
            new PrivateChannel('support.user.'.$thread->user_id),
            new PrivateChannel('support.admin.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.activity';
    }

    public function broadcastWith(): array
    {
        $thread = SupportThread::with('user:id,name')->findOrFail($this->threadId);
        $ticket = SupportTicket::query()->findOrFail($this->ticketId);
        $message = $this->messageId
            ? SupportTicketMessage::with('user:id,name')->find($this->messageId)
            : null;

        return [
            'thread_id' => $this->threadId,
            'thread_uuid' => $thread->uuid,
            'ticket_id' => $this->ticketId,
            'assigned_to' => $ticket->assigned_to,
            'message_id' => $this->messageId,
            'activity' => $this->activity,
            'customer' => [
                'id' => $thread->user_id,
                'name' => $thread->user?->name,
            ],
            'message' => $message ? [
                'sender_role' => $message->send_by,
                'sender_name' => $message->type === 'auto_reply' ? __('Customer Support') : $message->user?->name,
                'preview' => str($message->message)->squish()->limit(160)->toString(),
            ] : null,
            'message_record' => $message ? SupportPresenter::message($message) : null,
            'occurred_at' => now()->toISOString(),
        ];
    }
}
