<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\CommissionRun;
use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionCalculatorService
{
    public function calculate(int $month, int $year, string $status = 'provisional'): CommissionRun
    {
        $rates = $this->fixedRates();
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $status = in_array($status, ['provisional', 'final'], true) ? $status : 'provisional';

        return DB::transaction(function () use ($month, $year, $rates, $start, $end, $status): CommissionRun {
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

            $monthlySalesByAffiliate = $this->eligibleOrdersQuery($start, $end)
                ->select('affiliate_id', DB::raw('SUM(estimated_commission_base) as total_sales'))
                ->groupBy('affiliate_id')
                ->pluck('total_sales', 'affiliate_id');

            $orders = $this->eligibleOrdersQuery($start, $end)
                ->with(['affiliate.upline.upline.upline'])
                ->get();

            foreach ($orders as $order) {
                $seller = $order->affiliate;

                if (! $seller) {
                    continue;
                }

                $baseAmount = (float) $order->estimated_commission_base;
                $totalSales += $baseAmount;

                $totalCommission += $this->createEntry($run, $order->id, $seller, $seller, 'personal', null, $rates['personal_rate'], $baseAmount);

                $directDownlineIds = $seller->directDownlines()->pluck('id');
                $hasDirectDownlines = $directDownlineIds->isNotEmpty();

                if ($hasDirectDownlines) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller, $seller, 'manager_bonus', null, $rates['manager_bonus_rate'], $baseAmount);
                }

                $sellerMonthlySales = (float) ($monthlySalesByAffiliate[$seller->id] ?? 0);
                $directDownlineMonthlySales = $directDownlineIds->sum(
                    fn (int $downlineId): float => (float) ($monthlySalesByAffiliate[$downlineId] ?? 0)
                );
                $qualifiesForL1Split = $hasDirectDownlines
                    && $sellerMonthlySales >= 20000
                    && ($sellerMonthlySales + $directDownlineMonthlySales) >= 100000;

                if ($qualifiesForL1Split) {
                    $l1Amount = round($baseAmount * $rates['l1_rate'], 2);

                    $totalCommission += $this->createEntry(
                        $run,
                        $order->id,
                        $seller,
                        $seller,
                        'l1_split_seller',
                        1,
                        $rates['l1_rate'],
                        $baseAmount,
                        round($l1Amount * 0.70, 2)
                    );

                    if ($seller->upline) {
                        $totalCommission += $this->createEntry(
                            $run,
                            $order->id,
                            $seller->upline,
                            $seller,
                            'l1_split_upline',
                            1,
                            $rates['l1_rate'],
                            $baseAmount,
                            round($l1Amount * 0.30, 2)
                        );
                    }
                } elseif ($seller->upline) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller->upline, $seller, 'l1_overriding', 1, $rates['l1_rate'], $baseAmount);
                }

                if ($seller->upline?->upline) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller->upline->upline, $seller, 'l2_overriding', 2, $rates['l2_rate'], $baseAmount);
                }

                if ($seller->upline?->upline?->upline) {
                    $totalCommission += $this->createEntry($run, $order->id, $seller->upline->upline->upline, $seller, 'l3_overriding', 3, $rates['l3_rate'], $baseAmount);
                }
            }

            $run->update([
                'status' => $status,
                'total_sales' => round($totalSales, 2),
                'total_commission' => round($totalCommission, 2),
                'calculated_at' => now(),
            ]);

            return $run->refresh();
        });
    }

    private function fixedRates(): array
    {
        return [
            'personal_rate' => 0.10,
            'manager_bonus_rate' => 0.01,
            'l1_rate' => 0.01,
            'l2_rate' => 0.003,
            'l3_rate' => 0.002,
        ];
    }

    private function eligibleOrdersQuery(Carbon $start, Carbon $end)
    {
        return TiktokOrder::query()
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
        float $baseAmount,
        ?float $commissionAmount = null
    ): float {
        $commissionAmount ??= round($baseAmount * $rate, 2);

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
