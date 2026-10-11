<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\SearchPosProductRequest;
use App\Http\Resources\Seller\Pos\PosProductResource;
use App\Models\Store;
use App\Services\PosProductCatalogService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group('Seller POS')]
class SellerPosProductApiController extends Controller
{
    public function __construct(private readonly PosProductCatalogService $catalog)
    {
    }

    /**
     * Search POS products in a store.
     *
     * @return JsonResponse
     */
    #[QueryParameter('store_id', description: 'Store ID to search in.', type: 'int', required: true, example: 1)]
    #[QueryParameter('q', description: 'Search term.', type: 'string', example: 'rice')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 20, example: 20)]
    #[QueryParameter('include_out_of_stock', description: 'Include out-of-stock items.', type: 'boolean', default: false, example: false)]
    public function search(SearchPosProductRequest $request): JsonResponse
    {
        try {
            $seller = $request->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', null, 404);
            }

            $store = Store::where('id', (int) $request->input('store_id'))
                ->where('seller_id', $seller->id)
                ->first();

            if (!$store) {
                return ApiResponseType::sendJsonResponse(false, 'labels.store_not_found', null, 404);
            }

            $results = $this->catalog->searchInStore(
                store: $store,
                q: trim((string) $request->input('q', '')),
                perPage: (int) $request->input('per_page', 20),
                includeOutOfStock: (bool) $request->boolean('include_out_of_stock', false),
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.pos_products_fetched',
                data: [
                    'current_page' => $results->currentPage(),
                    'last_page'    => $results->lastPage(),
                    'per_page'     => $results->perPage(),
                    'total'        => $results->total(),
                    'products'     => PosProductResource::collection($results->getCollection()),
                ]
            );
        } catch (Throwable $e) {
            Log::error('POS product search error', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }
}
