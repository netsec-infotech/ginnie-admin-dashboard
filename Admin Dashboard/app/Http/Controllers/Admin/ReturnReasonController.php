<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnReason;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnReasonController extends Controller
{
    /**
     * Display list of global return reasons.
     */
    public function index(Request $request): View
    {
        // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id') ?? '#'],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title') ?? 'Title'],
            ['data' => 'otp_required', 'name' => 'otp_required', 'title' => __('labels.otp_required') ?? 'OTP Required'],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status') ?? 'Status'],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.actions') ?? 'Actions', 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.return-reasons.index', compact('columns'));
    }

    /**
     * Get global return reasons for DataTable.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = ReturnReason::global();

        $totalRecords = ReturnReason::global()->count();
        $filteredRecords = $totalRecords;

        // Search
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('status', 'like', "%{$searchValue}%");
            });
            $filteredRecords = $query->count();
        }

        // Order
        if ($request->has('order')) {
            $orderColumnIndex = $request->order[0]['column'] ?? 0;
            $orderDirection = $request->order[0]['dir'] ?? 'desc';
            // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
            $columns = ['id', 'title', 'otp_required', 'status'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'id';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderByDesc('id');
        }

        // Pagination
        if ($request->has('start') && $request->has('length')) {
            $query->skip((int) $request->start)->take((int) $request->length);
        }

        $reasons = $query->get();

        $data = $reasons->map(function ($reason) {
            $otpBadge = $reason->otp_required
                ? '<span class="badge bg-info-lt">Yes</span>'
                : '<span class="badge bg-secondary-lt">No</span>';

            $statusBadge = $reason->status === 'active'
                ? '<span class="badge bg-success-lt">Active</span>'
                : '<span class="badge bg-warning-lt">Inactive</span>';

            $reasonJson = e(json_encode($reason));

            $actions = '
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-icon btn-outline-primary btn-sm me-1" 
                            data-reason=\'' . $reasonJson . '\' 
                            onclick="editReason(this)" title="Edit">
                        <i class="ti ti-edit fs-3"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-outline-danger btn-sm" 
                            onclick="deleteReason(' . $reason->id . ')" title="Delete">
                        <i class="ti ti-trash fs-3"></i>
                    </button>
                </div>';

            return [
                'id' => '#' . $reason->id,
                'title' => e($reason->title),
                'otp_required' => $otpBadge,
                'status' => $statusBadge,
                'action' => $actions,
            ];
        });

        return response()->json([
            'draw' => (int) $request->draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Store a new global return reason.
     */
    public function store(Request $request): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (\App\Models\Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'proof_required' => 'nullable|boolean',
            'otp_required' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
        $reason = ReturnReason::create([
            'seller_id' => null,
            'title' => $request->input('title'),
            'proof_required' => $request->has('proof_required') ? $request->boolean('proof_required') : true,
            'otp_required' => $request->boolean('otp_required'),
            'status' => $request->input('status', 'active'),
        ]);

        return ApiResponseType::sendJsonResponse(true, __('messages.saved_successfully') ?? 'Return reason created successfully.', $reason);
    }

    /**
     * Update an existing return reason.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (\App\Models\Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $reason = ReturnReason::whereNull('seller_id')->findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'proof_required' => 'nullable|boolean',
            'otp_required' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
        $reason->update([
            'title' => $request->input('title'),
            'proof_required' => $request->has('proof_required') ? $request->boolean('proof_required') : true,
            'otp_required' => $request->boolean('otp_required'),
            'status' => $request->input('status', 'active'),
        ]);

        return ApiResponseType::sendJsonResponse(true, __('messages.updated_successfully') ?? 'Return reason updated successfully.', $reason);
    }

    /**
     * Delete a return reason.
     */
    public function destroy(int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (\App\Models\Setting::isDemoModeEnabled()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $reason = ReturnReason::whereNull('seller_id')->findOrFail($id);
        $reason->delete();

        return ApiResponseType::sendJsonResponse(true, __('messages.deleted_successfully') ?? 'Return reason deleted successfully.');
    }
}
