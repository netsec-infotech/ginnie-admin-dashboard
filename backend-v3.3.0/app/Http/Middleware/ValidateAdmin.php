<?php

namespace App\Http\Middleware;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (! $user) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Unauthenticated.'),
                    'data' => [],
                ], 401);
            }

            return redirect()->guest(route('admin.login'));
        }
        if ($user->getDefaultGuardName() === GuardNameEnum::ADMIN->value || $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value)) {
            return $next($request);
        }

        // If user doesn't have admin access, log them out and redirect to login
        Auth::logout();

        return redirect()->guest(route('admin.login'))->with('error', 'You do not have permission to access the admin panel.');
    }
}
