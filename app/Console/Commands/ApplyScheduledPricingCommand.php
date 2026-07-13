<?php

namespace App\Console\Commands;

use App\Services\Pricing\ScheduledPricingService;
use Illuminate\Console\Command;

class ApplyScheduledPricingCommand extends Command
{
    protected $signature = 'pricing:apply-scheduled';

    protected $description = 'Apply scheduled product prices';

    public function handle(ScheduledPricingService $scheduledPricingService): int
    {
        $pendingPrices = $scheduledPricingService->getPendingPrices();

        if ($pendingPrices->isEmpty()) {
            $this->info('No scheduled prices to apply.');

            return self::SUCCESS;
        }

        $appliedCount = 0;

        foreach ($pendingPrices as $scheduledPrice) {
            $scheduledPricingService->apply($scheduledPrice);
            $appliedCount++;
        }

        $this->info("Applied {$appliedCount} scheduled price(s).");

        return self::SUCCESS;
    }
}
