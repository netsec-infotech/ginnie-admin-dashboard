<?php

namespace App\Installer\Helpers;

use RuntimeException;

class InstalledFileManager
{
    /**
     * Create installed file.
     */
    public function create(): void
    {
        if (file_put_contents(storage_path('installed'), '', LOCK_EX) === false) {
            throw new RuntimeException('Installation marker could not be written.');
        }
    }

    /**
     * Update installed file.
     */
    public function update(): void
    {
        $this->create();
    }
}
