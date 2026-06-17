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
            'l1_overriding' => $entries
                ->filter(fn ($entry): bool => $entry->commission_type === 'l1_overriding'
                    || ($entry->commission_type === 'overriding' && (int) $entry->level === 1))
                ->sum('commission_amount'),
            'l1_split_seller' => $entries
                ->where('commission_type', 'l1_split_seller')
                ->sum('commission_amount'),
            'l1_split_upline' => $entries
                ->where('commission_type', 'l1_split_upline')
                ->sum('commission_amount'),
            'l2_overriding' => $entries
                ->filter(fn ($entry): bool => $entry->commission_type === 'l2_overriding'
                    || ($entry->commission_type === 'overriding' && (int) $entry->level === 2))
                ->sum('commission_amount'),
            'l3_overriding' => $entries
                ->filter(fn ($entry): bool => $entry->commission_type === 'l3_overriding'
                    || ($entry->commission_type === 'overriding' && (int) $entry->level === 3))
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
            'l1_overriding' => 0,
            'l1_split_seller' => 0,
            'l1_split_upline' => 0,
            'l2_overriding' => 0,
            'l3_overriding' => 0,
            'total' => 0,
        ];
    }
}
