<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalSearchRequest;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * Class GlobalSearchController
 * Handles Seller Panel global search endpoint (strictly tenant scoped).
 */
class GlobalSearchController extends Controller
{
    /**
     * Perform global search across seller menu, products, orders, and returns.
     */
    public function search(GlobalSearchRequest $request, GlobalSearchService $searchService): JsonResponse
    {
        $query = $request->getSearchQuery();
        $user = $request->user();

        $data = $query === ''
            ? $searchService->preload('seller', $user)
            : $searchService->search($query, 'seller', $user);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
