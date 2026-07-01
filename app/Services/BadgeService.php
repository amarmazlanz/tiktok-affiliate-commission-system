<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    public function definitions(): array
    {
        return [
            'first_sale' => [
                'label' => 'Jualan Pertama',
                'icon'  => '🎉',
                'desc'  => 'Berjaya membuat jualan pertama!',
                'css'   => 'badge-green',
            ],
            'rm10k_club' => [
                'label' => 'RM10K Club',
                'icon'  => '💰',
                'desc'  => 'Capai RM10,000 jualan peribadi sepanjang masa.',
                'css'   => 'badge-blue',
            ],
            'rm50k_club' => [
                'label' => 'RM50K Club',
                'icon'  => '🌟',
                'desc'  => 'Capai RM50,000 jualan peribadi sepanjang masa.',
                'css'   => 'badge-amber',
            ],
            'rm100k_club' => [
                'label' => 'RM100K Club',
                'icon'  => '👑',
                'desc'  => 'Capai RM100,000 jualan peribadi sepanjang masa.',
                'css'   => 'badge-red',
            ],
            'top_recruiter' => [
                'label' => 'Top Recruiter',
                'icon'  => '🤝',
                'desc'  => 'Ada 3+ ahli langsung yang buat jualan dalam satu bulan.',
                'css'   => 'badge-teal',
            ],
            'hat_trick' => [
                'label' => 'Hat Trick',
                'icon'  => '🎯',
                'desc'  => 'Buat jualan dalam 3 bulan berturut-turut.',
                'css'   => 'badge-blue',
            ],
        ];
    }

    public function check(Affiliate $affiliate): int
    {
        $awarded = 0;
        $earned  = AffiliateBadge::where('affiliate_id', $affiliate->id)->pluck('earned_at', 'badge_key');

        $lifetimeSales = (float) DB::table('commission_entries')
            ->where('source_affiliate_id', $affiliate->id)
            ->where('commission_type', 'personal')
            ->sum('base_amount');

        if (! isset($earned['first_sale']) && $lifetimeSales > 0) {
            $this->award($affiliate->id, 'first_sale');
            $awarded++;
        }

        foreach (['rm10k_club' => 10000, 'rm50k_club' => 50000, 'rm100k_club' => 100000] as $key => $threshold) {
            if (! isset($earned[$key]) && $lifetimeSales >= $threshold) {
                $this->award($affiliate->id, $key, ['lifetime_sales' => $lifetimeSales]);
                $awarded++;
            }
        }

        if (! isset($earned['top_recruiter'])) {
            $latestRun = DB::table('commission_runs')->orderByDesc('year')->orderByDesc('month')->first();
            if ($latestRun) {
                $directIds = DB::table('affiliates')->where('upline_id', $affiliate->id)->pluck('id');
                if ($directIds->isNotEmpty()) {
                    $activeDownlines = DB::table('commission_entries')
                        ->where('commission_run_id', $latestRun->id)
                        ->where('commission_type', 'personal')
                        ->whereIn('source_affiliate_id', $directIds)
                        ->distinct('source_affiliate_id')
                        ->count('source_affiliate_id');
                    if ($activeDownlines >= 3) {
                        $this->award($affiliate->id, 'top_recruiter', ['active_downlines' => $activeDownlines]);
                        $awarded++;
                    }
                }
            }
        }

        if (! isset($earned['hat_trick'])) {
            $months = DB::table('commission_entries')
                ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
                ->where('commission_entries.source_affiliate_id', $affiliate->id)
                ->where('commission_entries.commission_type', 'personal')
                ->selectRaw('commission_runs.year, commission_runs.month')
                ->distinct()
                ->orderByDesc('commission_runs.year')
                ->orderByDesc('commission_runs.month')
                ->get();

            if ($this->hasConsecutiveMonths($months, 3)) {
                $this->award($affiliate->id, 'hat_trick');
                $awarded++;
            }
        }

        return $awarded;
    }

    public function earned(Affiliate $affiliate): Collection
    {
        $defs = $this->definitions();

        return AffiliateBadge::where('affiliate_id', $affiliate->id)
            ->orderBy('earned_at')
            ->get()
            ->map(fn (AffiliateBadge $b): array => array_merge(
                $defs[$b->badge_key] ?? ['label' => $b->badge_key, 'icon' => '🏅', 'desc' => '', 'css' => 'badge-gray'],
                ['key' => $b->badge_key, 'earned_at' => $b->earned_at, 'meta' => $b->meta],
            ));
    }

    private function award(int $affiliateId, string $badgeKey, array $meta = []): void
    {
        AffiliateBadge::firstOrCreate(
            ['affiliate_id' => $affiliateId, 'badge_key' => $badgeKey],
            ['earned_at' => now(), 'meta' => empty($meta) ? null : $meta],
        );
    }

    private function hasConsecutiveMonths(Collection $months, int $required): bool
    {
        if ($months->count() < $required) {
            return false;
        }

        $streak = 1;

        for ($i = 1; $i < $months->count(); $i++) {
            $prev = (int) $months[$i - 1]->year * 12 + (int) $months[$i - 1]->month;
            $curr = (int) $months[$i]->year * 12 + (int) $months[$i]->month;

            if ($prev - $curr === 1) {
                $streak++;
                if ($streak >= $required) {
                    return true;
                }
            } else {
                $streak = 1;
            }
        }

        return false;
    }
}
