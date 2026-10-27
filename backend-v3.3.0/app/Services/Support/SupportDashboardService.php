<?php

namespace App\Services\Support;

use App\Enums\GuardNameEnum;
use App\Models\SupportTicket;
use App\Models\SupportTicketCall;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRating;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupportDashboardService
{
    private const ACTIVE_STATUSES = ['open', 'in_progress', 'waiting_customer'];

    public function normalizeDays(mixed $days): int
    {
        $days = (int) $days;

        return in_array($days, [7, 30, 90], true) ? $days : 30;
    }

    public function normalizeLateMinutes(mixed $minutes): int
    {
        $minutes = is_numeric($minutes) ? (int) $minutes : (int) config('support.late_reply_minutes', 15);

        return min(1440, max(1, $minutes));
    }

    public function data(int $days, mixed $lateMinutes = null): array
    {
        $days = $this->normalizeDays($days);
        $from = now()->subDays($days - 1)->startOfDay();
        $ratings = SupportTicketRating::query()->where('created_at', '>=', $from);
        $ratingSummary = (clone $ratings)->selectRaw('COUNT(*) as total, AVG(score) as average')->first();

        $topics = SupportTicket::query()
            ->leftJoin('support_ticket_types', 'support_ticket_types.id', '=', 'support_tickets.ticket_type_id')
            ->whereBetween('support_tickets.created_at', [$from, now()])
            ->selectRaw("COALESCE(support_ticket_types.title, 'Other') as topic, COUNT(*) as total")
            ->groupBy('support_ticket_types.id', 'support_ticket_types.title')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $lateReplyMinutes = $this->normalizeLateMinutes($lateMinutes);
        $waitingCustomers = $this->waitingPage(1);
        $lateReplies = $this->waitingPage(1, $lateReplyMinutes);

        return [
            'days' => $days,
            'from' => $from,
            'to' => now(),
            'lateReplyMinutes' => $lateReplyMinutes,
            'lateReplyOptions' => collect(config('support.late_reply_options', [5, 10, 15, 30, 60, 120]))
                ->push($lateReplyMinutes)
                ->map(fn ($minutes) => $this->normalizeLateMinutes($minutes))
                ->unique()
                ->sort()
                ->values(),
            'metrics' => [
                'active' => SupportTicket::query()->whereIn('status', self::ACTIVE_STATUSES)->count(),
                'unassigned' => SupportTicket::query()->whereIn('status', self::ACTIVE_STATUSES)->whereNull('assigned_to')->count(),
                'resolved' => SupportTicket::query()->whereBetween('resolved_at', [$from, now()])->count(),
                'callbacks' => SupportTicketCall::query()
                    ->where('status', 'requested')
                    ->whereHas('ticket', fn ($query) => $query->whereIn('status', self::ACTIVE_STATUSES))
                    ->count(),
                'waiting' => $waitingCustomers->total(),
                'late' => $lateReplies->total(),
                'rating_count' => (int) ($ratingSummary?->total ?? 0),
                'rating_average' => $ratingSummary?->average !== null ? round((float) $ratingSummary->average, 2) : null,
            ],
            'ratingDistribution' => (clone $ratings)
                ->select('score', DB::raw('COUNT(*) as total'))
                ->groupBy('score')
                ->pluck('total', 'score'),
            'topics' => $topics,
            'waitingCustomers' => $waitingCustomers->getCollection(),
            'waitingPagination' => $this->pagination($waitingCustomers),
            'lateReplies' => $lateReplies->getCollection(),
            'latePagination' => $this->pagination($lateReplies),
            'recent' => SupportTicket::query()
                ->with(['thread:id,uuid', 'user:id,name', 'ticketType:id,title', 'assignee:id,name', 'rating'])
                ->latest('last_message_at')
                ->limit(8)
                ->get(),
        ];
    }

    public function team(int $days): Collection
    {
        $from = now()->subDays($this->normalizeDays($days) - 1)->startOfDay();
        $ratings = SupportTicketRating::query()->where('created_at', '>=', $from);
        $responseByAgent = $this->responseTimesByAgent($from);
        $activeByAgent = SupportTicket::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
        $resolvedByAgent = SupportTicket::query()
            ->whereNotNull('assigned_to')
            ->whereBetween('resolved_at', [$from, now()])
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
        $ratingsByAgent = (clone $ratings)
            ->whereNotNull('agent_id')
            ->selectRaw('agent_id, COUNT(*) as total, AVG(score) as average')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');
        $callsByAgent = SupportTicketCall::query()
            ->whereNotNull('handled_by')
            ->whereBetween('attempted_at', [$from, now()])
            ->selectRaw('handled_by, COUNT(*) as total')
            ->groupBy('handled_by')
            ->pluck('total', 'handled_by');

        return User::query()
            ->where('access_panel', GuardNameEnum::ADMIN())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $agent) use ($activeByAgent, $resolvedByAgent, $ratingsByAgent, $callsByAgent, $responseByAgent) {
                $rating = $ratingsByAgent->get($agent->id);
                $response = $responseByAgent->get($agent->id);

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'active' => (int) ($activeByAgent[$agent->id] ?? 0),
                    'resolved' => (int) ($resolvedByAgent[$agent->id] ?? 0),
                    'calls' => (int) ($callsByAgent[$agent->id] ?? 0),
                    'rating_count' => (int) ($rating?->total ?? 0),
                    'rating_average' => $rating?->average !== null ? round((float) $rating->average, 2) : null,
                    'response_count' => (int) ($response['count'] ?? 0),
                    'response_average_seconds' => isset($response['average']) ? (int) round($response['average']) : null,
                ];
            })
            ->values();
    }

    public function ratings(int $days): Builder
    {
        $from = now()->subDays($this->normalizeDays($days) - 1)->startOfDay();

        return SupportTicketRating::query()
            ->with(['ticket:id,slug,user_id,thread_id', 'ticket.user:id,name', 'ticket.thread:id,uuid', 'agent:id,name'])
            ->where('created_at', '>=', $from)
            ->whereNotNull('feedback')
            ->where('feedback', '<>', '');
    }

    public function waitingPage(int $page, ?int $lateMinutes = null, int $perPage = 8): LengthAwarePaginator
    {
        $lateMinutes = $lateMinutes === null ? null : $this->normalizeLateMinutes($lateMinutes);
        $latestMessageAt = SupportTicketMessage::query()
            ->select('created_at')
            ->whereColumn('ticket_id', 'support_tickets.id')
            ->whereIn('send_by', ['user', 'admin'])
            ->latest('id')
            ->limit(1);

        $paginator = SupportTicket::query()
            ->select('support_tickets.*')
            ->addSelect(['waiting_since' => $latestMessageAt])
            ->with([
                'thread:id,uuid',
                'user:id,name',
                'ticketType:id,title',
                'assignee:id,name',
                'latestConversationMessage' => fn ($query) => $query->select([
                    'support_ticket_messages.id',
                    'support_ticket_messages.ticket_id',
                    'support_ticket_messages.send_by',
                    'support_ticket_messages.message',
                    'support_ticket_messages.created_at',
                ]),
            ])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereHas('latestConversationMessage', fn ($query) => $query
                ->where('send_by', 'user')
                ->when($lateMinutes !== null, fn ($late) => $late->where('created_at', '<=', now()->subMinutes($lateMinutes))))
            ->orderBy('waiting_since')
            ->orderBy('support_tickets.id')
            ->paginate(min(50, max(1, $perPage)), ['*'], 'page', max(1, $page));

        $paginator->setCollection($paginator->getCollection()
            ->map(function (SupportTicket $ticket) {
                $latest = $ticket->latestConversationMessage;

                return [
                    'ticket_id' => $ticket->id,
                    'slug' => $ticket->slug,
                    'thread_uuid' => $ticket->thread?->uuid,
                    'customer' => $ticket->user?->name ?? 'Customer',
                    'topic' => $ticket->ticketType?->title ?? 'Support',
                    'assignee' => $ticket->assignee?->name,
                    'status' => $ticket->status,
                    'preview' => str($latest?->message)->squish()->limit(100)->toString(),
                    'waiting_since' => $latest?->created_at?->toISOString(),
                    'wait_seconds' => $latest?->created_at?->diffInSeconds(now()) ?? 0,
                ];
            }));

        return $paginator;
    }

    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    private function responseTimesByAgent(CarbonInterface $from): Collection
    {
        $assignments = SupportTicketMessage::query()
            ->where('type', 'assignment')
            ->where('created_at', '>=', $from)
            ->orderBy('ticket_id')
            ->orderBy('id')
            ->get(['id', 'ticket_id', 'metadata', 'created_at'])
            ->filter(fn (SupportTicketMessage $message) => ! empty($message->metadata['agent_id']));

        if ($assignments->isEmpty()) {
            return collect();
        }

        $replies = SupportTicketMessage::query()
            ->whereIn('ticket_id', $assignments->pluck('ticket_id')->unique())
            ->where('send_by', 'admin')
            ->where('type', 'text')
            ->where('created_at', '>=', $assignments->min('created_at'))
            ->orderBy('id')
            ->get(['id', 'ticket_id', 'user_id', 'created_at'])
            ->groupBy('ticket_id');

        $samples = collect();
        foreach ($assignments->groupBy('ticket_id') as $ticketId => $ticketAssignments) {
            $ticketAssignments = $ticketAssignments->values();
            foreach ($ticketAssignments as $index => $assignment) {
                $agentId = (int) $assignment->metadata['agent_id'];
                $nextAssignmentAt = $ticketAssignments->get($index + 1)?->created_at;
                $reply = ($replies->get($ticketId) ?? collect())->first(fn (SupportTicketMessage $message) => (int) $message->user_id === $agentId
                    && $message->created_at->greaterThanOrEqualTo($assignment->created_at)
                    && (! $nextAssignmentAt || $message->created_at->lessThan($nextAssignmentAt))
                );
                if ($reply) {
                    $samples->push([
                        'agent_id' => $agentId,
                        'seconds' => $assignment->created_at->diffInSeconds($reply->created_at),
                    ]);
                }
            }
        }

        return $samples->groupBy('agent_id')->map(fn (Collection $agentSamples) => [
            'count' => $agentSamples->count(),
            'average' => $agentSamples->avg('seconds'),
        ]);
    }
}
