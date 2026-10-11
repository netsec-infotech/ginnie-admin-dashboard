<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\BulkUpload\BulkUploadJobStatusEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\BulkUploadJob;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BulkUploadFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected BulkUploadJob $job)
    {
    }

    /**
     * Routed to FCM (push), the database channel (in-app bell), and mail —
     * matches the choice made when wiring the "Notify me when finished" flag.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, FirebaseChannel::class, 'mail'];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $payload = $this->buildPayload();

            $mail = (new MailMessage)
                ->subject($payload['subject'])
                ->greeting('Hello '.($notifiable->name ?? ''))
                ->line($payload['body']);

            foreach ($this->summaryLines() as $line) {
                $mail->line($line);
            }

            return $mail
                ->action(__('labels.bulk_upload_notification_view_job'), $payload['url']);
        } catch (\Throwable $e) {
            Log::error('BulkUploadFinishedNotification mail failed: '.$e->getMessage(), [
                'job_id' => $this->job->id,
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toFirebase(object $notifiable): array
    {
        $payload = $this->buildPayload();

        return [
            'title' => $payload['subject'],
            'body' => $payload['body'],
            'image' => null,
            'data' => [
                'type' => NotificationTypeEnum::BULK_UPLOAD_FINISHED(),
                'role_type' => NotificationRoleTypeEnum::ADMIN(),
                'job_id' => $this->job->id,
                'module' => $payload['module_key'],
                'status' => $this->job->status?->value,
                'url' => $payload['url'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $payload = $this->buildPayload();

        return [
            'title' => $payload['subject'],
            'message' => $payload['body'],
            'type' => NotificationTypeEnum::BULK_UPLOAD_FINISHED(),
            'sent_to' => 'admin',
            'role_type' => NotificationRoleTypeEnum::ADMIN(),
            'user_id' => $notifiable->id ?? null,
            'metadata' => [
                'job_id' => $this->job->id,
                'module' => $payload['module_key'],
                'status' => $this->job->status?->value,
                'total_rows' => (int) $this->job->total_rows,
                'processed_rows' => (int) $this->job->processed_rows,
                'success_count' => (int) $this->job->success_count,
                'updated_count' => (int) $this->job->updated_count,
                'skipped_count' => (int) $this->job->skipped_count,
                'failed_count' => (int) $this->job->failed_count,
                'url' => $payload['url'],
            ],
        ];
    }

    /**
     * Resolve subject, body, URL, and module label from the job. Kept in one
     * place so toMail / toFirebase / toDatabase stay in sync.
     *
     * @return array{subject:string,body:string,url:string,module_key:string,module_label:string}
     */
    protected function buildPayload(): array
    {
        $registry = app(BulkUploadModuleRegistry::class);
        $cfg = $registry->getByModel($this->job->uploadable_type);
        $moduleKey = $cfg?->key ?? 'unknown';
        $moduleLabel = $cfg ? __($cfg->displayNameKey) : $moduleKey;

        $isFailure = $this->job->status?->value === BulkUploadJobStatusEnum::FAILED();
        $bodyKey = $isFailure
            ? 'labels.bulk_upload_notification_body_failed'
            : 'labels.bulk_upload_notification_body_completed';
        $subjectKey = $isFailure
            ? 'labels.bulk_upload_notification_subject_failed'
            : 'labels.bulk_upload_notification_subject_completed';

        $url = $cfg
            ? route("admin.{$moduleKey}.bulk-upload.jobs.show", ['id' => $this->job->id])
            : url('/');

        return [
            'subject' => __($subjectKey, ['module' => $moduleLabel, 'file' => $this->job->original_filename]),
            'body' => __($bodyKey, ['module' => $moduleLabel, 'file' => $this->job->original_filename]),
            'url' => $url,
            'module_key' => $moduleKey,
            'module_label' => $moduleLabel,
        ];
    }

    /**
     * Smart per-counter summary — only the non-zero buckets are mentioned so
     * Create-mode jobs don't dump "0 updated, 0 skipped" and Update-mode jobs
     * don't dump "0 created".
     *
     * @return array<int, string>
     */
    protected function summaryLines(): array
    {
        $lines = [
            __('labels.bulk_upload_notification_summary_total', [
                'processed' => (int) $this->job->processed_rows,
                'total' => (int) $this->job->total_rows,
            ]),
        ];
        if ((int) $this->job->success_count > 0) {
            $lines[] = __('labels.bulk_upload_notification_summary_created', ['count' => (int) $this->job->success_count]);
        }
        if ((int) $this->job->updated_count > 0) {
            $lines[] = __('labels.bulk_upload_notification_summary_updated', ['count' => (int) $this->job->updated_count]);
        }
        if ((int) $this->job->skipped_count > 0) {
            $lines[] = __('labels.bulk_upload_notification_summary_skipped', ['count' => (int) $this->job->skipped_count]);
        }
        if ((int) $this->job->failed_count > 0) {
            $lines[] = __('labels.bulk_upload_notification_summary_failed', ['count' => (int) $this->job->failed_count]);
        }

        return $lines;
    }
}
