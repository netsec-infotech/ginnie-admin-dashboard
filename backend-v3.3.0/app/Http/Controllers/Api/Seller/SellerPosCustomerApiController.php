<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\QuickRegisterCustomerRequest;
use App\Http\Resources\Seller\Pos\PosCustomerResource;
use App\Services\PosCustomerService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Group('Seller POS')]
class SellerPosCustomerApiController extends Controller
{
    public function __construct(private readonly PosCustomerService $customerService)
    {
    }

    /**
     * Search POS customers.
     *
     * @return JsonResponse
     */
    #[QueryParameter('q', description: 'Search term (name, mobile, or email).', type: 'string', example: 'John')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 15)]
    public function search(Request $request): JsonResponse
    {
        try {
            $q       = trim((string) $request->input('q', ''));
            $perPage = (int) $request->input('per_page', 15);

            $results = $this->customerService->search($q, $perPage);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.pos_customers_fetched',
                data: [
                    'current_page' => $results->currentPage(),
                    'last_page'    => $results->lastPage(),
                    'per_page'     => $results->perPage(),
                    'total'        => $results->total(),
                    'customers'    => PosCustomerResource::collection($results),
                ]
            );
        } catch (Throwable $e) {
            Log::error('POS customer search error', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }

    /**
     * Quick-register a walk-in customer.
     *
     * @return JsonResponse
     */
    public function quickRegister(QuickRegisterCustomerRequest $request): JsonResponse
    {
        try {
            $result = $this->customerService->quickRegister($request->validated());

            if (!$result['success']) {
                $data = $result['data'] ?? [];
                if (isset($data['customer'])) {
                    $data['customer'] = new PosCustomerResource($data['customer']);
                }

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.customer_already_exists_did_you_mean_to_search',
                    data: $data,
                    status: 409
                );
            }

            $customer = $result['data']['customer'] ?? null;

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.customer_registered_successfully',
                data: [
                    'customer' => $customer ? new PosCustomerResource($customer) : null,
                ],
                status: 201
            );
        } catch (Throwable $e) {
            Log::error('POS customer quick-register error', ['error' => $e->getMessage()]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', null, 500);
        }
    }
}
