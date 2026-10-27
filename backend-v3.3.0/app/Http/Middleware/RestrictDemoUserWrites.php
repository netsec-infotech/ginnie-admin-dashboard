<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use App\Types\Api\ApiResponseType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoUserWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && DemoMode::isDemoUser($request->user())) {
            $message = __('labels.demo_action_restricted');

            if ($request->expectsJson()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: $message,
                    data: [],
                    status: Response::HTTP_FORBIDDEN,
                );
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
