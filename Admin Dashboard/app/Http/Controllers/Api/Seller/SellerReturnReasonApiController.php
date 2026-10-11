<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnReasonResource;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

#[Group('Seller Return Reasons')]
class SellerReturnReasonApiController extends Controller
{
    /**
     * List Return Reasons for authenticated seller
     */
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search by title.', type: 'string', example: 'Damaged')]
    #[QueryParameter('include_global', description: 'Include active global admin reasons in listing.', type: 'boolean', default: false)]
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $perPage = (int) $request->input('per_page', 15);
            $q = $request->input('search');
            $includeGlobal = filter_var($request->input('include_global', false), FILTER_VALIDATE_BOOLEAN);

            $query = ReturnReason::query()
                ->with('products')
                ->where(function ($sub) use ($seller, $includeGlobal) {
                    $sub->where('seller_id', $seller->id);
                    if ($includeGlobal) {
                        $sub->orWhere(function ($g) {
                            $g->whereNull('seller_id')->where('status', 'active');
                        });
                    }
                })
                ->orderByDesc('id');

            if ($q) {
                $query->where('title', 'like', "%{$q}%");
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->transform(fn($reason) => new ReturnReasonResource($reason));

            $response = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'data' => $paginator->items(),
            ];

            return ApiResponseType::sendJsonResponse(true, __('labels.return_reasons_fetched_successfully') ?? 'Return reasons fetched successfully', $response);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.failed_to_fetch_return_reasons') ?? 'Failed to fetch return reasons', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show a single custom Return Reason owned by the seller
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $reason = ReturnReason::with('products')
                ->where('seller_id', $seller->id)
                ->findOrFail($id);

            return ApiResponseType::sendJsonResponse(true, __('labels.return_reason_fetched_successfully') ?? 'Return reason fetched successfully', new ReturnReasonResource($reason));
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.return_reason_not_found') ?? 'Return reason not found', null, 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Create a new custom Return Reason for the seller
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'proof_required' => 'nullable|boolean',
                'otp_required' => 'nullable|boolean',
                'status' => 'nullable|in:active,inactive',
                'product_ids' => 'nullable|array',
                'product_ids.*' => 'integer|exists:products,id',
            ]);

            if ($validator->fails()) {
                return ApiResponseType::sendJsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $validated = $validator->validated();

            // Validate that assigned products belong to the seller
            if (!empty($validated['product_ids'])) {
                $sellerProductIds = Product::where('seller_id', $seller->id)
                    ->whereIn('id', $validated['product_ids'])
                    ->pluck('id')
                    ->toArray();

                if (count($sellerProductIds) !== count($validated['product_ids'])) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? 'One or more selected products do not belong to your store', null, 403);
                }
            }

            $reason = ReturnReason::create([
                'seller_id' => $seller->id,
                'title' => trim($validated['title']),
                'proof_required' => (bool) ($validated['proof_required'] ?? false),
                'otp_required' => (bool) ($validated['otp_required'] ?? false),
                'status' => $validated['status'] ?? 'active',
            ]);

            if (isset($validated['product_ids'])) {
                $reason->products()->sync($validated['product_ids']);
            }

            $reason->load('products');

            return ApiResponseType::sendJsonResponse(true, __('labels.return_reason_created_successfully') ?? 'Return reason created successfully', new ReturnReasonResource($reason), 201);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Update an existing custom Return Reason owned by the seller
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $reason = ReturnReason::where('seller_id', $seller->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'proof_required' => 'nullable|boolean',
                'otp_required' => 'nullable|boolean',
                'status' => 'nullable|in:active,inactive',
                'product_ids' => 'nullable|array',
                'product_ids.*' => 'integer|exists:products,id',
            ]);

            if ($validator->fails()) {
                return ApiResponseType::sendJsonResponse(false, $validator->errors()->first(), $validator->errors()->toArray(), 422);
            }

            $validated = $validator->validated();

            if (isset($validated['product_ids'])) {
                $sellerProductIds = Product::where('seller_id', $seller->id)
                    ->whereIn('id', $validated['product_ids'])
                    ->pluck('id')
                    ->toArray();

                if (count($sellerProductIds) !== count($validated['product_ids'])) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? 'One or more selected products do not belong to your store', null, 403);
                }
            }

            $reason->update([
                'title' => trim($validated['title']),
                'proof_required' => (bool) ($validated['proof_required'] ?? false),
                'otp_required' => (bool) ($validated['otp_required'] ?? false),
                'status' => $validated['status'] ?? $reason->status,
            ]);

            if (array_key_exists('product_ids', $validated)) {
                $reason->products()->sync($validated['product_ids'] ?? []);
            }

            $reason->load('products');

            return ApiResponseType::sendJsonResponse(true, __('labels.return_reason_updated_successfully') ?? 'Return reason updated successfully', new ReturnReasonResource($reason));
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.return_reason_not_found') ?? 'Return reason not found', null, 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete a custom Return Reason owned by the seller
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $reason = ReturnReason::where('seller_id', $seller->id)->findOrFail($id);

            $reason->products()->detach();
            $reason->delete();

            return ApiResponseType::sendJsonResponse(true, __('labels.return_reason_deleted_successfully') ?? 'Return reason deleted successfully', []);
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.return_reason_not_found') ?? 'Return reason not found', null, 404);
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
