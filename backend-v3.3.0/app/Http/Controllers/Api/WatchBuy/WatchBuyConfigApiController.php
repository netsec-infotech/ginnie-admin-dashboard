<?php

namespace App\Http\Controllers\Api\WatchBuy;

use App\Http\Controllers\Controller;
use App\Http\Resources\WatchBuy\Customer\WatchBuyConfigResource;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class WatchBuyConfigApiController extends Controller
{
    public function __construct(
        private readonly WatchBuyService $service,
    ) {}

    public function __invoke(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'labels.watch_buy_configuration_retrieved',
            new WatchBuyConfigResource($this->service->config()),
        );
    }
}
