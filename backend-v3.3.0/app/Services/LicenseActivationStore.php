<?php

namespace App\Services;

use App\Support\EnvWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LicenseActivationStore
{
    private readonly string $activationFilePath;

    private readonly string $environmentFilePath;

    public function __construct(?string $activationFilePath = null, ?string $environmentFilePath = null)
    {
        $this->activationFilePath = $activationFilePath ?? storage_path('app/license.json');
        $this->environmentFilePath = $environmentFilePath ?? base_path('.env');
    }

    public function current(): array
    {
        $defaults = [
            'purchase_code' => (string) config('app.license_purchase', ''),
            'domain_url' => (string) config('app.license_domain', ''),
            'token' => (string) config('app.license_token', ''),
            'signature' => (string) config('app.license_signature', ''),
            'mode' => (string) config('app.license_mode', 'remote'),
            'last_validated_at' => config('app.license_last_validated_at'),
        ];
        $stored = $this->readStoredActivation();

        if ($stored !== []) {
            return array_replace($defaults, $stored);
        }

        return $defaults;
    }

    public function persist(
        string $purchaseCode,
        string $domainUrl,
        string $token,
        string $mode,
        ?Carbon $validatedAt = null
    ): array {
        $signature = LicenseValidator::signature($purchaseCode, $domainUrl, $token);
        $activation = [
            'purchase_code' => $purchaseCode,
            'domain_url' => $domainUrl,
            'token' => $token,
            'signature' => $signature,
            'mode' => $mode,
            'last_validated_at' => $validatedAt?->toIso8601String(),
        ];

        $this->writeStoredActivation($activation);

        try {
            $this->writeEnvironment($activation);
        } catch (\Throwable $e) {
            Log::warning('License environment mirror could not be updated: '.$e->getMessage());
        }

        $this->applyRuntimeConfig($activation);

        return $activation;
    }

    public function persistOffline(string $purchaseCode, string $domainUrl): array
    {
        return $this->persist(
            $purchaseCode,
            $domainUrl,
            'offline-pending-validation',
            'offline'
        );
    }

    public function clear(): void
    {
        $activation = [
            'purchase_code' => '',
            'domain_url' => '',
            'token' => '',
            'signature' => '',
            'mode' => 'remote',
            'last_validated_at' => null,
        ];
        $contents = '';

        if (file_exists($this->environmentFilePath)) {
            $contents = file_get_contents($this->environmentFilePath);

            if ($contents === false) {
                throw new \RuntimeException('License environment file could not be read.');
            }
        }

        $contents = EnvWriter::stripKeys($contents, [
            'LICENSE_PURCHASE_CODE',
            'LICENSE_DOMAIN_URL',
            'LICENSE_TOKEN',
            'LICENSE_SIGNATURE',
            'LICENSE_MODE',
            'LICENSE_LAST_VALIDATED_AT',
        ]);
        EnvWriter::write($this->environmentFilePath, rtrim($contents, "\n")."\n");
        $this->writeStoredActivation($activation);

        if (File::exists($this->activationPath().'.backup')
            && ! File::delete($this->activationPath().'.backup')) {
            throw new \RuntimeException('Stored license activation backup could not be removed.');
        }

        $this->applyRuntimeConfig($activation);
    }

    private function readStoredActivation(): array
    {
        $path = $this->activationPath();

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeStoredActivation(array $activation): void
    {
        $directory = dirname($this->activationPath());

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        EnvWriter::write(
            $this->activationPath(),
            json_encode($activation, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL
        );
        @chmod($this->activationPath(), 0600);
        @chmod($this->activationPath().'.backup', 0600);
    }

    private function writeEnvironment(array $activation): void
    {
        $path = $this->environmentFilePath;

        if (! file_exists($path)) {
            touch($path);
        }

        $contents = file_get_contents($path) ?: '';
        $contents = EnvWriter::stripKeys($contents, [
            'LICENSE_PURCHASE_CODE',
            'LICENSE_DOMAIN_URL',
            'LICENSE_TOKEN',
            'LICENSE_SIGNATURE',
            'LICENSE_MODE',
            'LICENSE_LAST_VALIDATED_AT',
        ]);

        $block = 'LICENSE_PURCHASE_CODE='.EnvWriter::value($activation['purchase_code'])."\n"
            .'LICENSE_DOMAIN_URL='.EnvWriter::value($activation['domain_url'])."\n"
            .'LICENSE_TOKEN='.EnvWriter::value($activation['token'])."\n"
            .'LICENSE_SIGNATURE='.EnvWriter::value($activation['signature'])."\n"
            .'LICENSE_MODE='.EnvWriter::value($activation['mode'])."\n"
            .'LICENSE_LAST_VALIDATED_AT='.EnvWriter::value((string) $activation['last_validated_at'])."\n";

        EnvWriter::write($path, rtrim($contents, "\n")."\n".$block);
    }

    private function applyRuntimeConfig(array $activation): void
    {
        config([
            'app.license_purchase' => $activation['purchase_code'],
            'app.license_domain' => $activation['domain_url'],
            'app.license_token' => $activation['token'],
            'app.license_signature' => $activation['signature'],
            'app.license_mode' => $activation['mode'],
            'app.license_last_validated_at' => $activation['last_validated_at'],
        ]);
    }

    private function activationPath(): string
    {
        return $this->activationFilePath;
    }
}
