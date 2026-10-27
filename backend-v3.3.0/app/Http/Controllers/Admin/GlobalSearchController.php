<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GlobalSearchRequest;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * Class GlobalSearchController
 * Handles Admin Panel global search endpoint.
 */
class GlobalSearchController extends Controller
{
    /**
     * Perform global search across menu, products, orders, returns, customers, and sellers.
     */
    public function search(GlobalSearchRequest $request, GlobalSearchService $searchService): JsonResponse
    {
        $query = $request->getSearchQuery();
        $user = $request->user();

        $data = $query === ''
            ? $searchService->preload('admin', $user)
            : $searchService->search($query, 'admin', $user);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
