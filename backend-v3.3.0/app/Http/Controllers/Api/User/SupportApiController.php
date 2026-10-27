<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\GuardNameEnum;
use App\Exceptions\SupportConflictException;
use App\Http\Controllers\Controller;
use App\Models\SupportThread;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SupportTicketType;
use App\Services\BroadcastSettingsService;
use App\Services\Support\SupportPresenter;
use App\Services\Support\SupportService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportApiController extends Controller
{
    public function __construct(
        private readonly SupportService $support,
        private readonly BroadcastSettingsService $broadcastSettings,
    ) {}

    public function thread(Request $request): JsonResponse
    {
        $thread = $this->support->threadFor($request->user());
        $recentOrders = $request->user()->orders()->latest('id')->limit(20)->get()->map(fn ($order) => [
            'id' => $order->id,
            'slug' => $order->slug,
            'number' => '#'.$order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'currency_code' => $order->currency_code,
            'total' => $order->final_total,
            'created_at' => $order->created_at?->toISOString(),
        ]);

        return ApiResponseType::sendJsonResponse(true, 'Support thread retrieved.', [
            'thread' => SupportPresenter::thread($thread, false, 50),
            'recent_orders' => $recentOrders,
            'poll_interval_ms' => (int) config('support.poll_interval_ms', 15000),
            'polling_enabled' => (bool) config('support.polling_enabled', true),
            'realtime' => $this->broadcastSettings->clientConfig(route('user.support.broadcasting.auth')),
        ]);
    }

    public function updates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after_message_id' => ['nullable', 'integer', 'min:0'],
            'after_call_id' => ['nullable', 'integer', 'min:0'],
        ]);
        $thread = $this->support->threadFor($request->user());
        $ticketIds = $thread->tickets()->pluck('id');
        $messages = SupportTicketMessage::query()
            ->with(['user', 'media'])
            ->whereIn('ticket_id', $ticketIds)
            ->where('id', '>', $validated['after_message_id'] ?? 0)
            ->orderBy('id')
            ->limit(200)
            ->get();
        $calls = $thread->tickets()->with(['calls' => fn ($query) => $query
            ->where('id', '>', $validated['after_call_id'] ?? 0)->orderBy('id')])->get()
            ->flatMap->calls
            ->map(fn ($call) => [
                'id' => $call->id,
                'session_id' => $call->ticket_id,
                'status' => $call->status,
                'scheduled_at' => $call->scheduled_at?->toISOString(),
                'created_at' => $call->created_at?->toISOString(),
            ])->values();

        return ApiResponseType::sendJsonResponse(true, 'Support updates retrieved.', [
            'messages' => $messages->map(fn ($message) => SupportPresenter::message($message))->values(),
            'calls' => $calls,
            'active_ticket_id' => $thread->fresh()->active_ticket_id,
            'message_cursor' => $messages->max('id') ?? (int) ($validated['after_message_id'] ?? 0),
            'call_cursor' => $calls->max('id') ?? (int) ($validated['after_call_id'] ?? 0),
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'before_message_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $thread = $this->support->threadFor($request->user());

        return ApiResponseType::sendJsonResponse(true, 'Older support messages retrieved.', SupportPresenter::messagePage(
            $thread,
            false,
            (int) $validated['before_message_id'],
            (int) ($validated['limit'] ?? 50),
        ));
    }

    public function realtime(Request $request): JsonResponse
    {
        $thread = SupportThread::where('user_id', $request->user()->id)->first();

        return ApiResponseType::sendJsonResponse(true, 'Support realtime configuration retrieved.', [
            'user_id' => $request->user()->id,
            'thread_uuid' => $thread?->uuid,
            'configured' => $this->broadcastSettings->isConfigured(),
            'realtime' => $this->broadcastSettings->clientConfig(route('user.support.broadcasting.auth')),
        ]);
    }

    public function topics(): JsonResponse
    {
        $topics = SupportTicketType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('title')->get()
            ->map(fn ($topic) => [
                'id' => $topic->id,
                'slug' => $topic->slug,
                'title' => $topic->title,
                'context' => $topic->context,
                'guidance' => $topic->guidance,
                'quick_replies' => $topic->customer_quick_replies ?? [],
            ]);

        return ApiResponseType::sendJsonResponse(true, 'Support topics retrieved.', $topics);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic_id' => ['required', 'integer', 'exists:support_ticket_types,id'],
            'order_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:191'],
            'message' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'client_message_id' => ['nullable', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ]);

        try {
            $ticket = $this->support->startSession($request->user(), $validated);
        } catch (SupportConflictException $exception) {
            return ApiResponseType::sendJsonResponse(false, $exception->getMessage(), null, 409);
        }

        return ApiResponseType::sendJsonResponse(true, 'Support session started.', SupportPresenter::ticket($ticket), 201);
    }

    public function storeMessage(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'client_message_id' => ['required', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ]);
        $ticket = $this->customerTicket($request, $slug);

        try {
            $message = $this->support->addMessage(
                $ticket,
                $request->user(),
                'user',
                $validated,
                $request->file('attachments', []),
            );
        } catch (SupportConflictException $exception) {
            return ApiResponseType::sendJsonResponse(false, $exception->getMessage(), null, 409);
        }

        return ApiResponseType::sendJsonResponse(true, 'Message sent.', SupportPresenter::message($message), 201);
    }

    public function typing(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate(['typing' => ['required', 'boolean']]);
        $ticket = $this->customerTicket($request, $slug);
        abort_unless($ticket->isActive(), 409);
        $this->support->broadcastTyping($ticket->thread, $request->user(), 'customer', $validated['typing']);

        return ApiResponseType::sendJsonResponse(true, 'Typing state sent.', ['typing' => $validated['typing']], 202);
    }

    public function markRead(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate(['message_id' => ['nullable', 'integer']]);
        $ticket = $this->customerTicket($request, $slug);
        $read = $this->support->markRead($ticket->thread, $request->user(), $validated['message_id'] ?? null);

        return ApiResponseType::sendJsonResponse(true, 'Conversation marked as read.', ['read_at' => $read->read_at?->toISOString()]);
    }

    public function callback(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);
        $ticket = $this->customerTicket($request, $slug);

        try {
            $call = $this->support->requestCallback($ticket, $request->user(), $validated);
        } catch (SupportConflictException $exception) {
            return ApiResponseType::sendJsonResponse(false, $exception->getMessage(), null, 409);
        }

        return ApiResponseType::sendJsonResponse(true, 'Callback requested.', ['id' => $call->id, 'status' => $call->status], 201);
    }

    public function resolve(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate(['summary' => ['nullable', 'string', 'max:2000']]);
        $ticket = $this->customerTicket($request, $slug);
        $ticket = $this->support->resolve($ticket, $request->user(), 'customer', $validated['summary'] ?? null);

        return ApiResponseType::sendJsonResponse(true, 'Support session resolved.', SupportPresenter::ticket($ticket));
    }

    public function rating(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        $ticket = $this->customerTicket($request, $slug);

        try {
            $rating = $this->support->rate($ticket, $request->user(), $validated['score'], $validated['feedback'] ?? null);
        } catch (SupportConflictException $exception) {
            return ApiResponseType::sendJsonResponse(false, $exception->getMessage(), null, 409);
        }

        return ApiResponseType::sendJsonResponse(true, 'Thank you for your feedback.', [
            'score' => $rating->score,
            'feedback' => $rating->feedback,
            'agent' => $rating->agent ? [
                'id' => $rating->agent->id,
                'name' => $rating->agent->name,
            ] : null,
        ]);
    }

    public function attachment(Request $request, Media $media): BinaryFileResponse|StreamedResponse
    {
        abort_unless($media->model_type === SupportTicketMessage::class, 404);
        $message = SupportTicketMessage::with('ticket')->findOrFail($media->model_id);

        $hasValidSignature = $request->hasValidSignature() || $request->hasValidSignature(false);
        $user = $request->user('sanctum') ?? $request->user('web') ?? $request->user();

        if (! $hasValidSignature) {
            abort_unless($user !== null, 404);
            $isOwner = (int) $message->ticket?->user_id === (int) $user->id;
            $isAdmin = ($user->access_panel ?? null) === GuardNameEnum::ADMIN()
                || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
            abort_unless($isOwner || $isAdmin, 404);
        }

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
            // Local support files render reliably in authenticated apps when served from Spatie's resolved path.
            return response()->file($media->getPath(), $headers);
        }

        $absolutePath = method_exists($disk, 'path') ? $disk->path($path) : null;
        if ($absolutePath !== null && is_file($absolutePath)) {
            // Older support files may still need the fallback disk/path resolver.
            return response()->file($absolutePath, $headers);
        }

        // Protected image previews need inline headers so authenticated clients can render them directly.
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

    private function customerTicket(Request $request, string $slug): SupportTicket
    {
        return SupportTicket::query()
            ->with('thread')
            ->where('user_id', $request->user()->id)
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
