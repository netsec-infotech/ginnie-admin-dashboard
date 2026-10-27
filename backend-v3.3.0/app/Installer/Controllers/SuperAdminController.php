<?php

namespace App\Installer\Controllers;

use App\Installer\Helpers\Reply;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    /**
     * Show the Super Admin details form.
     *
     * @return View
     */
    public function form()
    {
        return view('vendor.installer.super_admin');
    }

    /**
     * Validate and store Super Admin details in session. Actual user creation
     * happens after migrations (final step).
     *
     * @return array
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'mobile' => 'nullable|string|max:20',
        ];

        $validated = $request->validate($rules);
        $passwordHash = Hash::make($validated['password']);

        session([
            'install_super_admin' => [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => $passwordHash,
                'mobile' => $validated['mobile'] ?? null,
            ],
        ]);

        try {
            $payload = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password_hash' => $passwordHash,
                'mobile' => $validated['mobile'] ?? null,
                'saved_at' => now()->toIso8601String(),
            ];
            $dir = storage_path('app');
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            File::put(storage_path('app/install_super_admin.json'), json_encode($payload));
        } catch (\Throwable $e) {
            Log::error('Installer: could not persist super admin details: '.$e->getMessage());
        }

        return Reply::redirect(route('LaravelInstaller::database'), 'Super admin details saved. Continue installation.');
    }
}
