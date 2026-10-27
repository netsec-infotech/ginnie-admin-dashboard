<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SystemUpdateStatusEnum;
use App\Http\Controllers\Controller;
use App\Jobs\RunSystemUpdateJob;
use App\Models\Setting;
use App\Models\SystemUpdate;
use App\Services\SystemUpdater;
use App\Traits\ChecksPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly SystemUpdater $updater) {}

    public function index(): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_VIEW()), 403, trans('labels.permission_denied'));
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id'), 'label' => __('labels.id')],
            ['data' => 'version', 'name' => 'version', 'title' => __('labels.version'), 'label' => __('labels.version')],
            ['data' => 'package_name', 'name' => 'package_name', 'title' => __('labels.package'), 'label' => __('labels.package')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'applied_by', 'name' => 'applied_by', 'title' => __('labels.applied_by'), 'label' => __('labels.applied_by')],
            ['data' => 'applied_at', 'name' => 'applied_at', 'title' => __('labels.applied_at'), 'label' => __('labels.applied_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'label' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $canUpdate = $this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_EDIT());
        $currentVersion = Setting::getCurrentVersion();

        $prepared = session('system_update_prepared');
        $preparedUpdate = $this->isAdminSuperAdmin(Auth::user())
            && ($prepared['expires'] ?? 0) > time()
            && isset($prepared['preview'], $prepared['token'])
            && is_file(storage_path('app/private/updates/prepared/'.$prepared['token'].'/package.zip'))
                ? $prepared['preview'] : null;

        return view('admin.system-updates.index', compact('columns', 'canUpdate', 'currentVersion', 'preparedUpdate'));
    }

    public function preview(Request $request): JsonResponse
    {
        abort_unless($this->isAdminSuperAdmin(Auth::user()), 403, __('labels.update_requires_super_admin'));
        $request->validate([
            'package' => [
                'required',
                'file',
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
                'max:157286400', // 150MB
            ],
            'expected_checksum' => ['nullable', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ]);
        $token = bin2hex(random_bytes(32));
        $directory = storage_path('app/private/updates/prepared/'.$token);
        File::ensureDirectoryExists($directory, 0700);
        try {
            $file = $request->file('package');
            $checksum = hash_file('sha256', $file->getRealPath());
            if ($expected = $request->input('expected_checksum')) {
                if (! hash_equals(strtolower($expected), $checksum)) {
                    throw new \RuntimeException(__('labels.update_checksum_mismatch'));
                }
            }
            File::copy($file->getRealPath(), $directory.'/package.zip');
            $manifest = $this->updater->inspectPackage($directory.'/package.zip', $directory.'/work');
            File::deleteDirectory($directory.'/work');
            if ($previous = $request->session()->get('system_update_prepared.token')) {
                File::deleteDirectory(storage_path('app/private/updates/prepared/'.$previous));
            }
            $preview = ['token' => $token, 'version' => $manifest['version'],
                'vendor_update' => $manifest['vendor_update'] ?? false,
                'database_update' => ! empty($manifest['run_migrations']) || ! empty($manifest['run_seeders']),
                'platform_warnings' => $manifest['platform_warnings'] ?? []];
            $request->session()->put('system_update_prepared', ['token' => $token, 'expires' => time() + 1800, 'checksum' => $checksum, 'preview' => $preview]);

            return response()->json($preview);
        } catch (\Throwable $error) {
            File::deleteDirectory($directory);

            return response()->json(['message' => $error->getMessage()], 422);
        }
    }

    /**
     * Accept a ZIP upload and dispatch the queued update job.
     * Returns JSON so the frontend can immediately start polling the row.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->isAdminSuperAdmin(Auth::user()), 403, __('labels.update_requires_super_admin'));
        if ($request->hasFile('package')) {
            return $this->preview($request);
        }
        $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'confirmed' => ['accepted']]);
        $prepared = $request->session()->get('system_update_prepared');
        if (! $prepared || $prepared['expires'] < time() || ! hash_equals($prepared['token'], $request->string('token')->toString())) {
            return response()->json(['message' => __('The prepared update expired. Upload the package again.')], 422);
        }
        $directory = storage_path('app/private/updates/prepared/'.$prepared['token']);
        try {
            if (! is_file($directory.'/package.zip') || ! hash_equals($prepared['checksum'], hash_file('sha256', $directory.'/package.zip'))) {
                throw new \RuntimeException('The prepared package changed. Upload it again.');
            }
            $update = $this->updater->prepareUpload(new UploadedFile($directory.'/package.zip', 'update.zip', 'application/zip', null, true), (int) Auth::id());
            $token = bin2hex(random_bytes(32));
            File::put(Storage::disk('local')->path(SystemUpdater::UPDATES_DIR.'/'.$update->id.'.token'), $token);
            $state = storage_path('app/private/updates/recovery/'.$token.'/state.json');
            File::ensureDirectoryExists(dirname($state), 0700);
            File::put($state, json_encode(['id' => $update->id, 'status' => 'pending', 'step' => 'queued', 'progress' => 0, 'log' => 'Update confirmed. Waiting for the queue worker.', 'heartbeat_at' => date('c')]));
            RunSystemUpdateJob::dispatch($update->id);
            $request->session()->forget('system_update_prepared');
            File::deleteDirectory($directory);

            return response()->json(['success' => true, 'update_id' => $update->id, 'recovery_token' => $token,
                'recovery_url' => asset('system-update-status.php'), 'data' => $this->serializeRow($update->fresh())]);
        } catch (\Throwable $error) {
            if (isset($update)) {
                $update->update(['status' => 'failed', 'failed_at' => now(), 'log' => $error->getMessage()]);
            }
            Log::error('System update dispatch failed: '.$error->getMessage());

            return response()->json(['message' => $error->getMessage()], 422);
        }
    }

    /**
     * Return the most recent update row. Drives "resume polling after page refresh".
     */
    public function latest(): JsonResponse
    {
        if (! $this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_VIEW())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->updater->reconcileStuckRuns();

        $last = SystemUpdate::orderByDesc('id')->first();
        if (! $last) {
            return response()->json(null);
        }

        return response()->json($this->serializeRow($last));
    }

    /**
     * Return a specific update row. Same self-heal pass as latest().
     */
    public function showLog(Request $request, SystemUpdate $update): JsonResponse
    {
        if (! $this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_VIEW())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($update->isStale()) {
            $this->updater->reconcileStuckRuns();
            $update->refresh();
        }

        return response()->json($this->serializeRow($update));
    }

    public function datatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SETTING_SYSTEM_VIEW()), 403, trans('labels.permission_denied'));

        $query = SystemUpdate::query()->with('appliedBy');

        $totalRecords = SystemUpdate::count();
        $filteredRecords = $totalRecords;

        if ($request->has('search') && ! empty($request->search['value'])) {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search) {
                $q->where('version', 'like', "%{$search}%")
                    ->orWhere('package_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
            $filteredRecords = (clone $query)->count();
        }

        if ($request->has('order')) {
            $orderColumn = $request->columns[$request->order[0]['column']]['data'] ?? 'id';
            $orderDirection = $request->order[0]['dir'] ?? 'desc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderByDesc('id');
        }

        if ($request->has('start') && $request->has('length')) {
            $query->skip((int) $request->start)->take((int) $request->length);
        }

        $data = $query->get()->map(function (SystemUpdate $item) {
            $statusValue = $item->status?->value ?? 'pending';
            $statusClass = match ($statusValue) {
                SystemUpdateStatusEnum::APPLIED->value => 'bg-success-lt',
                SystemUpdateStatusEnum::FAILED->value => 'bg-danger-lt',
                default => 'bg-secondary-lt',
            };
            $statusHtml = "<span class='badge {$statusClass}'>".ucfirst($statusValue).'</span>';
            $appliedBy = optional($item->appliedBy)->name ?? __('labels.system');
            $appliedAt = optional($item->applied_at ?? $item->failed_at)?->format('Y-m-d H:i');
            $action = '<a class="btn btn-sm btn-outline-primary" href="'
                .route('admin.system-updates.log', ['update' => $item->id])
                .'" target="_blank">'.e(__('labels.view_log')).'</a>';

            return [
                'id' => $item->id,
                'version' => e($item->version),
                'package_name' => e($item->package_name),
                'status' => $statusHtml,
                'applied_by' => e($appliedBy),
                'applied_at' => $appliedAt,
                'action' => $action,
            ];
        });

        return response()->json([
            'draw' => (int) $request->draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    private function isAdminSuperAdmin(mixed $user): bool
    {
        return $user !== null
            && ($user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) || $user->hasRole('super-admin'));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(SystemUpdate $row): array
    {
        return [
            'id' => $row->id,
            'version' => $row->version,
            'status' => $row->status?->value,
            'step' => $row->step?->value,
            'progress' => $row->progress ?? 0,
            'log' => $row->log,
            'heartbeat_at' => optional($row->heartbeat_at)->toDateTimeString(),
            'applied_at' => optional($row->applied_at)->toDateTimeString(),
            'failed_at' => optional($row->failed_at)->toDateTimeString(),
            'is_stale' => $row->isStale(),
        ];
    }
}
