<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Language\SyncLanguagePacksRequest;
use App\Services\LanguagePackSyncService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;

class LanguagePackSyncController extends Controller
{
    public function __invoke(SyncLanguagePacksRequest $request, LanguagePackSyncService $sync): JsonResponse|RedirectResponse
    {
        try {
            $report = $sync->sync($request->validated('surface'));
            $success = $report['totals']['failed'] === 0;
            $message = __($success ? 'labels.language_sync_completed' : 'labels.language_sync_partial');
        } catch (Throwable $exception) {
            report($exception);
            $report = null;
            $success = false;
            $knownMessages = array_map(fn (string $key): string => __('labels.'.$key), [
                'language_sync_busy', 'language_sync_tables_missing', 'language_sync_invalid_surface',
            ]);
            $message = in_array($exception->getMessage(), $knownMessages, true)
                ? $exception->getMessage() : __('labels.language_sync_failed');
        }
        if ($request->expectsJson()) {
            return ApiResponseType::sendJsonResponse($success, $message, $report, $success ? 200 : 422);
        }

        return redirect()->route('admin.languages.index')->with('language_pack_sync', compact('report', 'success', 'message'));
    }
}
