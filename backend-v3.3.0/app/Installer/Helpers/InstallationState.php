<?php

namespace App\Installer\Helpers;

class InstallationState
{
    private readonly string $markerPath;

    public function __construct(?string $markerPath = null)
    {
        $this->markerPath = $markerPath ?? storage_path('installed');
    }

    public function isInstalled(): bool
    {
        return file_exists($this->markerPath);
    }
}
