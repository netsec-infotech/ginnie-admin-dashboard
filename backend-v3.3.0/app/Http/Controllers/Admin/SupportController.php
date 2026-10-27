<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Exceptions\SupportConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportAvailabilityRequest;
use App\Models\SupportThread;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketRating;
use App\Models\SupportTicketType;
use App\Models\User;
use App\Services\BroadcastSettingsService;
use App\Services\Support\SupportAvailabilityService;
use App\Services\Support\SupportDashboardService;
use App\Services\Support\SupportPresenter;
use App\Services\Support\SupportService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportController extends Controller
{
    public function __construct(
        private readonly SupportService $support,
        private readonly BroadcastSettingsService $broadcastSettings,
        private readonly SupportDashboardService $dashboard,
        private readonly SupportAvailabilityService $availability,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user?->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) ?? false;
        $hasPermission = fn (string $permission): bool => $isSuperAdmin || ($user?->can($permission) ?? false);

        return view('admin.support.index', [
            'realtime' => $this->broadcastSettings->clientConfig(route('admin.support.broadcasting.auth')),
            'pollInterval' => (int) config('support.poll_interval_ms', 15000),
            'pollingEnabled' => (bool) config('support.polling_enabled', true),
            'realtimeConfigured' => $this->broadcastSettings->isConfigured(),
            'agents' => User::where('access_panel', GuardNameEnum::ADMIN())->orderBy('name')->get(['id', 'name']),
            'topics' => SupportTicketType::where('is_active', true)->orderBy('sort_order')->orderBy('title')->get(['id', 'title']),
            'canReply' => $hasPermission('support.reply'),
            'canAssign' => $hasPermission('support.assign'),
            'canManage' => $hasPermission('support.manage'),
            'availability' => $this->availability->configuration(),
        ]);
    }

    public function dashboard(Request $request): View
    {
        $data = $this->dashboard->data(
            $this->dashboard->normalizeDays($request->query('days', 30)),
            $request->query('late_minutes'),
        );

        return view('admin.support.dashboard', [
            ...$data,
            'pollInterval' => (int) config('support.poll_interval_ms', 15000),
            'teamColumns' => [
                ['data' => 'executive', 'name' => 'name', 'label' => 'Executive', 'title' => 'Executive'],
                ['data' => 'active', 'name' => 'active', 'label' => 'Active', 'title' => 'Active', 'className' => 'text-end'],
                ['data' => 'resolved', 'name' => 'resolved', 'label' => 'Resolved', 'title' => 'Resolved', 'className' => 'text-end'],
                ['data' => 'calls', 'name' => 'calls', 'label' => 'Calls', 'title' => 'Calls', 'className' => 'text-end'],
                ['data' => 'average_response', 'name' => 'response_average_seconds', 'label' => 'Average response', 'title' => 'Average response', 'className' => 'text-end'],
                ['data' => 'csat', 'name' => 'rating_average', 'label' => 'CSAT', 'title' => 'CSAT', 'className' => 'text-end'],
            ],
            'ratingColumns' => [
                ['data' => 'ticket', 'name' => 'ticket', 'label' => 'Ticket', 'title' => 'Ticket', 'orderable' => false],
                ['data' => 'customer', 'name' => 'customer', 'label' => 'Customer', 'title' => 'Customer', 'orderable' => false],
                ['data' => 'executive', 'name' => 'executive', 'label' => 'Executive', 'title' => 'Executive', 'orderable' => false],
                ['data' => 'rating', 'name' => 'score', 'label' => 'Rating', 'title' => 'Rating'],
                ['data' => 'feedback', 'name' => 'feedback', 'label' => 'Feedback', 'title' => 'Feedback', 'orderable' => false, 'className' => 'text-wrap'],
                ['data' => 'submitted_at', 'name' => 'created_at', 'label' => 'Submitted', 'title' => 'Submitted'],
            ],
        ]);
    }

    public function updateAvailability(SupportAvailabilityRequest $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('Support availability saved.'),
            data: $this->availability->save($request->validated()),
        );
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', Rule::in([7, 30, 90])],
            'late_minutes' => ['nullable', 'integer', 'between:1,1440'],
        ]);
        $data = $this->dashboard->data(
            $this->dashboard->normalizeDays($validated['days'] ?? 30),
            $validated['late_minutes'] ?? null,
        );

        return response()->json([
            'data' => [
                'html' => view('admin.support.partials.dashboard-content', $data)->render(),
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    public function dashboardQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queue' => ['required', Rule::in(['waiting', 'late'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'late_minutes' => ['nullable', 'integer', 'between:1,1440'],
        ]);
        $queue = $validated['queue'];
        $lateMinutes = $this->dashboard->normalizeLateMinutes($validated['late_minutes'] ?? null);
        $paginator = $this->dashboard->waitingPage(
            (int) ($validated['page'] ?? 1),
            $queue === 'late' ? $lateMinutes : null,
        );

        return response()->json([
            'data' => [
                'html' => view('admin.support.partials.dashboard-queue-items', [
                    'items' => $paginator->getCollection(),
                    'queue' => $queue,
                    'lateReplyMinutes' => $lateMinutes,
                    'showEmpty' => $paginator->currentPage() === 1,
                ])->render(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function dashboardTeam(Request $request): JsonResponse
    {
        $days = $this->dashboard->normalizeDays($request->integer('days', 30));
        $rows = $this->dashboard->team($days);
        $total = $rows->count();
        $search = Str::lower(trim((string) data_get($request->input('search'), 'value', '')));
        if ($search !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(Str::lower($row['name']), $search))->values();
        }
        $filtered = $rows->count();
        $orderIndex = (int) data_get($request->input('order'), '0.column', 2);
        $orderName = data_get($request->input('columns'), $orderIndex.'.name', 'resolved');
        $orderName = in_array($orderName, ['name', 'active', 'resolved', 'calls', 'response_average_seconds', 'rating_average'], true)
            ? $orderName
            : 'resolved';
        $descending = data_get($request->input('order'), '0.dir', 'desc') !== 'asc';
        $rows = $rows->sortBy(fn (array $row) => $row[$orderName] ?? -1, SORT_REGULAR, $descending)
            ->slice(max(0, $request->integer('start')), min(100, max(1, $request->integer('length', 10))))
            ->values()
            ->map(function (array $row) {
                $initials = collect(preg_split('/\s+/', trim($row['name'])))->filter()->take(2)
                    ->map(fn (string $part) => mb_substr($part, 0, 1))->implode('');
                $response = $row['response_average_seconds'] === null
                    ? '<span class="text-secondary">No replies</span>'
                    : e($this->formatSupportDuration($row['response_average_seconds'])).' <span class="text-secondary">('.number_format($row['response_count']).')</span>';
                $csat = $row['rating_average'] === null
                    ? '<span class="text-secondary">No ratings</span>'
                    : '<span class="text-yellow"><i class="ti ti-star-filled"></i></span> '.number_format($row['rating_average'], 2).' <span class="text-secondary">('.number_format($row['rating_count']).')</span>';

                return [
                    'executive' => '<div class="d-flex align-items-center gap-2"><span class="avatar avatar-sm">'.e($initials).'</span><span class="fw-medium">'.e($row['name']).'</span></div>',
                    'active' => number_format($row['active']),
                    'resolved' => number_format($row['resolved']),
                    'calls' => number_format($row['calls']),
                    'average_response' => $response,
                    'csat' => $csat,
                ];
            });

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function dashboardRatings(Request $request): JsonResponse
    {
        $days = $this->dashboard->normalizeDays($request->integer('days', 30));
        $query = $this->dashboard->ratings($days);
        $total = (clone $query)->count();
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        if ($search !== '') {
            $query->where(function ($match) use ($search) {
                $match->where('feedback', 'like', "%{$search}%")
                    ->orWhere('score', 'like', "%{$search}%")
                    ->orWhereHas('ticket', fn ($ticket) => $ticket->where('slug', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")))
                    ->orWhereHas('agent', fn ($agent) => $agent->where('name', 'like', "%{$search}%"));
            });
        }
        $filtered = (clone $query)->count();
        $orderIndex = (int) data_get($request->input('order'), '0.column', 5);
        $orderName = data_get($request->input('columns'), $orderIndex.'.name', 'created_at');
        $orderName = in_array($orderName, ['score', 'created_at'], true) ? $orderName : 'created_at';
        $direction = data_get($request->input('order'), '0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $ratings = $query->orderBy($orderName, $direction)
            ->skip(max(0, $request->integer('start')))
            ->take(min(100, max(1, $request->integer('length', 10))))
            ->get()
            ->map(function (SupportTicketRating $rating) {
                $ticket = $rating->ticket;
                $ticketLabel = e($ticket?->slug ?? 'Deleted ticket');
                if ($ticket?->thread?->uuid) {
                    $ticketLabel = '<a class="fw-medium" href="'.e(route('admin.support.index', ['thread' => $ticket->thread->uuid, 'scope' => 'all'])).'">'.$ticketLabel.'</a>';
                }
                $stars = collect(range(1, 5))->map(fn (int $star) => '<i class="ti '.($star <= $rating->score ? 'ti-star-filled' : 'ti-star').' '.($star <= $rating->score ? 'text-yellow' : 'text-secondary').'" aria-hidden="true"></i>')->implode('');

                return [
                    'ticket' => $ticketLabel,
                    'customer' => e($ticket?->user?->name ?? 'Customer'),
                    'executive' => e($rating->agent?->name ?? 'Unassigned'),
                    'rating' => '<span class="text-nowrap" aria-label="'.$rating->score.' out of 5">'.$stars.' <span class="ms-1">'.$rating->score.'/5</span></span>',
                    'feedback' => '<div class="text-wrap" style="min-width: 16rem; max-width: 32rem">'.e($rating->feedback).'</div>',
                    'submitted_at' => $rating->created_at?->format('d M Y, h:i A'),
                ];
            });

        return response()->json([
            'draw' => $request->integer('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $ratings,
        ]);
    }

    private function formatSupportDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' sec';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours.' hr'.($minutes > 0 ? ' '.$minutes.' min' : '');
    }

    public function inbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'scope' => ['nullable', 'in:unassigned,mine,all'],
            'status' => ['nullable', 'in:open,in_progress,waiting_customer,resolved,closed'],
            'topic_id' => ['nullable', 'integer'],
            'callback' => ['nullable', 'in:required,all'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $agent = $request->user();
        $query = SupportThread::query()->with([
            'user', 'activeTicket.ticketType', 'activeTicket.order', 'activeTicket.assignee',
            'tickets' => fn ($query) => $query->reorder()->latest('id')->limit(1),
        ]);

        if (! empty($validated['status'])) {
            $query->whereHas('tickets', fn ($q) => $q->where('status', $validated['status']));
        }
        if (! empty($validated['topic_id'])) {
            $query->whereHas('tickets', fn ($q) => $q->where('ticket_type_id', $validated['topic_id']));
        }
        if (($validated['callback'] ?? null) === 'required') {
            $query->whereHas('tickets.calls', fn ($q) => $q->where('status', 'requested'));
        }
        if (! empty($validated['rating'])) {
            $query->whereHas('tickets.rating', fn ($q) => $q->where('score', $validated['rating']));
        }
        if ($search = trim((string) ($validated['q'] ?? ''))) {
            $orderId = (int) ltrim($search, '#');
            $query->where(function ($q) use ($search, $orderId) {
                $q->whereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%"))
                    ->orWhereHas('tickets', fn ($ticket) => $ticket
                        ->where('slug', 'like', "%{$search}%")
                        ->when($orderId > 0, fn ($order) => $order->orWhere('order_id', $orderId))
                        ->orWhereHas('messages', fn ($message) => $message->where('message', 'like', "%{$search}%")));
            });
        }

        $scopeCounts = [
            'unassigned' => (clone $query)->whereHas('activeTicket', fn ($q) => $q->whereNull('assigned_to'))->count(),
            'mine' => (clone $query)->whereHas('activeTicket', fn ($q) => $q->where('assigned_to', $agent->id))->count(),
            'all' => (clone $query)->count(),
        ];
        $scope = $validated['scope'] ?? 'unassigned';
        if ($scope === 'unassigned') {
            $query->whereHas('activeTicket', fn ($q) => $q->whereNull('assigned_to'));
        } elseif ($scope === 'mine') {
            $query->whereHas('activeTicket', fn ($q) => $q->where('assigned_to', $agent->id));
        }

        $paginator = $query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(30);
        $rows = collect($paginator->items())->map(function (SupportThread $thread) use ($agent) {
            $ticket = $thread->activeTicket ?? $thread->tickets->first();
            $latest = $ticket?->messages()->where('type', '!=', 'auto_reply')->reorder()->latest('id')->first();
            $readCursor = $thread->reads()->where('user_id', $agent->id)->value('last_read_message_id') ?? 0;
            $unread = SupportTicketMessage::query()
                ->whereIn('ticket_id', $thread->tickets()->pluck('id'))
                ->where('send_by', 'user')
                ->where('id', '>', $readCursor)
                ->count();
            $callbackRequired = $ticket?->calls()->where('status', 'requested')->exists() ?? false;

            return [
                'uuid' => $thread->uuid,
                'customer' => [
                    'name' => $thread->user?->name ?? 'Guest/Deleted User',
                    'email' => $thread->user?->email ?? '—',
                    'phone' => $thread->user?->mobile ?? '—',
                    'avatar' => $thread->user?->profile_image,
                ],
                'ticket' => $ticket ? [
                    'slug' => $ticket->slug,
                    'status' => $ticket->status,
                    'topic' => $ticket->ticketType?->title,
                    'order_number' => $ticket->order_id ? '#'.$ticket->order_id : null,
                    'assignee' => $ticket->assignee?->name,
                ] : null,
                'preview' => $latest ? SupportPresenter::messageText($latest, true) : ($ticket?->description ?? 'No messages yet'),
                'latest_at' => ($latest?->created_at ?? $thread->last_message_at)?->toISOString(),
                'unread_count' => $unread,
                'callback_required' => $callbackRequired,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'scope_counts' => $scopeCounts,
            ],
        ]);
    }

    public function show(SupportThread $thread): JsonResponse
    {
        return response()->json(['data' => SupportPresenter::thread($thread, true, 50)]);
    }

    public function messages(Request $request, SupportThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'before_message_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return response()->json(['data' => SupportPresenter::messagePage(
            $thread,
            true,
            (int) $validated['before_message_id'],
            (int) ($validated['limit'] ?? 50),
        )]);
    }

    public function typing(Request $request, SupportThread $thread): JsonResponse
    {
        $validated = $request->validate(['typing' => ['required', 'boolean']]);
        abort_unless($thread->activeTicket?->isActive() && $thread->activeTicket->assigned_to === $request->user()->id, 409);
        $this->support->broadcastTyping($thread, $request->user(), 'admin', $validated['typing']);

        return response()->json(['data' => ['typing' => $validated['typing']]], 202);
    }

    public function realtime(): JsonResponse
    {
        return response()->json(['data' => [
            'configured' => $this->broadcastSettings->isConfigured(),
            'realtime' => $this->broadcastSettings->clientConfig(route('admin.support.broadcasting.auth')),
        ]]);
    }

    public function updates(Request $request, SupportThread $thread): JsonResponse
    {
        $after = max(0, (int) $request->query('after_message_id', 0));
        $messages = SupportTicketMessage::query()->with(['user.media', 'media'])
            ->whereIn('ticket_id', $thread->tickets()->pluck('id'))
            ->where('id', '>', $after)->orderBy('id')->limit(200)->get();

        return response()->json(['data' => [
            'messages' => $messages->map(fn ($message) => SupportPresenter::message($message, true))->values(),
            'active_ticket_id' => $thread->fresh()->active_ticket_id,
            'message_cursor' => $messages->max('id') ?? $after,
        ]]);
    }

    public function message(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'client_message_id' => ['required', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ]);

        try {
            $message = $this->support->addMessage($ticket, $request->user(), 'admin', $validated, $request->file('attachments', []));
        } catch (SupportConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => SupportPresenter::message($message, true)], 201);
    }

    public function assign(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('access_panel', GuardNameEnum::ADMIN()),
            ],
        ]);
        $agent = isset($validated['agent_id']) ? User::findOrFail($validated['agent_id']) : null;
        $ticket = $this->support->assign($ticket, $agent, $request->user());

        return response()->json(['data' => SupportPresenter::ticket($ticket, true)]);
    }

    public function status(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:open,in_progress,waiting_customer']]);
        $ticket = $this->support->updateStatus($ticket, $request->user(), $validated['status']);

        return response()->json(['data' => SupportPresenter::ticket($ticket, true)]);
    }

    public function callback(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'in:requested,attempted,connected,no_answer,completed,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'private_note' => ['nullable', 'string', 'max:4000'],
        ]);
        $call = $this->support->logCall($ticket, $request->user(), $validated);

        return response()->json(['data' => $call], 201);
    }

    public function resolve(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate(['summary' => ['nullable', 'string', 'max:2000']]);
        $ticket = $this->support->resolve($ticket, $request->user(), 'admin', $validated['summary'] ?? null);

        return response()->json(['data' => SupportPresenter::ticket($ticket, true)]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate(['summary' => ['nullable', 'string', 'max:2000']]);
        $ticket = $this->support->resolve($ticket, $request->user(), 'admin', $validated['summary'] ?? null, 'closed');

        return response()->json(['data' => SupportPresenter::ticket($ticket, true)]);
    }

    public function read(Request $request, SupportThread $thread): JsonResponse
    {
        $validated = $request->validate(['message_id' => ['nullable', 'integer']]);
        $read = $this->support->markRead($thread, $request->user(), $validated['message_id'] ?? null);

        return response()->json(['data' => ['read_at' => $read->read_at?->toISOString()]]);
    }

    public function attachment(Request $request, Media $media): BinaryFileResponse|StreamedResponse
    {
        abort_unless($media->model_type === SupportTicketMessage::class, 404);
        abort_unless(SupportTicketMessage::whereKey($media->model_id)->exists(), 404);

        if ($request->boolean('inline') || $this->shouldRenderInlineImage($request, $media)) {
            return $this->inlineAttachmentResponse($media);
        }

        $file = $this->supportAttachmentFile($media);
        abort_unless($file !== null, 404);

        return Storage::disk($file['disk'])->download($file['path'], $media->file_name);
    }

    private function inlineAttachmentResponse(Media $media): BinaryFileResponse|StreamedResponse
    {
        $file = $this->supportAttachmentFile($media);

        abort_unless($file !== null, 404);

        $disk = Storage::disk($file['disk']);
        $path = $file['path'];

        $headers = [
            'Content-Type' => $this->attachmentMimeType($media),
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'Cache-Control' => 'private, max-age=300',
        ];

        if (is_file($media->getPath())) {
            // Local support files render reliably in browsers when served from Spatie's resolved path.
            return response()->file($media->getPath(), $headers);
        }

        $absolutePath = method_exists($disk, 'path') ? $disk->path($path) : null;
        if ($absolutePath !== null && is_file($absolutePath)) {
            // Older support files may still need the fallback disk/path resolver.
            return response()->file($absolutePath, $headers);
        }

        // Protected image previews need inline headers so the browser can render them inside <img>.
        return $disk->response($path, $media->file_name, $headers, 'inline');
    }

    /**
     * @return array{disk:string,path:string}|null
     */
    private function supportAttachmentFile(Media $media): ?array
    {
        $fileName = (string) $media->file_name;
        $disks = array_unique(array_filter([
            $media->disk,
            config('support.attachments_disk', 'local'),
            'local',
            'public',
        ]));
        $paths = array_unique(array_filter([
            $media->getPathRelativeToRoot(),
            $media->getKey().'/'.$fileName,
            'support_attachments/'.$media->getKey().'/'.$fileName,
        ]));

        foreach ($disks as $diskName) {
            $disk = Storage::disk($diskName);
            foreach ($paths as $path) {
                if ($disk->exists($path)) {
                    return ['disk' => $diskName, 'path' => $path];
                }
            }
        }

        // Support chat has used multiple private storage layouts during integration; fail closed if none exists.
        return null;
    }

    private function shouldRenderInlineImage(Request $request, Media $media): bool
    {
        if (! str_starts_with($this->attachmentMimeType($media), 'image/')
            && ! in_array(strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return false;
        }

        // Image attachments are previewed directly in chat; append download=1 when a forced download is needed.
        return ! $request->boolean('download');
    }

    private function attachmentMimeType(Media $media): string
    {
        if (str_starts_with((string) $media->mime_type, 'image/')) {
            return (string) $media->mime_type;
        }

        // Some customer uploads arrive with a generic MIME type; browsers still need image content headers.
        return match (strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => $media->mime_type ?: 'application/octet-stream',
        };
    }

    public function deleteMessage(Request $request, SupportTicketMessage $message): JsonResponse
    {
        $this->support->deleteMessage($message, $request->user());

        return response()->json(['message' => 'Message deleted.']);
    }

    public function clearThread(Request $request, SupportThread $thread): JsonResponse
    {
        $this->support->clearThread($thread, $request->user());

        return response()->json(['message' => 'Chat history cleared.']);
    }

    public function topics(): JsonResponse
    {
        return response()->json(['data' => SupportTicketType::orderBy('sort_order')->orderBy('title')->get()]);
    }

    public function storeTopic(Request $request): JsonResponse
    {
        $validated = $this->validateTopic($request);
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['title']);
        $topic = SupportTicketType::create($validated);

        return response()->json(['data' => $topic], 201);
    }

    public function updateTopic(Request $request, SupportTicketType $topic): JsonResponse
    {
        $topic->update($this->validateTopic($request, $topic));

        return response()->json(['data' => $topic->fresh()]);
    }

    private function validateTopic(Request $request, ?SupportTicketType $topic = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('support_ticket_types', 'slug')->ignore($topic?->id)],
            'context' => ['required', 'in:both,order,general'],
            'guidance' => ['nullable', 'string', 'max:2000'],
            'customer_quick_replies' => ['nullable', 'array', 'max:12'],
            'customer_quick_replies.*' => ['required', 'string', 'max:191', 'distinct'],
            'agent_quick_replies' => ['nullable', 'array', 'max:12'],
            'agent_quick_replies.*' => ['required', 'string', 'max:191', 'distinct'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);
    }
}
