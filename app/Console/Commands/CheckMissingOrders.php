<?php

namespace App\Console\Commands;

use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckMissingOrders extends Command
{
    protected $signature = 'orders:missing {username} {month} {year}';

    protected $description = 'Find Settled orders for a username that ended up as no-upline (not linked to any affiliate)';

    public function handle(): void
    {
        $username = $this->argument('username');
        $month = (int) $this->argument('month');
        $year = (int) $this->argument('year');

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $orders = TiktokOrder::query()
            ->whereNull('affiliate_id')
            ->where('order_status', 'Settled')
            ->where('creator_username_normalized', 'like', "%{$username}%")
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('time_commission_paid', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end): void {
                        $q->whereNull('time_commission_paid')
                            ->whereBetween('time_created', [$start, $end]);
                    });
            })
            ->orderBy('time_commission_paid')
            ->get(['order_id', 'creator_username', 'estimated_commission_base', 'time_commission_paid']);

        if ($orders->isEmpty()) {
            $this->info("No missing orders found for '{$username}' in {$month}/{$year}.");
            return;
        }

        $this->table(
            ['Order ID', 'Creator Username', 'Est. Commission Base', 'Time Commission Paid'],
            $orders->map(fn ($o) => [
                $o->order_id,
                $o->creator_username,
                'RM ' . number_format((float) $o->estimated_commission_base, 2),
                $o->time_commission_paid?->format('d/m/Y H:i') ?? '-',
            ])
        );

        $total = $orders->sum(fn ($o) => (float) $o->estimated_commission_base);
        $this->line('');
        $this->info("Total missing: RM " . number_format($total, 2) . " ({$orders->count()} orders)");
    }
}
