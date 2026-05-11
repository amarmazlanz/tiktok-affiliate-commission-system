<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRun;
use App\Services\CommissionCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(): View
    {
        return view('admin.commissions.index', [
            'runs' => CommissionRun::query()->latest('year')->latest('month')->paginate(15),
            'months' => $this->months(),
        ]);
    }

    public function store(Request $request, CommissionCalculatorService $calculator): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $run = $calculator->calculate((int) $data['month'], (int) $data['year']);

        return redirect()
            ->route('admin.commissions.show', $run)
            ->with('success', 'Commission calculation berjaya dijalankan.');
    }

    public function show(CommissionRun $commission): View
    {
        $commission->load(['commissionEntries.receiverAffiliate']);

        $summaries = $commission->commissionEntries
            ->groupBy('receiver_affiliate_id')
            ->map(function ($entries) {
                $affiliate = $entries->first()->receiverAffiliate;

                $personal = $entries
                    ->where('commission_type', 'personal')
                    ->sum('commission_amount');
                $managerBonus = $entries
                    ->where('commission_type', 'manager_bonus')
                    ->sum('commission_amount');
                $overridingL1 = $entries
                    ->where('commission_type', 'overriding')
                    ->where('level', 1)
                    ->sum('commission_amount');
                $overridingL2 = $entries
                    ->where('commission_type', 'overriding')
                    ->where('level', 2)
                    ->sum('commission_amount');
                $overridingL3 = $entries
                    ->where('commission_type', 'overriding')
                    ->where('level', 3)
                    ->sum('commission_amount');

                return [
                    'affiliate' => $affiliate,
                    'personal' => $personal,
                    'manager_bonus' => $managerBonus,
                    'overriding_l1' => $overridingL1,
                    'overriding_l2' => $overridingL2,
                    'overriding_l3' => $overridingL3,
                    'total' => $personal + $managerBonus + $overridingL1 + $overridingL2 + $overridingL3,
                ];
            })
            ->sortBy(fn ($summary) => $summary['affiliate']->name)
            ->values();

        return view('admin.commissions.show', [
            'commission' => $commission,
            'summaries' => $summaries,
            'months' => $this->months(),
        ]);
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
