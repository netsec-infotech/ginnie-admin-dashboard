<?php

namespace App\Installer\Controllers;

use App\Enums\DefaultSystemRolesEnum;
use App\Installer\Helpers\DatabaseManager;
use App\Installer\Helpers\InstallationProgress;
use App\Models\User;
use App\Services\LicenseActivationStore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseController extends Controller
{
    /**
     * @var DatabaseManager
     */
    private $databaseManager;

    public function __construct(
        DatabaseManager $databaseManager,
        private readonly InstallationProgress $progress,
        private readonly LicenseActivationStore $activationStore
    ) {
        $this->databaseManager = $databaseManager;
    }

    public function show()
    {
        if (empty($this->superAdminDetails())) {
            return redirect()->route('LaravelInstaller::superAdmin')->with('error', 'Super admin details missing.');
        }

        $token = $this->progressToken();
        $progress = $this->progress->snapshot($token);

        return view('vendor.installer.database', compact('progress'));
    }

    public function progress(Request $request)
    {
        $token = (string) $request->session()->get('install_progress_token', '');

        if (! $this->progress->belongsTo($token)) {
            return response()->json(['message' => 'Installation progress is not available.'], 404);
        }

        return response()->json($this->progress->snapshot($token));
    }

    /**
     * Migrate and seed the database.
     */
    public function database(Request $request)
    {
        set_time_limit(600);

        $details = $this->superAdminDetails();

        if (empty($details)) {
            return redirect()->route('LaravelInstaller::superAdmin')->with('error', 'Super admin details missing.');
        }
        $token = $this->progressToken();

        try {
            $lock = $this->progress->acquireLock();
        } catch (Throwable $e) {
            return $this->failureResponse($request, 'Installation could not acquire its process lock.');
        }

        if ($lock === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'running',
                    'message' => 'Installation is already running.',
                ], 409);
            }

            return redirect()->route('LaravelInstaller::database')->with('error', 'Installation is already running.');
        }

        $failurePhase = 'preflight';

        try {
            $this->progress->start($token);
            $response = $this->databaseManager->migrateAndSeed();

            if (($response['status'] ?? 'danger') !== 'success') {
                return $this->failureResponse(
                    $request,
                    $response['message'] ?? 'Database installation failed.',
                    $response['reference'] ?? $this->progress->snapshot($token)['error_reference'] ?? null
                );
            }

            $failurePhase = 'administrator';
            $this->progress->advance('administrator', 0, 1, 'Creating and verifying the super administrator.');
            $user = $this->createOrUpdateSuperAdmin($details);

            if (! $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                throw new RuntimeException('Super administrator role verification failed.');
            }

            $this->progress->completePhase('administrator', 'Super administrator created and verified.');

            $failurePhase = 'finalization';
            $activation = $this->activationStore->current();

            if (empty($activation['purchase_code']) || empty($activation['signature'])) {
                throw new RuntimeException('License activation is not available.');
            }

            $request->session()->put('install_completion_ready', $token);
            $this->progress->ready('Database and administrator setup completed. Running final verification.');

            $redirectUrl = route('LaravelInstaller::final');

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'ready',
                    'message' => 'Installation stages completed.',
                    'redirect_url' => $redirectUrl,
                ]);
            }

            return redirect()->to($redirectUrl);
        } catch (Throwable $e) {
            $reference = 'INST-'.strtoupper(Str::random(10));
            Log::error("Installation process failed. Reference: {$reference}", ['exception' => $e]);
            $this->progress->fail($failurePhase, 'Installation verification failed.', $reference);

            return $this->failureResponse($request, 'Installation verification failed.', $reference);
        } finally {
            $this->progress->releaseLock($lock);
        }
    }

    private function superAdminDetails(): ?array
    {
        $details = session('install_super_admin');

        if (is_array($details) && $details !== []) {
            return $details;
        }

        $path = storage_path('app/install_super_admin.json');

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    private function createOrUpdateSuperAdmin(array $details): User
    {
        $user = User::firstOrCreate(
            ['email' => $details['email']],
            [
                'name' => $details['name'] ?? 'super admin',
                'status' => 'active',
                'access_panel' => 'admin',
                'password' => $details['password_hash'] ?? bcrypt($details['password'] ?? ''),
                'mobile' => $details['mobile'] ?? null,
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->fill([
                'name' => $details['name'] ?? $user->name,
                'status' => 'active',
                'access_panel' => 'admin',
                'mobile' => $details['mobile'] ?? $user->mobile,
            ]);

            if (! empty($details['password_hash'])) {
                $user->password = $details['password_hash'];
            } elseif (! empty($details['password'])) {
                $user->password = bcrypt($details['password']);
            }

            $user->save();
        }

        if (! $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
            $user->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN());
        }

        return $user;
    }

    private function progressToken(): string
    {
        $token = (string) session('install_progress_token', '');

        if ($token === '') {
            $token = Str::random(64);
            session(['install_progress_token' => $token]);
        }

        return $token;
    }

    private function failureResponse(Request $request, string $message, ?string $reference = null)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'failed',
                'message' => $message,
                'reference' => $reference,
            ], 422);
        }

        return redirect()->route('LaravelInstaller::database')->with(
            'error',
            $reference ? "{$message} Reference: {$reference}" : $message
        );
    }
}
