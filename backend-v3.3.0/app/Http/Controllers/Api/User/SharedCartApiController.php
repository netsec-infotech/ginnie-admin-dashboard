<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Cart\ImportSharedCartRequest;
use App\Http\Requests\User\Cart\PreviewSharedCartRequest;
use App\Http\Requests\User\Cart\ShareCartRequest;
use App\Http\Resources\User\SharedCartResource;
use App\Services\SharedCartService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

#[Group('Cart')]
class SharedCartApiController extends Controller
{
    public function __construct(private readonly SharedCartService $sharedCartService) {}

    public function store(ShareCartRequest $request): JsonResponse
    {
        $result = $this->sharedCartService->create(
            $request->user(),
            $request->validated('title'),
        );

        return $this->respond($result);
    }

    #[QueryParameter('latitude', description: 'Latitude used for delivery preview.', type: 'float', example: 23.1168454)]
    #[QueryParameter('longitude', description: 'Longitude used for delivery preview.', type: 'float', example: 70.0280567)]
    #[QueryParameter('address_id', description: 'Owned customer address; its coordinates take precedence.', type: 'integer', example: 1)]
    #[PathParameter('token', description: 'Opaque variable-length share token.', type: 'string')]
    public function show(PreviewSharedCartRequest $request, string $token): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->sharedCartService->preview(
            token: $token,
            user: Auth::guard('sanctum')->user(),
            latitude: isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            addressId: isset($validated['address_id']) ? (int) $validated['address_id'] : null,
        );

        if ($result['success']) {
            $result['data'] = new SharedCartResource($result['data']);
        }

        return $this->respond($result);
    }

    #[PathParameter('token', description: 'Opaque variable-length share token.', type: 'string')]
    public function import(ImportSharedCartRequest $request, string $token): JsonResponse
    {
        $result = $this->sharedCartService->import(
            $request->user(),
            $token,
            $request->validated('action'),
        );

        return $this->respond($result);
    }

    /** @param array<string, mixed> $result */
    private function respond(array $result): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'],
            $result['status'],
        );
    }
}
