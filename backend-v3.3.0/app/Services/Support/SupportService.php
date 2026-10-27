<?php

namespace App\Services\Support;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Events\Support\SupportActivityCreated;
use App\Events\Support\SupportTypingChanged;
use App\Exceptions\SupportConflictException;
use App\Models\Order;
use App\Models\SupportThread;
use App\Models\SupportThreadRead;
use App\Models\SupportTicket;
use App\Models\SupportTicketCall;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRating;
use App\Models\SupportTicketType;
use App\Models\User;
use App\Notifications\SupportAdminMessageReceived;
use App\Notifications\SupportMessageReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SupportService
{
    public function __construct(private readonly SupportAvailabilityService $availability) {}

    public function threadFor(User $customer): SupportThread
    {
        return SupportThread::firstOrCreate(
            ['user_id' => $customer->id],
            ['uuid' => (string) Str::uuid()],
        );
    }

    public function startSession(User $customer, array $data): SupportTicket
    {
        [$ticket, $message, $autoReply] = DB::transaction(function () use ($customer, $data) {
            User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $thread = SupportThread::query()->where('user_id', $customer->id)->lockForUpdate()->first();
            if (! $thread) {
                $this->threadFor($customer);
                $thread = SupportThread::query()->where('user_id', $customer->id)->lockForUpdate()->firstOrFail();
            }

            if ($thread->active_ticket_id) {
                throw new SupportConflictException('An active support session already exists.');
            }

            $topic = SupportTicketType::query()->where('is_active', true)->findOrFail($data['topic_id']);
            $order = null;
            if (! empty($data['order_id'])) {
                $order = Order::query()->where('user_id', $customer->id)->findOrFail($data['order_id']);
            }

            $previous = $thread->tickets()->latest('id')->first();
            $ticket = SupportTicket::create([
                'thread_id' => $thread->id,
                'parent_ticket_id' => $previous?->id,
                'ticket_type_id' => $topic->id,
                'user_id' => $customer->id,
                'order_id' => $order?->id,
                'assigned_to' => null,
                'subject' => $data['subject'] ?? $topic->title,
                'slug' => $this->uniqueTicketSlug(),
                'email' => $customer->email ?? '',
                'description' => $data['message'] ?? $topic->title,
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            $thread->update(['active_ticket_id' => $ticket->id, 'last_message_at' => now()]);
            $message = $this->createMessage($ticket, $customer, 'user', [
                'message' => $data['message'] ?? $topic->title,
                'client_message_id' => $data['client_message_id'] ?? (string) Str::uuid(),
                'type' => 'text',
            ], $data['attachments'] ?? []);

            return [$ticket, $message, $this->createAwayReply($ticket, $customer)];
        }, 3);

        $this->publishSafely($ticket, 'session.created', $message->id);
        $this->notifySupportTeamSafely($ticket, $message);
        if ($autoReply) {
            $this->publishSafely($ticket, 'message.created', $autoReply->id);
        }

        return $ticket->fresh();
    }

    public function addMessage(
        SupportTicket $ticket,
        User $sender,
        string $senderRole,
        array $data,
        array $attachments = [],
    ): SupportTicketMessage {
        [$message, $created, $autoReply] = DB::transaction(function () use ($ticket, $sender, $senderRole, $data, $attachments) {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $locked->isActive()) {
                throw new SupportConflictException('This support session is closed.');
            }

            if ($senderRole === 'admin' && $locked->assigned_to !== $sender->id) {
                throw new SupportConflictException('Assign this conversation to yourself before replying.');
            }

            if (! empty($data['client_message_id'])) {
                $existing = SupportTicketMessage::query()
                    ->where('ticket_id', $locked->id)
                    ->where('client_message_id', $data['client_message_id'])
                    ->first();
                if ($existing) {
                    return [$existing, false, null];
                }
            }

            $message = $this->createMessage($locked, $sender, $senderRole, $data, $attachments);
            $locked->update([
                'status' => $senderRole === 'admin' && $locked->status === 'open' ? 'in_progress' : $locked->status,
                'last_message_at' => now(),
            ]);
            $locked->thread()->update(['last_message_at' => now()]);

            return [$message, true, $senderRole === 'user' ? $this->createAwayReply($locked, $sender) : null];
        }, 3);

        $this->publishSafely($ticket, 'message.created', $message->id);
        if ($autoReply) {
            $this->publishSafely($ticket, 'message.created', $autoReply->id);
        }

        if ($created) {
            if ($senderRole === 'user') {
                $this->notifySupportTeamSafely($ticket, $message);
            } elseif ($senderRole === 'admin') {
                try {
                    $ticket->user()->first()?->notify(new SupportMessageReceived($message));
                } catch (\Throwable $exception) {
                    Log::warning('Support message notification failed.', [
                        'ticket_id' => $ticket->id,
                        'message_id' => $message->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $message->fresh(['media', 'user']);
    }

    public function resolve(SupportTicket $ticket, User $actor, string $role, ?string $summary, string $finalStatus = 'resolved'): SupportTicket
    {
        $resolved = DB::transaction(function () use ($ticket, $actor, $role, $summary, $finalStatus) {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $locked->isActive()) {
                return $locked;
            }

            $locked->update([
                'status' => $finalStatus === 'closed' ? 'closed' : 'resolved',
                'resolution_summary' => $summary,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'closed_at' => now(),
            ]);
            $locked->thread()->where('active_ticket_id', $locked->id)->update(['active_ticket_id' => null]);
            $this->createMessage($locked, $actor, 'system', [
                'message' => 'This conversation has been closed',
                'type' => 'closure',
                'metadata' => [
                    'resolved_by_role' => $role,
                    'summary' => $summary,
                    ...$this->actorMetadata($locked, $actor),
                ],
            ]);

            return $locked;
        }, 3);

        $this->publishSafely($resolved, $finalStatus === 'closed' ? 'session.closed' : 'session.resolved');

        return $resolved->fresh();
    }

    public function markRead(SupportThread $thread, User $reader, ?int $messageId): SupportThreadRead
    {
        return SupportThreadRead::updateOrCreate(
            ['thread_id' => $thread->id, 'user_id' => $reader->id],
            ['last_read_message_id' => $messageId, 'read_at' => now()],
        );
    }

    public function requestCallback(SupportTicket $ticket, User $requester, array $data): SupportTicketCall
    {
        if (! $ticket->isActive()) {
            throw new SupportConflictException('This support session is closed.');
        }

        $call = $ticket->calls()->create([
            'requested_by' => $requester->id,
            'phone' => $data['phone'] ?? $requester->mobile,
            'status' => $data['status'] ?? 'requested',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'private_note' => $data['private_note'] ?? null,
            'handled_by' => $data['handled_by'] ?? null,
            'attempted_at' => isset($data['status']) && $data['status'] !== 'requested' ? now() : null,
        ]);
        $this->addSystemEvent($ticket, $requester, 'callback', 'Call requested', [
            'call_id' => $call->id,
            'status' => $call->status,
        ]);
        $this->publishSafely($ticket, 'callback.updated');

        return $call;
    }

    public function logCall(SupportTicket $ticket, User $agent, array $data): SupportTicketCall
    {
        if (! $ticket->isActive()) {
            throw new SupportConflictException('This support session is closed.');
        }

        $call = $ticket->calls()->where('status', 'requested')->latest('id')->first()
            ?? new SupportTicketCall(['ticket_id' => $ticket->id, 'requested_by' => $agent->id]);
        $call->fill([
            'handled_by' => $agent->id,
            'phone' => $data['phone'] ?? $call->phone ?? $ticket->user?->mobile,
            'status' => $data['status'],
            'scheduled_at' => $data['scheduled_at'] ?? $call->scheduled_at,
            'attempted_at' => $data['status'] === 'requested' ? null : now(),
            'outcome' => $data['outcome'] ?? null,
            'private_note' => $data['private_note'] ?? null,
        ]);
        $call->save();
        $this->addSystemEvent($ticket, $agent, 'callback', 'Call record updated', [
            'call_id' => $call->id,
            'status' => $call->status,
        ]);
        $this->publishSafely($ticket, 'callback.updated');

        return $call;
    }

    public function rate(SupportTicket $ticket, User $customer, int $score, ?string $feedback): SupportTicketRating
    {
        if ($ticket->isActive() || $ticket->user_id !== $customer->id) {
            throw new SupportConflictException('Only a closed support session can be rated.');
        }

        $agentId = collect([$ticket->assigned_to, $ticket->resolved_by])
            ->filter()
            ->first(fn ($userId) => User::query()->whereKey($userId)->where('access_panel', 'admin')->exists());
        $rating = SupportTicketRating::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'user_id' => $customer->id,
                'agent_id' => $agentId,
                'score' => $score,
                'feedback' => $feedback,
            ],
        );
        $this->publishSafely($ticket, 'rating.updated');

        return $rating;
    }

    public function assign(SupportTicket $ticket, ?User $agent, User $actor): SupportTicket
    {
        if (! $ticket->isActive()) {
            throw new SupportConflictException('This support session is closed.');
        }

        $ticket->update(['assigned_to' => $agent?->id, 'status' => $agent ? 'in_progress' : 'open']);
        $this->addSystemEvent($ticket, $actor, 'assignment', $agent ? 'Conversation assigned' : 'Conversation unassigned', [
            'agent_id' => $agent?->id,
            'agent_name' => $agent?->name,
        ]);
        $this->publishSafely($ticket, 'assignment.updated');

        return $ticket->fresh();
    }

    public function updateStatus(SupportTicket $ticket, User $actor, string $status): SupportTicket
    {
        if (! $ticket->isActive() || ! in_array($status, ['open', 'in_progress', 'waiting_customer'], true)) {
            throw new SupportConflictException('The session status cannot be changed.');
        }

        $ticket->update(['status' => $status]);
        $this->addSystemEvent($ticket, $actor, 'status', 'Conversation status changed', ['status' => $status]);
        $this->publishSafely($ticket, 'status.updated');

        return $ticket->fresh();
    }

    public function broadcastTyping(SupportThread $thread, User $actor, string $role, bool $typing): void
    {
        try {
            event(new SupportTypingChanged($thread->id, $actor->id, $role, $typing));
        } catch (\Throwable $exception) {
            Log::warning('Support typing broadcast failed.', [
                'thread_id' => $thread->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function deleteMessage(SupportTicketMessage $message, User $actor): void
    {
        $ticket = $message->ticket()->firstOrFail();
        DB::transaction(function () use ($message, $actor, $ticket) {
            $locked = SupportTicketMessage::query()->lockForUpdate()->findOrFail($message->id);
            $locked->update(['deleted_by' => $actor->id]);
            $locked->delete();
            $latestAt = $ticket->messages()->max('created_at');
            $ticket->update(['last_message_at' => $latestAt]);
            $ticket->thread()->update(['last_message_at' => $ticket->thread->tickets()->max('last_message_at')]);
        });
        $message->clearMediaCollection(SpatieMediaCollectionName::SUPPORT_ATTACHMENTS());
        $this->publishSafely($ticket, 'message.deleted');
    }

    public function clearThread(SupportThread $thread, User $actor): void
    {
        $messages = SupportTicketMessage::query()
            ->with('media')
            ->whereIn('ticket_id', $thread->tickets()->pluck('id'))
            ->get();

        DB::transaction(function () use ($thread, $actor, $messages) {
            SupportTicketMessage::query()
                ->whereKey($messages->modelKeys())
                ->update(['deleted_by' => $actor->id, 'deleted_at' => now()]);
            $thread->tickets()->update(['last_message_at' => null]);
            $thread->update(['last_message_at' => now()]);
        });
        $messages->each(
            fn (SupportTicketMessage $message) => $message->clearMediaCollection(SpatieMediaCollectionName::SUPPORT_ATTACHMENTS())
        );
        $ticket = $thread->activeTicket ?? $thread->tickets()->latest('id')->first();
        if ($ticket) {
            $this->publishSafely($ticket, 'thread.cleared');
        }
    }

    private function createAwayReply(SupportTicket $ticket, User $customer): ?SupportTicketMessage
    {
        $text = $this->availability->awayMessage(now());
        if ($text === null || $ticket->messages()->withTrashed()
            ->where('type', 'auto_reply')
            ->where('created_at', '>', now()->subMinutes(30))
            ->exists()) {
            return null;
        }

        return $this->createMessage($ticket, $customer, 'system', [
            'type' => 'auto_reply',
            'message' => $text,
            'metadata' => ['automated' => true, 'source' => 'support_availability'],
        ]);
    }

    private function createMessage(
        SupportTicket $ticket,
        User $sender,
        string $senderRole,
        array $data,
        array $attachments = [],
    ): SupportTicketMessage {
        $message = $ticket->messages()->create([
            'send_by' => $senderRole,
            'user_id' => $sender->id,
            'client_message_id' => $data['client_message_id'] ?? null,
            'type' => $data['type'] ?? 'text',
            'message' => $data['message'] ?? '',
            'metadata' => $data['metadata'] ?? null,
        ]);

        foreach ($attachments as $attachment) {
            if ($attachment instanceof UploadedFile) {
                $message->addMedia($attachment)->toMediaCollection(SpatieMediaCollectionName::SUPPORT_ATTACHMENTS());
            }
        }

        return $message;
    }

    private function addSystemEvent(SupportTicket $ticket, User $actor, string $type, string $text, array $metadata): void
    {
        $this->createMessage($ticket, $actor, 'system', [
            'type' => $type,
            'message' => $text,
            'metadata' => [...$metadata, ...$this->actorMetadata($ticket, $actor)],
        ]);
        $ticket->update(['last_message_at' => now()]);
        $ticket->thread()->update(['last_message_at' => now()]);
    }

    private function actorMetadata(SupportTicket $ticket, User $actor): array
    {
        return [
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $ticket->user_id === $actor->id ? 'customer' : 'admin',
        ];
    }

    private function uniqueTicketSlug(): string
    {
        do {
            $slug = 'SUP-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        } while (SupportTicket::where('slug', $slug)->exists());

        return $slug;
    }

    private function publishSafely(SupportTicket $ticket, string $activity, ?int $messageId = null): void
    {
        try {
            event(new SupportActivityCreated($ticket->thread_id, $ticket->id, $activity, $messageId));
        } catch (\Throwable $exception) {
            Log::warning('Support broadcast failed; REST data remains committed.', [
                'ticket_id' => $ticket->id,
                'activity' => $activity,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifySupportTeamSafely(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        try {
            $currentTicket = SupportTicket::query()->findOrFail($ticket->id);
            $recipients = User::query()
                ->with(['roles', 'permissions'])
                ->where('access_panel', GuardNameEnum::ADMIN())
                ->where('status', 'active')
                ->when($currentTicket->assigned_to, fn ($query, $agentId) => $query->whereKey($agentId))
                ->get()
                ->filter(fn (User $agent): bool => $agent->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())
                    || $agent->hasPermissionTo(AdminPermissionEnum::SUPPORT_VIEW()))
                ->values();

            Notification::send($recipients, new SupportAdminMessageReceived($message, $currentTicket->assigned_to));
        } catch (\Throwable $exception) {
            Log::warning('Support team notification failed.', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
