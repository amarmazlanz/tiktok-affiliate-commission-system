<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
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

        return view('affiliate.team', [
            'affiliate' => $affiliate,
            'teamTree' => $tree,
            'teamSummary' => [
                'direct_count' => $tree['direct_count'],
                'total_count' => $tree['total_team_count'],
                'level_2_count' => $tree['level_2_count'],
                'level_3_plus_count' => $tree['level_3_plus_count'],
            ],
        ]);
    }
}
