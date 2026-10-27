<?php

namespace App\Installer\Controllers;

use App\Enums\DefaultSystemRolesEnum;
use App\Installer\Helpers\InstallationProgress;
use App\Installer\Helpers\InstalledFileManager;
use App\Models\User;
use App\Support\EnvWriter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FinalController extends Controller
{
    /**
     * Update installed file and display finished view.
     */
    public function finish(
        InstalledFileManager $fileManager,
        InstallationProgress $progress,
        Request $request
    ) {
        $token = (string) $request->session()->get('install_completion_ready', '');

        if ($token === '') {
            $token = (string) $request->session()->get('install_progress_token', '');
        }

        if ($token === '' || ! $progress->isReady($token)) {
            return redirect()->route('LaravelInstaller::database')->with('error', 'Installation has not completed successfully.');
        }

        try {
            $progress->advance('finalization', 0, 1, 'Running final installation verification.');
            $user = User::where('access_panel', 'admin')->first();

            if (! $user || ! $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                throw new RuntimeException('Verified super administrator is missing.');
            }

            $exitCode = Artisan::call('storage:link');

            if ($exitCode !== 0) {
                throw new RuntimeException("Storage link command exited with status {$exitCode}.");
            }

            $envPath = base_path('.env');

            if (! is_file($envPath)) {
                throw new RuntimeException('Environment file is missing.');
            }

            $envContent = file_get_contents($envPath);

            if ($envContent === false) {
                throw new RuntimeException('Environment file could not be read.');
            }

            $envContent = EnvWriter::stripKeys($envContent, ['SESSION_DRIVER']);
            EnvWriter::write($envPath, rtrim($envContent, "\n")."\nSESSION_DRIVER=database\n");
            $fileManager->update();
        } catch (Throwable $e) {
            $reference = 'INST-'.strtoupper(Str::random(10));
            Log::error("Installation finalization failed. Reference: {$reference}", ['exception' => $e]);
            $progress->fail('finalization', 'Installation finalization failed.', $reference);

            return redirect()->route('LaravelInstaller::database')->with(
                'error',
                "Installation finalization failed. Reference: {$reference}"
            );
        }

        $progress->complete('Installation completed successfully.');
        $details = [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
        ];
        $request->session()->forget([
            'install_super_admin',
            'install_completion_ready',
            'install_progress_token',
        ]);
        $adminPath = storage_path('app/install_super_admin.json');

        if (File::exists($adminPath)) {
            File::delete($adminPath);
        }

        return view('vendor.installer.finished', compact('user', 'details'));
    }
}
