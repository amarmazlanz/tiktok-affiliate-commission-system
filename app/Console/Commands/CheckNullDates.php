<?php

namespace App\Console\Commands;

use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use Illuminate\Console\Command;

class CheckNullDates extends Command
{
    protected $signature = 'orders:null-dates {username}';

    protected $description = 'Find Settled orders for a username where time_commission_paid is null (date parsing failed)';

    public function handle(): void
    {
        $username = $this->argument('username');

        $ids = TiktokAccount::query()
            ->where('username_normalized', 'like', "%{$username}%")
            ->pluck('affiliate_id');

        if ($ids->isEmpty()) {
            $this->warn("Tiada akaun dengan username '{$username}'.");
            return;
        }

        $orders = TiktokOrder::query()
            ->whereIn('affiliate_id', $ids)
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->whereNull('time_commission_paid')
            ->get(['order_id', 'creator_username', 'estimated_commission_base', 'raw_data']);

        $this->line('');
        $this->line("=== Orders SETTLED dengan time_commission_paid = NULL ('{$username}') ===");

        if ($orders->isEmpty()) {
            $this->info('Tiada. Semua orders ada tarikh commission paid.');
            return;
        }

        $this->table(
            ['Order ID', 'Creator', 'Est. Comm Base', 'Raw Time Commission Paid'],
            $orders->map(fn ($o) => [
                $o->order_id,
                $o->creator_username,
                'RM ' . number_format((float) $o->estimated_commission_base, 2),
                data_get(json_decode($o->raw_data, true), 'Time Commission Paid', '(tiada)'),
            ])
        );

        $this->warn('Total: RM ' . number_format($orders->sum(fn ($o) => (float) $o->estimated_commission_base), 2));
        $this->line('');
    }
}
