<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Services\BadgeService;
use Illuminate\Console\Command;

class CheckAffiliateBadges extends Command
{
    protected $signature   = 'badges:check';
    protected $description = 'Award new milestone badges to qualifying affiliates';

    public function handle(BadgeService $badges): void
    {
        $affiliates = Affiliate::all();
        $total      = 0;

        foreach ($affiliates as $affiliate) {
            $total += $badges->check($affiliate);
        }

        $this->info("Checked {$affiliates->count()} affiliates, awarded {$total} new badge(s).");
    }
}
