<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\CommissionRun;
use App\Services\AffiliateTeamService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __invoke(Request $request, AffiliateTeamService $teams): View
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $tree = $teams->tree($affiliate);
        $periodFilters = $this->resolvePeriodFilters($request);
        $sortFilters = $this->resolveSortFilters($request);
        $performance = $this->sortPerformance(
            $teams->performance($affiliate, $periodFilters['month'], $periodFilters['year']),
            $sortFilters['sort'],
            $sortFilters['direction'],
        );

        $teamStats = null;
        $directCount = $tree['direct_count'];

        if ($directCount > 0) {
            $perfCollection = collect($performance);
            $top5 = collect($teams->performance($affiliate, $periodFilters['month'], $periodFilters['year']))
                ->sortByDesc('total_sales')
                ->take(5)
                ->values()
                ->all();

            $teamStats = [
                'total_team_sales'  => $perfCollection->sum('total_sales'),
                'total_overriding'  => $perfCollection->sum('overriding_generated'),
                'active_members'    => $perfCollection->filter(fn ($r) => $r['total_sales'] > 0)->count(),
                'inactive_members'  => $perfCollection->filter(fn ($r) => $r['total_sales'] == 0)->count(),
                'total_members'     => $perfCollection->count(),
                'top5'              => $top5,
            ];
        }

        return view('affiliate.team', [
            'affiliate' => $affiliate,
            'teamTree' => $tree,
            'teamSummary' => [
                'direct_count' => $directCount,
                'total_count' => $tree['total_team_count'],
                'level_2_count' => $tree['level_2_count'],
                'level_3_plus_count' => $tree['level_3_plus_count'],
            ],
            'teamPerformance' => $performance,
            'teamStats' => $teamStats,
            'periodFilters' => $periodFilters,
            'sortFilters' => $sortFilters,
            'months' => $this->months(),
            'availableYears' => CommissionRun::query()
                ->select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year'),
        ]);
    }

    private function resolvePeriodFilters(Request $request): array
    {
        $now = now();
        $month = $request->query('month');
        $year = $request->query('year');
        $month = $month === null ? $now->month : ($month === 'all' ? 'all' : filter_var($month, FILTER_VALIDATE_INT));
        $year = $year === null ? $now->year : ($year === 'all' ? 'all' : filter_var($year, FILTER_VALIDATE_INT));

        if ($month !== 'all' && (! is_int($month) || $month < 1 || $month > 12)) {
            $month = $now->month;
        }

        if ($year !== 'all' && (! is_int($year) || $year < 2000 || $year > 2100)) {
            $year = $now->year;
        }

        if ($year === 'all') {
            $month = 'all';
        }

        return ['month' => $month, 'year' => $year];
    }

    private function resolveSortFilters(Request $request): array
    {
        $allowedSorts = [
            'name',
            'level',
            'type',
            'tiktok_accounts',
            'total_sales',
            'personal_commission',
            'overriding_generated',
        ];

        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = null;
            $direction = 'asc';
        }

        return ['sort' => $sort, 'direction' => $direction];
    }

    private function sortPerformance(array $performance, ?string $sort, string $direction): array
    {
        if ($sort === null) {
            return $performance;
        }

        $value = fn (array $row): mixed => match ($sort) {
            'name' => strtolower($row['affiliate']->name),
            'level' => $row['level_depth'],
            'type' => $row['affiliate']->affiliate_type,
            'tiktok_accounts' => $row['tiktok_account_count'],
            'total_sales' => $row['total_sales'],
            'personal_commission' => $row['personal_commission'],
            'overriding_generated' => $row['overriding_generated'],
            default => strtolower($row['affiliate']->name),
        };

        usort($performance, function (array $a, array $b) use ($direction, $value): int {
            $comparison = $value($a) <=> $value($b);

            if ($comparison === 0) {
                $comparison = strcmp($a['affiliate']->name, $b['affiliate']->name);
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $performance;
    }

    private function months(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }
}
