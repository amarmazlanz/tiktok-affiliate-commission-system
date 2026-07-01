<?php

namespace App\Services;

use App\Models\CommissionRun;
use App\Models\TiktokOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommissionCalculatorService
{
    private const ORDER_CHUNK_SIZE = 1000;
    private const ENTRY_INSERT_CHUNK_SIZE = 1000;

    public function calculate(int $month, int $year, string $status = 'provisional'): CommissionRun
    {
        $rates = $this->fixedRates();
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $status = in_array($status, ['provisional', 'final'], true) ? $status : 'provisional';

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $run = CommissionRun::query()->firstOrCreate([
            'month' => $month,
            'year' => $year,
        ], [
            'status' => 'draft',
            'total_sales' => 0,
            'total_commission' => 0,
        ]);

        try {
            return DB::transaction(function () use ($run, $rates, $start, $end, $status): CommissionRun {
                $run->commissionEntries()->delete();
                $run->update([
                    'status' => 'processing',
                    'total_sales' => 0,
                    'total_commission' => 0,
                    'calculated_at' => null,
                ]);

                $affiliateData = $this->affiliateData();
                $monthlySalesByAffiliate = $this->monthlySalesByAffiliate($start, $end);
                $directDownlineSalesByAffiliate = $this->directDownlineSalesByAffiliate($monthlySalesByAffiliate, $affiliateData['uplineByAffiliate']);

                $totals = [
                    'sales' => 0.0,
                    'commission' => 0.0,
                ];

                $this->eligibleOrdersQuery($start, $end)
                    ->select(['id', 'affiliate_id', 'estimated_commission_base'])
                    ->orderBy('id')
                    ->chunkById(self::ORDER_CHUNK_SIZE, function ($orders) use ($run, $rates, $affiliateData, $monthlySalesByAffiliate, $directDownlineSalesByAffiliate, &$totals): void {
                        $entries = [];
                        $now = now();

                        foreach ($orders as $order) {
                            $sellerId = (int) $order->affiliate_id;

                            if (! array_key_exists($sellerId, $affiliateData['uplineByAffiliate'])) {
                                continue;
                            }

                            $baseAmount = round((float) $order->estimated_commission_base, 2);

                            if ($baseAmount <= 0) {
                                continue;
                            }

                            $totals['sales'] += $baseAmount;

                            $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $sellerId, $sellerId, 'personal', null, $rates['personal_rate'], $baseAmount, null, $now);

                            $hasDirectDownlines = ($affiliateData['directDownlineCountByAffiliate'][$sellerId] ?? 0) > 0;

                            if ($hasDirectDownlines) {
                                $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $sellerId, $sellerId, 'manager_bonus', null, $rates['manager_bonus_rate'], $baseAmount, null, $now);
                            }

                            $uplineId = $affiliateData['uplineByAffiliate'][$sellerId] ?? null;
                            $secondUplineId = $uplineId ? ($affiliateData['uplineByAffiliate'][$uplineId] ?? null) : null;
                            $thirdUplineId = $secondUplineId ? ($affiliateData['uplineByAffiliate'][$secondUplineId] ?? null) : null;

                            $sellerMonthlySales = (float) ($monthlySalesByAffiliate[$sellerId] ?? 0);
                            $directDownlineMonthlySales = (float) ($directDownlineSalesByAffiliate[$sellerId] ?? 0);
                            $qualifiesForL1Split = $hasDirectDownlines
                                && $sellerMonthlySales >= 20000
                                && ($sellerMonthlySales + $directDownlineMonthlySales) >= 100000;

                            if ($qualifiesForL1Split) {
                                $l1Amount = round($baseAmount * $rates['l1_rate'], 2);
                                $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $sellerId, $sellerId, 'l1_split_seller', 1, $rates['l1_rate'], $baseAmount, round($l1Amount * 0.70, 2), $now);

                                if ($uplineId) {
                                    $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $uplineId, $sellerId, 'l1_split_upline', 1, $rates['l1_rate'], $baseAmount, round($l1Amount * 0.30, 2), $now);
                                }
                            } elseif ($uplineId) {
                                $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $uplineId, $sellerId, 'l1_overriding', 1, $rates['l1_rate'], $baseAmount, null, $now);
                            }

                            if ($secondUplineId) {
                                $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $secondUplineId, $sellerId, 'l2_overriding', 2, $rates['l2_rate'], $baseAmount, null, $now);
                            }

                            if ($thirdUplineId) {
                                $this->queueEntry($entries, $totals, $run->id, (int) $order->id, $thirdUplineId, $sellerId, 'l3_overriding', 3, $rates['l3_rate'], $baseAmount, null, $now);
                            }
                        }

                        $this->insertEntries($entries);
                    });

                $run->update([
                    'status' => $status,
                    'total_sales' => round($totals['sales'], 2),
                    'total_commission' => round($totals['commission'], 2),
                    'calculated_at' => now(),
                ]);

                return $run->refresh();
            });
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed']);

            throw $exception;
        }
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

    private function affiliateData(): array
    {
        $affiliates = DB::table('affiliates')
            ->select(['id', 'upline_id'])
            ->get();

        $uplineByAffiliate = [];
        $directDownlineCountByAffiliate = [];

        foreach ($affiliates as $affiliate) {
            $affiliateId = (int) $affiliate->id;
            $uplineId = $affiliate->upline_id ? (int) $affiliate->upline_id : null;

            $uplineByAffiliate[$affiliateId] = $uplineId;

            if ($uplineId) {
                $directDownlineCountByAffiliate[$uplineId] = ($directDownlineCountByAffiliate[$uplineId] ?? 0) + 1;
            }
        }

        return [
            'uplineByAffiliate' => $uplineByAffiliate,
            'directDownlineCountByAffiliate' => $directDownlineCountByAffiliate,
        ];
    }

    private function monthlySalesByAffiliate(Carbon $start, Carbon $end): array
    {
        return $this->eligibleOrdersQuery($start, $end)
            ->select('affiliate_id', DB::raw('SUM(estimated_commission_base) as total_sales'))
            ->groupBy('affiliate_id')
            ->pluck('total_sales', 'affiliate_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    private function directDownlineSalesByAffiliate(array $monthlySalesByAffiliate, array $uplineByAffiliate): array
    {
        $sales = [];

        foreach ($monthlySalesByAffiliate as $affiliateId => $totalSales) {
            $uplineId = $uplineByAffiliate[(int) $affiliateId] ?? null;

            if (! $uplineId) {
                continue;
            }

            $sales[$uplineId] = ($sales[$uplineId] ?? 0) + (float) $totalSales;
        }

        return $sales;
    }

    private function queueEntry(
        array &$entries,
        array &$totals,
        int $runId,
        int $orderId,
        int $receiverAffiliateId,
        int $sourceAffiliateId,
        string $type,
        ?int $level,
        float $rate,
        float $baseAmount,
        ?float $commissionAmount,
        Carbon $now
    ): void {
        $commissionAmount ??= round($baseAmount * $rate, 2);
        $commissionAmount = round($commissionAmount, 2);
        $totals['commission'] += $commissionAmount;

        $entries[] = [
            'commission_run_id' => $runId,
            'receiver_affiliate_id' => $receiverAffiliateId,
            'source_affiliate_id' => $sourceAffiliateId,
            'tiktok_order_id' => $orderId,
            'commission_type' => $type,
            'level' => $level,
            'rate' => $rate,
            'base_amount' => $baseAmount,
            'commission_amount' => $commissionAmount,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function insertEntries(array $entries): void
    {
        foreach (array_chunk($entries, self::ENTRY_INSERT_CHUNK_SIZE) as $chunk) {
            DB::table('commission_entries')->insert($chunk);
        }
    }
}
