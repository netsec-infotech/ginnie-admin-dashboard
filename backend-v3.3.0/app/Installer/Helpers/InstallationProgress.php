<?php

namespace App\Installer\Helpers;

use RuntimeException;

class InstallationProgress
{
    private readonly string $progressPath;

    private readonly string $lockPath;

    public function __construct(?string $progressPath = null, ?string $lockPath = null)
    {
        $this->progressPath = $progressPath ?? storage_path('app/installer-progress.json');
        $this->lockPath = $lockPath ?? storage_path('app/installer-progress.lock');
    }

    public function start(string $token): array
    {
        $state = $this->defaultState();
        $state['token'] = $token;
        $state['status'] = 'running';
        $state['phase'] = 'preflight';
        $state['message'] = 'Installation preflight started.';
        $state['started_at'] = now()->toIso8601String();
        $state = $this->appendLog($state, 'info', $state['message']);

        return $this->write($state);
    }

    public function advance(string $phase, int $completed, int $total, string $message): array
    {
        $state = $this->read();
        $state['status'] = 'running';
        $state['phase'] = $phase;
        $state['percentage'] = $this->percentageFor($phase, $completed, $total);
        $state['current'] = max(0, $completed);
        $state['total'] = max(0, $total);
        $state['message'] = $this->clean($message);
        $state = $this->appendLog($state, 'info', $state['message']);

        return $this->write($state);
    }

    public function completePhase(string $phase, string $message): array
    {
        return $this->advance($phase, 1, 1, $message);
    }

    public function fail(string $phase, string $message, string $reference): array
    {
        $state = $this->read();
        $state['status'] = 'failed';
        $state['phase'] = $phase;
        $state['message'] = $this->clean($message);
        $state['error_reference'] = $this->clean($reference);
        $state = $this->appendLog($state, 'error', $state['message'].' Reference: '.$state['error_reference']);

        return $this->write($state);
    }

    public function ready(string $message): array
    {
        $state = $this->read();
        $state['status'] = 'ready';
        $state['phase'] = 'finalization';
        $state['percentage'] = $this->phaseStart('finalization');
        $state['current'] = 0;
        $state['total'] = 1;
        $state['message'] = $this->clean($message);
        $state = $this->appendLog($state, 'success', $state['message']);

        return $this->write($state);
    }

    public function complete(string $message): array
    {
        $state = $this->read();
        $state['status'] = 'complete';
        $state['phase'] = 'complete';
        $state['percentage'] = 100;
        $state['current'] = 1;
        $state['total'] = 1;
        $state['message'] = $this->clean($message);
        $state['completed_at'] = now()->toIso8601String();
        $state = $this->appendLog($state, 'success', $state['message']);

        return $this->write($state);
    }

    public function snapshot(?string $token = null): array
    {
        $state = $this->read();

        if ($token !== null && ! hash_equals((string) $state['token'], $token)) {
            return $this->clientState($this->defaultState());
        }

        return $this->clientState($state);
    }

    public function isReady(string $token): bool
    {
        $state = $this->read();

        return hash_equals((string) $state['token'], $token) && $state['status'] === 'ready';
    }

    public function belongsTo(string $token): bool
    {
        $state = $this->read();

        return $token !== '' && hash_equals((string) $state['token'], $token);
    }

    public function acquireLock()
    {
        $this->ensureDirectory(dirname($this->lockPath));
        $handle = fopen($this->lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Installer lock could not be opened.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    public function releaseLock($handle): void
    {
        if (! is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function percentageFor(string $phase, int $completed, int $total): int
    {
        $weight = $this->weights()[$phase] ?? 0;
        $ratio = $total > 0 ? min(1, max(0, $completed / $total)) : 1;

        return min(99, $this->phaseStart($phase) + (int) floor($weight * $ratio));
    }

    private function phaseStart(string $phase): int
    {
        $percentage = 0;

        foreach ($this->weights() as $name => $weight) {
            if ($name === $phase) {
                break;
            }

            $percentage += $weight;
        }

        return min(99, $percentage);
    }

    private function weights(): array
    {
        $weights = (array) config('installer.progress.weights', []);
        $defaults = [
            'preflight' => 5,
            'migrations' => 60,
            'seeders' => 20,
            'administrator' => 10,
            'finalization' => 5,
        ];
        $weights = array_replace($defaults, array_map(fn ($weight) => max(0, (int) $weight), $weights));
        $total = array_sum($weights);

        if ($total !== 100) {
            return $defaults;
        }

        return $weights;
    }

    private function read(): array
    {
        if (! is_file($this->progressPath)) {
            return $this->defaultState();
        }

        $decoded = json_decode((string) file_get_contents($this->progressPath), true);

        return is_array($decoded) ? array_replace($this->defaultState(), $decoded) : $this->defaultState();
    }

    private function write(array $state): array
    {
        $this->ensureDirectory(dirname($this->progressPath));
        $state['updated_at'] = now()->toIso8601String();
        $temporaryPath = $this->progressPath.'.tmp.'.bin2hex(random_bytes(6));
        $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false || file_put_contents($temporaryPath, $encoded, LOCK_EX) === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('Installer progress could not be written.');
        }

        @chmod($temporaryPath, 0600);

        if (! rename($temporaryPath, $this->progressPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Installer progress could not be activated.');
        }

        return $state;
    }

    private function appendLog(array $state, string $level, string $message): array
    {
        $logs = is_array($state['logs'] ?? null) ? $state['logs'] : [];
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'level' => $level,
            'message' => $this->clean($message),
        ];
        $limit = max(20, (int) config('installer.progress.max_log_entries', 250));
        $state['logs'] = array_slice($logs, -$limit);

        return $state;
    }

    private function clientState(array $state): array
    {
        unset($state['token']);

        return $state;
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, 500);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Installer progress directory could not be created.');
        }
    }

    private function defaultState(): array
    {
        return [
            'token' => '',
            'status' => 'idle',
            'phase' => 'idle',
            'percentage' => 0,
            'current' => 0,
            'total' => 0,
            'message' => 'Ready to install.',
            'error_reference' => null,
            'logs' => [],
            'started_at' => null,
            'updated_at' => null,
            'completed_at' => null,
        ];
    }
}
