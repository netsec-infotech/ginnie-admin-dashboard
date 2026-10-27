<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Support\DemoMode;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReturnReasonController extends Controller
{
    /**
     * Display seller's custom return reasons.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $seller = $user?->seller();

        if (! $seller) {
            abort(403, 'Seller profile not found.');
        }

        // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id') ?? '#'],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title') ?? 'Title'],
            ['data' => 'otp_required', 'name' => 'otp_required', 'title' => __('labels.otp_required') ?? 'OTP Required'],
            ['data' => 'products', 'name' => 'products', 'title' => __('labels.assigned_products') ?? 'Assigned Products', 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status') ?? 'Status'],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.actions') ?? 'Actions', 'orderable' => false, 'searchable' => false],
        ];

        $products = Product::where('seller_id', $seller->id)
            ->where('status', 'active')
            ->select('id', 'title')
            ->get();

        return view('seller.return-reasons.index', compact('columns', 'products'));
    }

    /**
     * Get seller return reasons for DataTable.
     */
    public function datatable(Request $request): JsonResponse
    {
        $user = Auth::user();
        $seller = $user?->seller();

        if (! $seller) {
            return response()->json([
                'draw' => (int) $request->draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $query = ReturnReason::where('seller_id', $seller->id)->with('products:id,title');

        $totalRecords = ReturnReason::where('seller_id', $seller->id)->count();
        $filteredRecords = $totalRecords;

        // Search
        if ($request->has('search') && ! empty($request->search['value'])) {
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
            $columns = ['id', 'title', 'otp_required', 'products', 'status'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'id';
            if (in_array($orderColumn, ['id', 'title', 'otp_required', 'status'], true)) {
                $query->orderBy($orderColumn, $orderDirection);
            } else {
                $query->orderByDesc('id');
            }
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

            $assignedProductsHtml = $this->renderAssignedProducts($reason);

            $reasonJson = e(json_encode($reason));

            $actions = '
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-icon btn-outline-primary btn-sm me-1" 
                            data-reason=\''.$reasonJson.'\' 
                            onclick="editSellerReason(this)" title="Edit">
                        <i class="ti ti-edit fs-3"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-outline-danger btn-sm" 
                            onclick="deleteSellerReason('.$reason->id.')" title="Delete">
                        <i class="ti ti-trash fs-3"></i>
                    </button>
                </div>';

            return [
                'id' => '#'.$reason->id,
                'title' => e($reason->title),
                'otp_required' => $otpBadge,
                'products' => $assignedProductsHtml,
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

    private function renderAssignedProducts(ReturnReason $reason): string
    {
        if ($reason->products->isEmpty()) {
            return '<span class="text-muted small">None</span>';
        }

        $assignedProductsHtml = '';

        // Seller return reasons datatable: show five names, then a More badge for the rest.
        foreach ($reason->products->take(5) as $product) {
            $assignedProductsHtml .= '<span class="badge bg-blue-lt me-1 mb-1">'.e($product->getTranslation('title', 'en', false)).'</span>';
        }

        $remainingProducts = $reason->products->skip(5);
        if ($remainingProducts->isNotEmpty()) {
            $remainingTitles = $remainingProducts
                ->map(fn ($product) => $product->getTranslation('title', 'en', false))
                ->implode(', ');
            $assignedProductsHtml .= '<span class="badge bg-secondary-lt me-1 mb-1" title="'.e($remainingTitles).'" data-bs-toggle="tooltip">More (+'
                .$remainingProducts->count().')</span>';
        }

        return $assignedProductsHtml;
    }

    /**
     * Store a new custom return reason for the seller.
     */
    public function store(Request $request): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $user = Auth::user();
        $seller = $user?->seller();

        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, 'Seller profile not found.', [], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'proof_required' => 'nullable|boolean',
            'otp_required' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
        $reason = ReturnReason::create([
            'seller_id' => $seller->id,
            'title' => $request->input('title'),
            'proof_required' => $request->has('proof_required') ? $request->boolean('proof_required') : true,
            'otp_required' => $request->boolean('otp_required'),
            'status' => $request->input('status', 'active'),
        ]);

        if ($request->has('product_ids')) {
            $sellerProductIds = Product::where('seller_id', $seller->id)
                ->whereIn('id', $request->input('product_ids', []))
                ->pluck('id')
                ->toArray();
            $reason->products()->sync($sellerProductIds);
        }

        return ApiResponseType::sendJsonResponse(true, __('labels.saved_successfully') ?? 'Return reason created successfully.', $reason);
    }

    /**
     * Update an existing return reason.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $user = Auth::user();
        $seller = $user?->seller();

        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, 'Seller profile not found.', [], 403);
        }

        $reason = ReturnReason::where('seller_id', $seller->id)->findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'proof_required' => 'nullable|boolean',
            'otp_required' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        // Return Flow Fix: Handle mandatory proof independently of return reason configuration.
        $reason->update([
            'title' => $request->input('title'),
            'proof_required' => $request->has('proof_required') ? $request->boolean('proof_required') : true,
            'otp_required' => $request->boolean('otp_required'),
            'status' => $request->input('status', 'active'),
        ]);

        if ($request->has('product_ids')) {
            $sellerProductIds = Product::where('seller_id', $seller->id)
                ->whereIn('id', $request->input('product_ids', []))
                ->pluck('id')
                ->toArray();
            $reason->products()->sync($sellerProductIds);
        } else {
            $reason->products()->detach();
        }

        return ApiResponseType::sendJsonResponse(true, __('labels.updated_successfully') ?? 'Return reason updated successfully.', $reason);
    }

    /**
     * Delete a custom return reason.
     */
    public function destroy(int $id): JsonResponse
    {
        // Demo Mode Fix: Prevent action execution in demo mode
        if (DemoMode::isDemoUser(auth()->user())) {
            return ApiResponseType::sendJsonResponse(false, __('labels.demo_mode_message_placeholder') ?? 'This action is not allowed in demo mode.', [], 403);
        }

        $user = Auth::user();
        $seller = $user?->seller();

        if (! $seller) {
            return ApiResponseType::sendJsonResponse(false, 'Seller profile not found.', [], 403);
        }

        $reason = ReturnReason::where('seller_id', $seller->id)->findOrFail($id);
        $reason->products()->detach();
        $reason->delete();

        return ApiResponseType::sendJsonResponse(true, __('labels.deleted_successfully') ?? 'Return reason deleted successfully.');
    }
}
