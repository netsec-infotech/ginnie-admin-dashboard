<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRejectionReason;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnRejectionReasonController extends Controller
{
    /**
     * Display list of return rejection reasons.
     */
    public function index(Request $request): View
    {
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id') ?? '#'],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title') ?? 'Title'],
            ['data' => 'comment_required', 'name' => 'comment_required', 'title' => __('labels.comment_required') ?? 'Comment Required'],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status') ?? 'Status'],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.actions') ?? 'Actions', 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.return-rejection-reasons.index', compact('columns'));
    }

    /**
     * Get return rejection reasons for DataTable.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = ReturnRejectionReason::query();

        $totalRecords = ReturnRejectionReason::count();
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
            $columns = ['id', 'title', 'comment_required', 'status'];
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
            $commentBadge = $reason->comment_required
                ? '<span class="badge bg-success-lt">Yes</span>'
                : '<span class="badge bg-secondary-lt">No</span>';

            $statusBadge = $reason->status === 'active'
                ? '<span class="badge bg-success-lt">Active</span>'
                : '<span class="badge bg-warning-lt">Inactive</span>';

            $reasonJson = e(json_encode($reason));

            $actions = '
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-icon btn-outline-primary btn-sm me-1" 
                            data-reason=\'' . $reasonJson . '\' 
                            onclick="editRejectionReason(this)" title="Edit">
                        <i class="ti ti-edit fs-3"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-outline-danger btn-sm" 
                            onclick="deleteRejectionReason(' . $reason->id . ')" title="Delete">
                        <i class="ti ti-trash fs-3"></i>
                    </button>
                </div>';

            return [
                'id' => '#' . $reason->id,
                'title' => e($reason->title),
                'comment_required' => $commentBadge,
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
     * Store a new return rejection reason.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'comment_required' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $reason = ReturnRejectionReason::create([
            'title' => $request->input('title'),
            'comment_required' => $request->boolean('comment_required'),
            'status' => $request->input('status', 'active'),
        ]);

        return ApiResponseType::sendJsonResponse(true, __('messages.saved_successfully') ?? 'Rejection reason created successfully.', $reason);
    }

    /**
     * Update an existing return rejection reason.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $reason = ReturnRejectionReason::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'comment_required' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $reason->update([
            'title' => $request->input('title'),
            'comment_required' => $request->boolean('comment_required'),
            'status' => $request->input('status', 'active'),
        ]);

        return ApiResponseType::sendJsonResponse(true, __('messages.updated_successfully') ?? 'Rejection reason updated successfully.', $reason);
    }

    /**
     * Delete a return rejection reason.
     */
    public function destroy(int $id): JsonResponse
    {
        $reason = ReturnRejectionReason::findOrFail($id);
        $reason->delete();

        return ApiResponseType::sendJsonResponse(true, __('messages.deleted_successfully') ?? 'Rejection reason deleted successfully.');
    }
}
