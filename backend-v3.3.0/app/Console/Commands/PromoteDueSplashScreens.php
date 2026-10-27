<?php

namespace App\Console\Commands;

use App\Enums\SplashAppTypeEnum;
use App\Services\SplashScreenService;
use Illuminate\Console\Command;

class PromoteDueSplashScreens extends Command
{
    protected $signature = 'splash-screens:promote-due';

    protected $description = 'Promote staged splash screens whose activation time has passed';

    /**
     * Execute the console command.
     */
    public function handle(SplashScreenService $splashScreens): int
    {
        $promoted = 0;

        foreach (SplashAppTypeEnum::values() as $appType) {
            if ($splashScreens->promoteDueForApp((string) $appType)) {
                $promoted++;
                $this->line("Promoted due splash screen for {$appType}.");
            }
        }

        $this->info("Promoted {$promoted} due splash screen(s).");

        return self::SUCCESS;
    }
}
