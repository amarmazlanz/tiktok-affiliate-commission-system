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
            'report_status' => ['required', Rule::in(['provisional', 'final'])],
            'confirm_final_recalculate' => ['nullable', 'boolean'],
        ]);

        $existingRun = CommissionRun::query()
            ->where('month', (int) $data['month'])
            ->where('year', (int) $data['year'])
            ->first();

        if ($existingRun?->status === 'final' && ! $request->boolean('confirm_final_recalculate')) {
            return back()
                ->withInput()
                ->withErrors(['confirm_final_recalculate' => 'Report ini sudah Final. Sila confirm sebelum recalculate.']);
        }

        $run = $calculator->calculate((int) $data['month'], (int) $data['year'], $data['report_status']);

        return redirect()
            ->route('admin.commissions.show', $run)
            ->with('success', 'Commission calculation berjaya dijalankan.');
    }

    public function show(CommissionRun $commission): View
    {
        $commission->load([
            'commissionEntries.receiverAffiliate',
            'commissionEntries.sourceAffiliate',
            'commissionEntries.tiktokOrder',
        ]);

        $commissionEntries = $commission->commissionEntries;

        $salesByAffiliate = $commissionEntries
            ->where('commission_type', 'personal')
            ->groupBy('source_affiliate_id')
            ->map(fn ($entries) => $entries->sum('base_amount'));

        $summaries = $commissionEntries
            ->groupBy('receiver_affiliate_id')
            ->map(function ($entries) use ($salesByAffiliate) {
                $affiliate = $entries->first()->receiverAffiliate;

                $personal = $entries
                    ->where('commission_type', 'personal')
                    ->sum('commission_amount');
                $managerBonus = $entries
                    ->where('commission_type', 'manager_bonus')
                    ->sum('commission_amount');
                $l1Overriding = $entries
                    ->filter(fn ($entry): bool => $entry->commission_type === 'l1_overriding'
                        || ($entry->commission_type === 'overriding' && (int) $entry->level === 1))
                    ->sum('commission_amount');
                $l1SplitSeller = $entries
                    ->where('commission_type', 'l1_split_seller')
                    ->sum('commission_amount');
                $l1SplitUpline = $entries
                    ->where('commission_type', 'l1_split_upline')
                    ->sum('commission_amount');
                $l1Earnings = $l1Overriding + $l1SplitSeller + $l1SplitUpline;
                $l2Overriding = $entries
                    ->filter(fn ($entry): bool => $entry->commission_type === 'l2_overriding'
                        || ($entry->commission_type === 'overriding' && (int) $entry->level === 2))
                    ->sum('commission_amount');
                $l3Overriding = $entries
                    ->filter(fn ($entry): bool => $entry->commission_type === 'l3_overriding'
                        || ($entry->commission_type === 'overriding' && (int) $entry->level === 3))
                    ->sum('commission_amount');

                return [
                    'affiliate' => $affiliate,
                    'total_sales' => (float) ($salesByAffiliate[$affiliate->id] ?? 0),
                    'personal' => $personal,
                    'manager_bonus' => $managerBonus,
                    'l1_earnings' => $l1Earnings,
                    'l2_earnings' => $l2Overriding,
                    'l3_earnings' => $l3Overriding,
                    'total' => $personal + $managerBonus + $l1Earnings + $l2Overriding + $l3Overriding,
                ];
            })
            ->sortBy(fn ($summary) => $summary['affiliate']->name)
            ->values();

        return view('admin.commissions.show', [
            'commission' => $commission,
            'summaries' => $summaries,
            'months' => $this->months(),
            'entryTypeLabels' => $this->entryTypeLabels(),
        ]);
    }

    private function entryTypeLabels(): array
    {
        return [
            'personal' => 'Personal Commission',
            'manager_bonus' => 'Manager Bonus',
            'overriding' => 'Overriding',
            'l1_overriding' => 'L1 Overriding',
            'l1_split_seller' => 'L1 Split - Seller 70%',
            'l1_split_upline' => 'L1 Split - Upline 30%',
            'l2_overriding' => 'L2 Overriding',
            'l3_overriding' => 'L3 Overriding',
        ];
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
