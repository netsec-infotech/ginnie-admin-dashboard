<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Services\CronMonitorService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CronMonitorController extends Controller
{
    use ChecksPermissions;

    protected bool $viewPermission = false;
    protected bool $runPermission = false;

    public function __construct(protected CronMonitorService $cronMonitorService)
    {
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::CRON_MONITOR_VIEW());
        $this->runPermission = $this->hasPermission(AdminPermissionEnum::CRON_MONITOR_RUN());
    }

    /**
     * Display the cron monitor dashboard.
     */
    public function index(): View
    {
        if (!$this->viewPermission) {
            abort(403, __('labels.unauthorized'));
        }

        $commands = $this->cronMonitorService->getCommandStatuses();
        $isSchedulerHealthy = $this->cronMonitorService->isSchedulerHealthy();
        $isQueueWorkerHealthy = $this->cronMonitorService->isQueueWorkerHealthy();
        $runPermission = $this->runPermission;

        return view('admin.cron-monitor.index', compact(
            'commands',
            'isSchedulerHealthy',
            'isQueueWorkerHealthy',
            'runPermission',
        ));
    }

    /**
     * Return updated status data for auto-refresh polling.
     */
    public function status(): JsonResponse
    {
        if (!$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        try {
            $commands = $this->cronMonitorService->getCommandStatuses();
            $isSchedulerHealthy = $this->cronMonitorService->isSchedulerHealthy();
            $isQueueWorkerHealthy = $this->cronMonitorService->isQueueWorkerHealthy();

            return ApiResponseType::sendJsonResponse(true, 'labels.success', [
                'commands' => $commands,
                'is_scheduler_healthy' => $isSchedulerHealthy,
                'is_queue_worker_healthy' => $isQueueWorkerHealthy,
            ]);
        } catch (\Throwable $e) {
            Log::error('Cron monitor status check failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null);
        }
    }

    /**
     * Manually run a scheduled command.
     */
    public function run(Request $request): JsonResponse
    {
        if (!$this->runPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        $command = $request->input('command');

        if (!$command) {
            return ApiResponseType::sendJsonResponse(false, 'labels.command_is_required', null);
        }

        try {
            $log = $this->cronMonitorService->runCommand($command);

            return ApiResponseType::sendJsonResponse(true, 'labels.command_executed_successfully', [
                'status' => $log->status,
                'duration_ms' => $log->duration_ms,
                'output' => $log->output,
                'finished_at' => $log->finished_at?->format('Y-m-d H:i:s'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.invalid_command', null);
        } catch (\Throwable $e) {
            Log::error('Manual command execution failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null);
        }
    }

    /**
     * Get run history for a specific command.
     */
    public function history(Request $request): JsonResponse
    {
        if (!$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, 'labels.unauthorized', null);
        }

        $command = $request->input('command');

        if (!$command) {
            return ApiResponseType::sendJsonResponse(false, 'labels.command_is_required', null);
        }

        try {
            $history = $this->cronMonitorService->getRunHistory($command, 20);

            return ApiResponseType::sendJsonResponse(true, 'labels.success', $history);
        } catch (\Throwable $e) {
            Log::error('Cron history fetch failed', ['error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null);
        }
    }
}
