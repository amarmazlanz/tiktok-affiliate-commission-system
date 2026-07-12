<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\CommissionRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommissionReportScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_summary_calculates_and_sorts_total_overriding_without_double_counting(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $manager = $this->affiliate('Qualified Manager', 'Titan Group', 'inhouse');
        $personalOnly = $this->affiliate('Personal Only Affiliate', 'Titan Group', 'inhouse');
        $run = CommissionRun::query()->create([
            'month' => 7,
            'year' => 2026,
            'status' => 'final',
            'total_sales' => 76001.90,
            'total_commission' => 10895.24,
            'calculated_at' => now(),
        ]);
        $now = now();

        DB::table('commission_entries')->insert([
            $this->entry($run->id, $manager->id, $manager->id, null, 'personal', null, 0.10, 75001.90, 7500.19, $now),
            $this->entry($run->id, $manager->id, $manager->id, null, 'manager_bonus', null, 0.01, 75001.90, 750.21, $now),
            $this->entry($run->id, $manager->id, $personalOnly->id, null, 'l1_split_seller', 1, 0.007, 356154.29, 2493.08, $now),
            $this->entry($run->id, $manager->id, $personalOnly->id, null, 'l2_overriding', 2, 0.003, 17253.33, 51.76, $now),
            $this->entry($run->id, $personalOnly->id, $personalOnly->id, null, 'personal', null, 0.10, 1000, 100, $now),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.commissions.show', [
            'commission' => $run,
            'summary_sort' => 'total_overriding',
            'summary_dir' => 'desc',
        ]));

        $response
            ->assertOk()
            ->assertSee('Total Overriding')
            ->assertSee('RM 3,295.05')
            ->assertSee('RM 10,795.24')
            ->assertSee('summary_sort=total_overriding', false)
            ->assertViewHas('totalOverriding', fn ($value): bool => abs((float) $value - 3295.05) < 0.001)
            ->assertViewHas('summaries', function ($summaries) use ($manager, $personalOnly): bool {
                $rows = collect($summaries->items())->keyBy('affiliate_id');
                $managerRow = $rows->get($manager->id);
                $personalOnlyRow = $rows->get($personalOnly->id);

                return (int) $summaries->items()[0]->affiliate_id === $manager->id
                    && abs((float) $managerRow->total_overriding - 3295.05) < 0.001
                    && abs((float) $managerRow->total - 10795.24) < 0.001
                    && abs((float) $personalOnlyRow->total_overriding) < 0.001
                    && abs((float) $personalOnlyRow->total - 100.0) < 0.001;
            });
    }

    public function test_commission_report_paginates_large_entry_dataset_without_loading_every_entry(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $seller = $this->affiliate('Large Seller', 'Titan Group', 'inhouse');
        $upline = $this->affiliate('Large Upline', 'Aurora Group');
        $senior = $this->affiliate('Large Senior', 'SWG');
        $run = CommissionRun::query()->create([
            'month' => 6,
            'year' => 2026,
            'status' => 'final',
            'total_sales' => 4000000,
            'total_commission' => 444000,
            'calculated_at' => now(),
        ]);

        $now = now();

        foreach (range(1, 40000) as $index) {
            $orders[] = [
                'order_id' => sprintf('ORD-%05d', $index),
                'affiliate_id' => $seller->id,
                'creator_username' => 'seller',
                'creator_username_normalized' => 'seller',
                'order_status' => 'Settled',
                'estimated_commission_base' => 100,
                'time_created' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($orders) === 1000) {
                DB::table('tiktok_orders')->insert($orders);
                $orders = [];
            }
        }

        if (! empty($orders)) {
            DB::table('tiktok_orders')->insert($orders);
        }

        DB::table('tiktok_orders')
            ->orderBy('id')
            ->select(['id'])
            ->chunk(30, function ($orders) use ($run, $seller, $upline, $senior, $now): void {
                $entries = [];

                foreach ($orders as $order) {
                    $entries[] = $this->entry($run->id, $seller->id, $seller->id, $order->id, 'personal', null, 0.10, 100, 10, $now);
                    $entries[] = $this->entry($run->id, $upline->id, $seller->id, $order->id, 'l1_overriding', 1, 0.01, 100, 1, $now);
                    $entries[] = $this->entry($run->id, $senior->id, $seller->id, $order->id, 'l2_overriding', 2, 0.003, 100, 0.30, $now);
                }

                DB::table('commission_entries')->insert($entries);
            });

        $this->assertSame(120000, DB::table('commission_entries')->where('commission_run_id', $run->id)->count());

        DB::enableQueryLog();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.commissions.show', $run));

        $queryCount = count(DB::getQueryLog());

        $response
            ->assertOk()
            ->assertSee('Showing 1-50 of 120,000 entries')
            ->assertSee('Large Seller')
            ->assertSee('Personal Commission')
            ->assertSee('data-combobox', false)
            ->assertSee($seller->affiliate_code)
            ->assertSee('No receiver found');

        $this->assertLessThan(40, $queryCount);
        $this->assertSame(120000, DB::table('commission_entries')->where('commission_run_id', $run->id)->count());

        $summaryFilterResponse = $this
            ->actingAs($admin)
            ->get(route('admin.commissions.show', [
                'commission' => $run,
                'summary_group' => 'Aurora Group',
                'summary_affiliate' => $upline->id,
                'summary_sort' => 'total',
                'summary_dir' => 'desc',
            ]));

        $summaryFilterResponse
            ->assertOk()
            ->assertSee('Showing 1-1 of 1 affiliates')
            ->assertSee('Large Upline')
            ->assertSee('Aurora Group')
            ->assertSee('Group Total Sales')
            ->assertSee('RM 0.00')
            ->assertSee('Affiliate Luar');

        $inhouseSummaryResponse = $this
            ->actingAs($admin)
            ->get(route('admin.commissions.show', [
                'commission' => $run,
                'summary_group' => 'Titan Group',
                'summary_affiliate' => $seller->id,
            ]));

        $inhouseSummaryResponse
            ->assertOk()
            ->assertSee('Group Total Sales')
            ->assertSee('RM 4,000,000.00')
            ->assertSee('Large Seller')
            ->assertDontSee('Affiliate Luar');

        $invalidSummaryAffiliateResponse = $this
            ->actingAs($admin)
            ->get(route('admin.commissions.show', [
                'commission' => $run,
                'summary_group' => 'Aurora Group',
                'summary_affiliate' => $seller->id,
            ]));

        $invalidSummaryAffiliateResponse
            ->assertOk()
            ->assertSee('Showing 1-1 of 1 affiliates')
            ->assertSee('Large Upline');

        $entryGroupResponse = $this
            ->actingAs($admin)
            ->get(route('admin.commissions.show', [
                'commission' => $run,
                'entry_group' => 'SWG',
                'per_page' => 50,
            ]));

        $entryGroupResponse
            ->assertOk()
            ->assertSee('Showing 1-50 of 40,000 entries')
            ->assertSee('entry_group=SWG', false);

        $ajaxGroupResponse = $this
            ->actingAs($admin)
            ->getJson(route('admin.commissions.show', [
                'commission' => $run,
                'ajax' => 1,
                'entry_group' => 'SWG',
                'per_page' => 50,
            ]));

        $ajaxGroupResponse
            ->assertOk()
            ->assertJsonPath('resultCount', 40000)
            ->assertJsonPath('receiverOptions.0.id', $senior->id)
            ->assertJsonCount(1, 'receiverOptions')
            ->assertJsonCount(0, 'sourceOptions')
            ->assertJsonPath('selectedReceiver', null)
            ->assertJsonPath('selectedSource', null)
            ->assertSee('Showing 1-50 of 40,000 entries', false)
            ->assertSee('Large Senior', false)
            ->assertDontSee('Affiliate Income Summary', false);

        $ajaxInvalidReceiverResponse = $this
            ->actingAs($admin)
            ->getJson(route('admin.commissions.show', [
                'commission' => $run,
                'ajax' => 1,
                'entry_group' => 'SWG',
                'receiver' => $seller->id,
                'per_page' => 50,
            ]));

        $ajaxInvalidReceiverResponse
            ->assertOk()
            ->assertJsonPath('selectedReceiver', null)
            ->assertJsonPath('resultCount', 40000);

        $ajaxInvalidSourceResponse = $this
            ->actingAs($admin)
            ->getJson(route('admin.commissions.show', [
                'commission' => $run,
                'ajax' => 1,
                'entry_group' => 'SWG',
                'source' => $seller->id,
                'per_page' => 50,
            ]));

        $ajaxInvalidSourceResponse
            ->assertOk()
            ->assertJsonPath('selectedSource', null)
            ->assertJsonCount(0, 'sourceOptions')
            ->assertJsonPath('resultCount', 40000);

        $ajaxTitanResponse = $this
            ->actingAs($admin)
            ->getJson(route('admin.commissions.show', [
                'commission' => $run,
                'ajax' => 1,
                'entry_group' => 'Titan Group',
                'receiver' => $seller->id,
                'source' => $seller->id,
                'per_page' => 50,
            ]));

        $ajaxTitanResponse
            ->assertOk()
            ->assertJsonPath('receiverOptions.0.id', $seller->id)
            ->assertJsonPath('sourceOptions.0.id', $seller->id)
            ->assertJsonPath('selectedReceiver', $seller->id)
            ->assertJsonPath('selectedSource', $seller->id)
            ->assertJsonPath('resultCount', 40000);

        $ajaxAllGroupsResponse = $this
            ->actingAs($admin)
            ->getJson(route('admin.commissions.show', [
                'commission' => $run,
                'ajax' => 1,
                'per_page' => 50,
            ]));

        $ajaxAllGroupsResponse
            ->assertOk()
            ->assertJsonCount(3, 'receiverOptions')
            ->assertJsonCount(1, 'sourceOptions')
            ->assertJsonPath('sourceOptions.0.id', $seller->id);

        $ajaxPaginationResponse = $this
            ->actingAs($admin)
            ->getJson(route('admin.commissions.show', [
                'commission' => $run,
                'ajax' => 1,
                'receiver' => $seller->id,
                'type' => 'personal',
                'per_page' => 100,
                'entries_page' => 2,
            ]));

        $ajaxPaginationResponse
            ->assertOk()
            ->assertJsonPath('resultCount', 40000)
            ->assertSee('Showing 101-200 of 40,000 entries', false)
            ->assertSee('entries_page=3', false)
            ->assertSee('receiver='.$seller->id, false);

        $filteredResponse = $this
            ->actingAs($admin)
            ->get(route('admin.commissions.show', [
                'commission' => $run,
                'receiver' => $seller->id,
                'type' => 'personal',
                'per_page' => 50,
                'entries_page' => 2,
            ]));

        $filteredResponse
            ->assertOk()
            ->assertSee('Showing 51-100 of 40,000 entries')
            ->assertSee('receiver='.$seller->id, false)
            ->assertSee('type=personal', false)
            ->assertSee('entries_page=3', false);
    }

    private function affiliate(string $name, string $groupName, string $affiliateType = 'online'): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => null,
            'affiliate_code' => uniqid('AFF-'),
            'upline_id' => null,
            'group_name' => $groupName,
            'affiliate_type' => $affiliateType,
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => null,
            'phone' => null,
            'status' => 'active',
        ]);
    }

    private function entry(
        int $runId,
        int $receiverId,
        int $sourceId,
        ?int $orderId,
        string $type,
        ?int $level,
        float $rate,
        float $baseAmount,
        float $commissionAmount,
        $now,
    ): array {
        return [
            'commission_run_id' => $runId,
            'receiver_affiliate_id' => $receiverId,
            'source_affiliate_id' => $sourceId,
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
}
