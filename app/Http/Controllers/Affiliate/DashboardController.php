<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $affiliate = $request->user()->affiliate;

        if (! $affiliate) {
            return view('affiliate.dashboard', [
                'affiliate' => null,
                'personalSales' => 0,
                'commissionSummary' => $this->emptyCommissionSummary(),
                'tiktokAccounts' => collect(),
                'directDownlines' => collect(),
                'recentOrders' => collect(),
            ]);
        }

        $personalSales = $affiliate->tiktokOrders()
            ->where('order_status', 'Settled')
            ->sum('estimated_commission_base');

        $entries = $affiliate->receivedCommissionEntries()->get();

        $commissionSummary = [
            'personal' => $entries
                ->where('commission_type', 'personal')
                ->sum('commission_amount'),
            'manager_bonus' => $entries
                ->where('commission_type', 'manager_bonus')
                ->sum('commission_amount'),
            'overriding_l1' => $entries
                ->where('commission_type', 'overriding')
                ->where('level', 1)
                ->sum('commission_amount'),
            'overriding_l2' => $entries
                ->where('commission_type', 'overriding')
                ->where('level', 2)
                ->sum('commission_amount'),
            'overriding_l3' => $entries
                ->where('commission_type', 'overriding')
                ->where('level', 3)
                ->sum('commission_amount'),
        ];

        $commissionSummary['total'] = array_sum($commissionSummary);

        return view('affiliate.dashboard', [
            'affiliate' => $affiliate,
            'personalSales' => $personalSales,
            'commissionSummary' => $commissionSummary,
            'tiktokAccounts' => $affiliate->tiktokAccounts()->latest()->get(),
            'directDownlines' => $affiliate->directDownlines()
                ->withCount('tiktokAccounts')
                ->orderBy('name')
                ->get(),
            'recentOrders' => $affiliate->tiktokOrders()
                ->latest('time_created')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    private function emptyCommissionSummary(): array
    {
        return [
            'personal' => 0,
            'manager_bonus' => 0,
            'overriding_l1' => 0,
            'overriding_l2' => 0,
            'overriding_l3' => 0,
            'total' => 0,
        ];
    }
}
