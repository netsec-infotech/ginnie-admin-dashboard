<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\AuthTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use AuthTrait;

    protected string $role = 'admin';

    public function loginAdmin(): View
    {
        return view('admin.auth.login');
    }

    public function check(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => $request->user() !== null,
        ]);
    }

    public function reloginMetadata(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => $request->user() !== null,
            'csrf_token' => csrf_token(),
            'login_url' => route('admin.login.post'),
        ]);
    }

    public function logout(Request $request): JsonResponse|Redirector|RedirectResponse
    {
        try {
            Auth::logout(); // Log the user out
            $request->session()->invalidate(); // Invalidate the session

            return redirect(route('admin.login'));

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.logout_failed', ['error' => $e->getMessage()]),
                'data' => [],
            ], 500);
        }
    }
}
