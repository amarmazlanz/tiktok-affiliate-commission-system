<?php

namespace App\Console\Commands;

use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

        // Part 1: No-upline orders (affiliate_id = NULL)
        $missing = TiktokOrder::query()
            ->whereNull('affiliate_id')
            ->where('order_status', 'Settled')
            ->where('creator_username_normalized', 'like', "%{$username}%")
            ->whereNotNull('time_commission_paid')
            ->whereBetween('time_commission_paid', [$start, $end])
            ->orderBy('time_commission_paid')
            ->get(['order_id', 'creator_username', 'estimated_commission_base', 'time_commission_paid']);

        $this->line('');
        $this->line('=== 1. NO-UPLINE (tidak linked ke mana-mana affiliate) ===');
        if ($missing->isEmpty()) {
            $this->info("Tiada.");
        } else {
            $this->table(
                ['Order ID', 'Creator Username', 'Est. Commission Base', 'Time Commission Paid'],
                $missing->map(fn ($o) => [
                    $o->order_id,
                    $o->creator_username,
                    'RM ' . number_format((float) $o->estimated_commission_base, 2),
                    $o->time_commission_paid?->format('d/m/Y H:i') ?? '-',
                ])
            );
            $this->warn('Total no-upline: RM ' . number_format($missing->sum(fn ($o) => (float) $o->estimated_commission_base), 2));
        }

        // Part 2: Linked orders but EXCLUDED from commission (zero base or date outside range)
        $affiliateIds = TiktokAccount::query()
            ->where('username_normalized', 'like', "%{$username}%")
            ->pluck('affiliate_id');

        if ($affiliateIds->isEmpty()) {
            $this->line('');
            $this->warn("Tiada TikTok account dengan username '{$username}' dalam sistem.");
            return;
        }

        $excluded = TiktokOrder::query()
            ->whereIn('affiliate_id', $affiliateIds)
            ->where('order_status', 'Settled')
            ->whereNotNull('time_commission_paid')
            ->whereBetween('time_commission_paid', [$start, $end])
            ->where(function ($q): void {
                $q->whereNull('estimated_commission_base')
                    ->orWhere('estimated_commission_base', '<=', 0);
            })
            ->get(['order_id', 'creator_username', 'estimated_commission_base', 'time_commission_paid']);

        $this->line('');
        $this->line('=== 2. LINKED tapi DIKECUALIKAN (est. commission base = 0 atau null) ===');
        if ($excluded->isEmpty()) {
            $this->info("Tiada.");
        } else {
            $this->table(
                ['Order ID', 'Creator Username', 'Est. Commission Base', 'Time Commission Paid'],
                $excluded->map(fn ($o) => [
                    $o->order_id,
                    $o->creator_username,
                    (string) $o->estimated_commission_base ?? 'NULL',
                    $o->time_commission_paid?->format('d/m/Y H:i') ?? '-',
                ])
            );
        }

        // Part 3: Linked orders in WRONG MONTH (possible date parsing issue)
        $wrongMonth = TiktokOrder::query()
            ->whereIn('affiliate_id', $affiliateIds)
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->whereNotNull('time_commission_paid')
            ->whereMonth('time_commission_paid', '!=', $month)
            ->whereYear('time_commission_paid', $year)
            ->get(['order_id', 'creator_username', 'estimated_commission_base', 'time_commission_paid']);

        $this->line('');
        $this->line("=== 3. LINKED tapi tarikh time_commission_paid BUKAN bulan {$month} (kemungkinan date parsing issue) ===");
        if ($wrongMonth->isEmpty()) {
            $this->info("Tiada.");
        } else {
            $this->table(
                ['Order ID', 'Creator Username', 'Est. Commission Base', 'Time Commission Paid (dalam sistem)'],
                $wrongMonth->map(fn ($o) => [
                    $o->order_id,
                    $o->creator_username,
                    'RM ' . number_format((float) $o->estimated_commission_base, 2),
                    $o->time_commission_paid?->format('d/m/Y H:i') ?? '-',
                ])
            );
            $this->warn('Total: RM ' . number_format($wrongMonth->sum(fn ($o) => (float) $o->estimated_commission_base), 2));
        }

        // Part 4: Total yang sistem kira untuk bulan ini
        $included = TiktokOrder::query()
            ->whereIn('affiliate_id', $affiliateIds)
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->whereNotNull('time_commission_paid')
            ->whereBetween('time_commission_paid', [$start, $end])
            ->sum('estimated_commission_base');

        $this->line('');
        $this->info('=== TOTAL yang sistem kira untuk ' . Carbon::create($year, $month)->format('F Y') . " ===");
        $this->info('RM ' . number_format((float) $included, 2));
        $this->line('');
    }
}
