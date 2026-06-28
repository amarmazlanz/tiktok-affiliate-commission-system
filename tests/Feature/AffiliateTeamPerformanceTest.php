<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\CommissionRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateTeamPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_own_downline_performance_with_correct_totals(): void
    {
        $managerUser = $this->affiliateUser('manager@example.com', 'MGR-0001');
        $manager = $this->affiliate($managerUser, 'Manager One', 'Titan Group');
        $directDownline = $this->affiliate(null, 'Direct Downline', 'Titan Group', ['upline_id' => $manager->id]);
        $subDownline = $this->affiliate(null, 'Sub Downline', 'Titan Group', ['upline_id' => $directDownline->id]);
        $unrelated = $this->affiliate(null, 'Unrelated Affiliate', 'Aurora Group');

        $run = CommissionRun::query()->create([
            'month' => 6,
            'year' => 2026,
            'status' => 'final',
            'total_sales' => 1500,
            'total_commission' => 161.50,
            'calculated_at' => now(),
        ]);
        $now = now();

        DB::table('commission_entries')->insert([
            $this->entry($run->id, $directDownline->id, $directDownline->id, 'personal', null, 1000, 100, $now),
            $this->entry($run->id, $subDownline->id, $subDownline->id, 'personal', null, 500, 50, $now),
            $this->entry($run->id, $manager->id, $directDownline->id, 'l1_overriding', 1, 1000, 10, $now),
            $this->entry($run->id, $manager->id, $subDownline->id, 'l2_overriding', 2, 500, 1.5, $now),
            $this->entry($run->id, $unrelated->id, $unrelated->id, 'personal', null, 9999, 999.9, $now),
        ]);

        $response = $this->actingAs($managerUser)
            ->get(route('affiliate.team', ['month' => 6, 'year' => 2026]))
            ->assertOk()
            ->assertSee('Direct Downline')
            ->assertSee('Sub Downline')
            ->assertDontSee('Unrelated Affiliate');

        $response->assertSee('RM 1,000.00');
        $response->assertSee('RM 100.00');
        $response->assertSee('RM 10.00');
        $response->assertSee('RM 500.00');
        $response->assertSee('RM 50.00');
        $response->assertSee('RM 1.50');
        $response->assertDontSee('Total Team Sales');
        $response->assertDontSee('Apply');
    }

    public function test_team_performance_can_be_sorted_by_total_sales(): void
    {
        $managerUser = $this->affiliateUser('sorter@example.com', 'SRT-0001');
        $manager = $this->affiliate($managerUser, 'Sorter Manager', 'Titan Group');
        $lowSales = $this->affiliate(null, 'Low Sales Downline', 'Titan Group', ['upline_id' => $manager->id]);
        $highSales = $this->affiliate(null, 'High Sales Downline', 'Titan Group', ['upline_id' => $manager->id]);

        $run = CommissionRun::query()->create([
            'month' => 4,
            'year' => 2026,
            'status' => 'final',
        ]);
        $now = now();

        DB::table('commission_entries')->insert([
            $this->entry($run->id, $lowSales->id, $lowSales->id, 'personal', null, 100, 10, $now),
            $this->entry($run->id, $highSales->id, $highSales->id, 'personal', null, 900, 90, $now),
        ]);

        $this->actingAs($managerUser)
            ->get(route('affiliate.team', [
                'month' => 4,
                'year' => 2026,
                'sort' => 'total_sales',
                'direction' => 'desc',
            ]))
            ->assertOk()
            ->assertSeeInOrder(['High Sales Downline', 'Low Sales Downline'])
            ->assertSee('month=4', false)
            ->assertSee('year=2026', false)
            ->assertSee('sort=total_sales', false)
            ->assertSee('direction=asc', false);

        $this->actingAs($managerUser)
            ->get(route('affiliate.team', [
                'month' => 4,
                'year' => 2026,
                'sort' => 'total_sales',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSeeInOrder(['Low Sales Downline', 'High Sales Downline'])
            ->assertSee('Total Sales', false)
            ->assertSee('↑', false);
    }

    public function test_unrelated_affiliate_cannot_see_managers_team_performance(): void
    {
        $managerUser = $this->affiliateUser('manager2@example.com', 'MGR-0002');
        $manager = $this->affiliate($managerUser, 'Manager Two', 'Titan Group');
        $this->affiliate(null, 'Manager Two Downline', 'Titan Group', ['upline_id' => $manager->id]);

        $outsiderUser = $this->affiliateUser('outsider@example.com', 'OUT-0001');
        $this->affiliate($outsiderUser, 'Outsider', 'Aurora Group');

        $this->actingAs($outsiderUser)
            ->get(route('affiliate.team'))
            ->assertOk()
            ->assertDontSee('Manager Two Downline');
    }

    public function test_all_months_filter_aggregates_across_periods(): void
    {
        $managerUser = $this->affiliateUser('manager3@example.com', 'MGR-0003');
        $manager = $this->affiliate($managerUser, 'Manager Three', 'Titan Group');
        $downline = $this->affiliate(null, 'Multi Period Downline', 'Titan Group', ['upline_id' => $manager->id]);

        $runOne = CommissionRun::query()->create(['month' => 5, 'year' => 2026, 'status' => 'final']);
        $runTwo = CommissionRun::query()->create(['month' => 6, 'year' => 2026, 'status' => 'final']);
        $now = now();

        DB::table('commission_entries')->insert([
            $this->entry($runOne->id, $downline->id, $downline->id, 'personal', null, 300, 30, $now),
            $this->entry($runTwo->id, $downline->id, $downline->id, 'personal', null, 200, 20, $now),
        ]);

        $this->actingAs($managerUser)
            ->get(route('affiliate.team', ['month' => 'all', 'year' => 'all']))
            ->assertOk()
            ->assertSee('RM 500.00')
            ->assertSee('RM 50.00');
    }

    private function affiliateUser(string $email, string $affiliateCode): User
    {
        return User::query()->create([
            'name' => $affiliateCode,
            'email' => $email,
            'affiliate_code' => $affiliateCode,
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
    }

    private function affiliate(?User $user, string $name, string $group, array $overrides = []): Affiliate
    {
        return Affiliate::query()->create(array_merge([
            'user_id' => $user?->id,
            'affiliate_code' => $user?->affiliate_code ?: uniqid('AFF-'),
            'upline_id' => null,
            'group_name' => $group,
            'affiliate_type' => 'inhouse',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => $user?->email,
            'status' => 'active',
        ], $overrides));
    }

    private function entry(
        int $runId,
        int $receiverId,
        int $sourceId,
        string $type,
        ?int $level,
        float $baseAmount,
        float $commissionAmount,
        $now,
    ): array {
        return [
            'commission_run_id' => $runId,
            'receiver_affiliate_id' => $receiverId,
            'source_affiliate_id' => $sourceId,
            'tiktok_order_id' => null,
            'commission_type' => $type,
            'level' => $level,
            'rate' => 0.01,
            'base_amount' => $baseAmount,
            'commission_amount' => $commissionAmount,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
