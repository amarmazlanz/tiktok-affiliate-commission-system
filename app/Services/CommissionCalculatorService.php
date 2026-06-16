<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\CommissionRateSetting;
use App\Models\CommissionRun;
use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionCalculatorService
{
    public function calculate(int $month, int $year): CommissionRun
    {
        $rates = CommissionRateSetting::ratesFor($month, $year);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($month, $year, $rates, $start, $end): CommissionRun {
            $run = CommissionRun::query()->firstOrCreate([
                'month' => $month,
                'year' => $year,
            ], [
                'status' => 'draft',
                'total_sales' => 0,
                'total_commission' => 0,
            ]);

            $run->commissionEntries()->delete();
            $run->update([
                'status' => 'processing',
                'total_sales' => 0,
                'total_commission' => 0,
                'calculated_at' => null,
            ]);

            $totalSales = 0.0;
            $totalCommission = 0.0;

            $orders = TiktokOrder::query()
                ->with(['affiliate.upline.upline.upline'])
                ->where('order_status', 'Settled')
                ->where('estimated_commission_base', '>', 0)
                ->whereNotNull('affiliate_id')
                ->where(function ($query) use ($start, $end): void {
                    $query
                        ->whereBetween('time_commission_paid', [$start, $end])
                        ->orWhere(function ($query) use ($start, $end): void {
                            $query
                                ->whereNull('time_commission_paid')
                                ->whereBetween('time_created', [$start, $end]);
                        });
                })
                ->get();

            foreach ($orders as $order) {
                $seller = $order->affiliate;

                if (! $seller) {
                    continue;
                }

                $baseAmount = (float) $order->estimated_commission_base;
                $totalSales += $baseAmount;

                $totalCommission += $this->createEntry($run, $order->id, $seller, $seller, 'personal', null, $rates['personal_rate'], $baseAmount);

                if ($seller->directDownlines()->exists()) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller, $seller, 'manager_bonus', null, $rates['manager_bonus_rate'], $baseAmount);
                } elseif ($seller->upline) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller->upline, $seller, 'overriding', 1, $rates['l1_rate'], $baseAmount);
                }

                if ($seller->upline?->upline) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller->upline->upline, $seller, 'overriding', 2, $rates['l2_rate'], $baseAmount);
                }

                if ($seller->upline?->upline?->upline) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller->upline->upline->upline, $seller, 'overriding', 3, $rates['l3_rate'], $baseAmount);
                }
            }

            $run->update([
                'status' => 'completed',
                'total_sales' => round($totalSales, 2),
                'total_commission' => round($totalCommission, 2),
                'calculated_at' => now(),
            ]);

            return $run->refresh();
        });
    }

    private function createEntry(
        CommissionRun $run,
        int $orderId,
        Affiliate $receiver,
        Affiliate $source,
        string $type,
        ?int $level,
        float $rate,
        float $baseAmount
    ): float {
        $commissionAmount = round($baseAmount * $rate, 2);

        $run->commissionEntries()->create([
            'receiver_affiliate_id' => $receiver->id,
            'source_affiliate_id' => $source->id,
            'tiktok_order_id' => $orderId,
            'commission_type' => $type,
            'level' => $level,
            'rate' => $rate,
            'base_amount' => round($baseAmount, 2),
            'commission_amount' => $commissionAmount,
        ]);

        return $commissionAmount;
    }
}
