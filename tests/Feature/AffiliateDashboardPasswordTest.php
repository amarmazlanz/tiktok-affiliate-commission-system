<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateDashboardPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_portal_separates_dashboard_team_commission_and_tiktok_pages(): void
    {
        $user = $this->affiliateUser();
        $affiliate = $this->affiliate($user, 'Ali Seller');
        $downline = $this->affiliate(null, 'Very Long Downline Name That Should Wrap Properly', [
            'upline_id' => $affiliate->id,
            'email' => 'downline@example.com',
        ]);

        DB::table('tiktok_accounts')->insert([
            [
                'affiliate_id' => $affiliate->id,
                'username' => 'ali_shop',
                'username_normalized' => 'ali_shop',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'affiliate_id' => $downline->id,
                'username' => 'downline_shop',
                'username_normalized' => 'downline_shop',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'affiliate_id' => $downline->id,
                'username' => 'downline_shop_2',
                'username_normalized' => 'downline_shop_2',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'affiliate_id' => $downline->id,
                'username' => 'downline_shop_3',
                'username_normalized' => 'downline_shop_3',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'affiliate_id' => $downline->id,
                'username' => 'downline_shop_4',
                'username_normalized' => 'downline_shop_4',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('tiktok_orders')->insert([
            'order_id' => 'LONG-ORDER-ID-0000001',
            'affiliate_id' => $affiliate->id,
            'creator_username' => 'ali_shop',
            'creator_username_normalized' => 'ali_shop',
            'order_status' => 'Settled',
            'estimated_commission_base' => 123.45,
            'time_created' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('affiliate.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Profile Overview')
            ->assertSee('Account Settings')
            ->assertSee('View My Commission')
            ->assertSee('View My Team')
            ->assertSee('data-period-select', false)
            ->assertSee("fetch(requestUrl", false)
            ->assertDontSee('Commission Breakdown')
            ->assertDontSee('My Team Hierarchy')
            ->assertDontSee('Recent Orders')
            ->assertDontSee('LONG-ORDER-ID-0000001')
            ->assertDontSee('downline_shop');

        $this->actingAs($user)->get(route('affiliate.team'))
            ->assertOk()
            ->assertSee('Very Long Downline Name That Should Wrap Properly')
            ->assertSee('downline_shop')
            ->assertSee('data-team-expand-all', false);

        $this->actingAs($user)->get(route('affiliate.tiktok-accounts'))
            ->assertOk()
            ->assertSee('ali_shop')
            ->assertDontSee('downline_shop');

        $this->actingAs($user)->get(route('affiliate.invite'))
            ->assertOk()
            ->assertSee('Share this link with a new applicant.')
            ->assertSee('Online registration and approval are not active yet.');

        $this->actingAs($user)->get(route('affiliate.settings'))
            ->assertOk()
            ->assertSeeText('Login & Security')
            ->assertSee($user->affiliate_code)
            ->assertSee('Change Password');
    }

    public function test_affiliate_only_sees_their_own_full_descendant_branch(): void
    {
        $izzuddinUser = User::query()->create([
            'name' => 'IZZUDDIN',
            'email' => 'izzuddin@example.com',
            'affiliate_code' => 'TIT-0001',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
        $izzuddin = $this->affiliate($izzuddinUser, 'IZZUDDIN');
        $azimUser = User::query()->create([
            'name' => 'AZIM',
            'email' => 'azim@example.com',
            'affiliate_code' => 'TIT-0002',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
        $azim = $this->affiliate($azimUser, 'AZIM', ['upline_id' => $izzuddin->id]);
        $norhafieza = $this->affiliate(null, 'NORHAFIEZA', [
            'affiliate_code' => 'TIT-0003',
            'upline_id' => $azim->id,
        ]);
        $firdaus = $this->affiliate(null, 'FIRDAUS', [
            'affiliate_code' => 'TIT-0004',
            'upline_id' => $norhafieza->id,
        ]);
        $unrelatedManager = $this->affiliate(null, 'UNRELATED MANAGER', [
            'affiliate_code' => 'AUR-0001',
            'group_name' => 'Aurora Group',
        ]);
        $this->affiliate(null, 'UNRELATED MEMBER', [
            'affiliate_code' => 'AUR-0002',
            'group_name' => 'Aurora Group',
            'upline_id' => $unrelatedManager->id,
        ]);

        DB::table('tiktok_accounts')->insert([
            'affiliate_id' => $firdaus->id,
            'username' => 'firdaus_shop',
            'username_normalized' => 'firdaus_shop',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::enableQueryLog();
        $response = $this->actingAs($azimUser)->get(route('affiliate.team'));
        $queryCount = count(DB::getQueryLog());

        $response
            ->assertOk()
            ->assertSee('AZIM')
            ->assertSee('NORHAFIEZA')
            ->assertSee('FIRDAUS')
            ->assertSee('firdaus_shop')
            ->assertSee('>L1<', false)
            ->assertSee('>L2<', false)
            ->assertDontSee('UNRELATED MANAGER')
            ->assertDontSee('UNRELATED MEMBER')
            ->assertViewHas('teamSummary', fn (array $summary): bool => $summary === [
                'direct_count' => 1,
                'total_count' => 2,
                'level_2_count' => 1,
                'level_3_plus_count' => 0,
            ])
            ->assertViewHas('teamTree', function (array $tree) use ($azim, $norhafieza, $firdaus, $izzuddin): bool {
                $ids = collect([$tree]);
                $flatten = function ($nodes) use (&$flatten) {
                    return collect($nodes)->flatMap(fn (array $node) => collect([$node['affiliate']->id])
                        ->merge($flatten($node['children'])));
                };
                $branchIds = $flatten($ids)->all();

                return $tree['affiliate']->id === $azim->id
                    && in_array($norhafieza->id, $branchIds, true)
                    && in_array($firdaus->id, $branchIds, true)
                    && ! in_array($izzuddin->id, $branchIds, true);
            });

        $this->assertLessThan(35, $queryCount);
    }

    public function test_affiliate_commission_breakdown_only_shows_received_entries(): void
    {
        $user = $this->affiliateUser();
        $receiver = $this->affiliate($user, 'Ali Receiver');
        $source = $this->affiliate(null, 'Abu Source', [
            'affiliate_code' => 'AFF-0002',
        ]);
        $unrelated = $this->affiliate(null, 'Unrelated Receiver', [
            'affiliate_code' => 'AFF-9999',
        ]);

        $runId = DB::table('commission_runs')->insertGetId([
            'month' => 7,
            'year' => 2026,
            'status' => 'final',
            'total_sales' => 1000,
            'total_commission' => 100,
            'calculated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('tiktok_orders')->insertGetId([
            'order_id' => 'ORDER-RECEIVED-001',
            'affiliate_id' => $source->id,
            'creator_username' => 'abu_shop',
            'creator_username_normalized' => 'abu_shop',
            'order_status' => 'Settled',
            'estimated_commission_base' => 1000,
            'time_created' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commission_entries')->insert([
            [
                'commission_run_id' => $runId,
                'receiver_affiliate_id' => $receiver->id,
                'source_affiliate_id' => $source->id,
                'tiktok_order_id' => $orderId,
                'commission_type' => 'l1_overriding',
                'level' => 1,
                'rate' => 0.01,
                'base_amount' => 1000,
                'commission_amount' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'commission_run_id' => $runId,
                'receiver_affiliate_id' => $unrelated->id,
                'source_affiliate_id' => $source->id,
                'tiktok_order_id' => $orderId,
                'commission_type' => 'personal',
                'level' => null,
                'rate' => 0.10,
                'base_amount' => 1000,
                'commission_amount' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('affiliate.commission', [
            'commission_type' => 'l1_overriding',
            'source_affiliate' => $source->id,
            'month' => 7,
            'year' => 2026,
        ]));

        $response
            ->assertOk()
            ->assertSee('My Commission')
            ->assertSee('Commission Breakdown')
            ->assertSee('data-auto-submit-select', false)
            ->assertDontSee('>Filter<', false)
            ->assertSee('Abu Source')
            ->assertSee('ORDER-RECEIVED-001')
            ->assertSee('L1 Overriding')
            ->assertSee('RM 10.00')
            ->assertDontSee('Unrelated Receiver')
            ->assertDontSee('RM 100.00');
    }

    public function test_affiliate_can_change_own_password_with_current_password(): void
    {
        $user = $this->affiliateUser();
        $this->affiliate($user, 'Ali Seller');

        $this->actingAs($user)
            ->post(route('affiliate.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->post(route('affiliate.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('affiliate.dashboard'))
            ->assertSessionHas('success', 'Password changed successfully.');

        $this->actingAs($user)
            ->get(route('affiliate.dashboard'))
            ->assertOk()
            ->assertSee('Password changed successfully.')
            ->assertSee('data-success-toast', false);

        Auth::logout();

        $this->assertTrue(Auth::attempt([
            'affiliate_code' => $user->affiliate_code,
            'password' => 'new-password-123',
        ]));
    }

    public function test_admin_cannot_open_affiliate_portal_pages(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        foreach ([
            'affiliate.dashboard',
            'affiliate.commission',
            'affiliate.team',
            'affiliate.tiktok-accounts',
            'affiliate.invite',
            'affiliate.settings',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertRedirect(route('admin.dashboard'));
        }
    }

    public function test_dashboard_period_filter_defaults_to_latest_period_and_updates_kpis(): void
    {
        $user = $this->affiliateUser();
        $affiliate = $this->affiliate($user, 'Ali Seller');
        $source = $this->affiliate(null, 'Abu Source', ['affiliate_code' => 'AFF-0002']);

        $aprilRun = $this->commissionRun(4, 2026);
        $juneRun = $this->commissionRun(6, 2026);
        $decemberRun = $this->commissionRun(12, 2025);

        $this->settledOrder($affiliate, 'APRIL-ORDER', 1000, '2026-04-15 10:00:00');
        $this->settledOrder($affiliate, 'JUNE-ORDER', 2500, '2026-06-15 10:00:00');
        $this->settledOrder($affiliate, 'DECEMBER-ORDER', 400, '2025-12-15 10:00:00');
        $this->commissionEntry($aprilRun, $affiliate, $source, 'personal', 100);
        $this->commissionEntry($aprilRun, $affiliate, $source, 'manager_bonus', 10);
        $this->commissionEntry($aprilRun, $affiliate, $source, 'l1_overriding', 5, 1);
        $this->commissionEntry($juneRun, $affiliate, $source, 'personal', 250);
        $this->commissionEntry($juneRun, $affiliate, $source, 'manager_bonus', 25);
        $this->commissionEntry($juneRun, $affiliate, $source, 'l2_overriding', 7.50, 2);
        $this->commissionEntry($decemberRun, $affiliate, $source, 'personal', 40);

        $this->actingAs($user)
            ->get(route('affiliate.dashboard'))
            ->assertOk()
            ->assertSee('June 2026')
            ->assertSee('RM 2,500.00')
            ->assertSee('RM 250.00')
            ->assertSee('RM 7.50')
            ->assertDontSee('Recent Orders')
            ->assertDontSee('Commission Breakdown')
            ->assertViewHas('personalSales', fn ($value): bool => (float) $value === 2500.0)
            ->assertViewHas('commissionSummary', fn (array $summary): bool =>
                $summary['personal'] === 250.0
                && $summary['manager_bonus'] === 25.0
                && $summary['l2_overriding'] === 7.5
                && $summary['total'] === 282.5);

        $this->actingAs($user)
            ->get(route('affiliate.dashboard', ['month' => 4, 'year' => 2026]))
            ->assertOk()
            ->assertSee('April 2026')
            ->assertSee('RM 1,000.00')
            ->assertSee('RM 100.00')
            ->assertSee('RM 5.00')
            ->assertViewHas('personalSales', fn ($value): bool => (float) $value === 1000.0)
            ->assertViewHas('commissionSummary', fn (array $summary): bool =>
                $summary['personal'] === 100.0
                && $summary['manager_bonus'] === 10.0
                && $summary['l1_overriding'] === 5.0
                && $summary['total'] === 115.0);

        $this->actingAs($user)
            ->get(route('affiliate.dashboard', ['month' => 1, 'year' => 2026]))
            ->assertOk()
            ->assertSee('January 2026')
            ->assertSee('RM 0.00')
            ->assertViewHas('personalSales', fn ($value): bool => (float) $value === 0.0)
            ->assertViewHas('commissionSummary', fn (array $summary): bool => $summary['total'] === 0.0);

        $this->actingAs($user)
            ->get(route('affiliate.dashboard', ['month' => 'all', 'year' => 2026]))
            ->assertOk()
            ->assertSee('Year 2026')
            ->assertViewHas('personalSales', fn ($value): bool => (float) $value === 3500.0)
            ->assertViewHas('commissionSummary', fn (array $summary): bool => $summary['total'] === 397.5);

        $this->actingAs($user)
            ->get(route('affiliate.dashboard', ['month' => 'all', 'year' => 'all']))
            ->assertOk()
            ->assertSee('Lifetime Performance')
            ->assertViewHas('personalSales', fn ($value): bool => (float) $value === 3900.0)
            ->assertViewHas('commissionSummary', fn (array $summary): bool => $summary['total'] === 437.5);

        $this->actingAs($user)
            ->get(route('affiliate.dashboard', ['month' => 4, 'year' => 'all']))
            ->assertOk()
            ->assertSee('Lifetime Performance')
            ->assertViewHas('periodFilters', fn (array $filters): bool =>
                $filters['month'] === 'all' && $filters['year'] === 'all');
    }

    public function test_dashboard_period_ajax_returns_summary_only_and_commission_ajax_returns_breakdown(): void
    {
        $user = $this->affiliateUser();
        $affiliate = $this->affiliate($user, 'Ali Seller');
        $source = $this->affiliate(null, 'Abu Source', ['affiliate_code' => 'AFF-0002']);
        $run = $this->commissionRun(4, 2026);

        $this->settledOrder($affiliate, 'APRIL-AJAX-ORDER', 1200, '2026-04-20 10:00:00');
        $this->commissionEntry($run, $affiliate, $source, 'personal', 120);

        $response = $this->actingAs($user)->getJson(route('affiliate.dashboard', [
            'ajax' => 1,
            'month' => 4,
            'year' => 2026,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('periodLabel', 'April 2026')
            ->assertJsonPath('month', 4)
            ->assertJsonPath('year', 2026)
            ->assertJsonMissingPath('breakdownHtml');

        $this->assertStringContainsString('RM 1,200.00', $response->json('html'));
        $this->assertStringContainsString('RM 120.00', $response->json('html'));
        $this->assertStringNotContainsString('Profile Overview', $response->json('html'));

        $commissionResponse = $this->actingAs($user)->getJson(route('affiliate.commission', [
            'ajax' => 1,
            'month' => 4,
            'year' => 2026,
        ]));

        $commissionResponse
            ->assertOk()
            ->assertJsonPath('periodLabel', 'April 2026')
            ->assertJsonFragment(['sourceAffiliate' => null]);
        $this->assertStringContainsString('Commission Breakdown', $commissionResponse->json('breakdownHtml'));
        $this->assertStringContainsString('Abu Source', $commissionResponse->json('breakdownHtml'));
    }

    private function commissionRun(int $month, int $year): int
    {
        return DB::table('commission_runs')->insertGetId([
            'month' => $month,
            'year' => $year,
            'status' => 'final',
            'total_sales' => 0,
            'total_commission' => 0,
            'calculated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function settledOrder(Affiliate $affiliate, string $orderId, float $base, string $createdAt): void
    {
        DB::table('tiktok_orders')->insert([
            'order_id' => $orderId,
            'affiliate_id' => $affiliate->id,
            'creator_username' => 'ali_shop',
            'creator_username_normalized' => 'ali_shop',
            'order_status' => 'Settled',
            'estimated_commission_base' => $base,
            'time_created' => $createdAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function commissionEntry(
        int $runId,
        Affiliate $receiver,
        Affiliate $source,
        string $type,
        float $amount,
        ?int $level = null,
    ): void {
        DB::table('commission_entries')->insert([
            'commission_run_id' => $runId,
            'receiver_affiliate_id' => $receiver->id,
            'source_affiliate_id' => $source->id,
            'tiktok_order_id' => null,
            'commission_type' => $type,
            'level' => $level,
            'rate' => 0.10,
            'base_amount' => 1000,
            'commission_amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function affiliateUser(): User
    {
        return User::query()->create([
            'name' => 'Ali Seller',
            'email' => 'ali@example.com',
            'affiliate_code' => 'AFF-0001',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
    }

    private function affiliate(?User $user, string $name, array $attributes = []): Affiliate
    {
        return Affiliate::query()->create(array_merge([
            'user_id' => $user?->id,
            'affiliate_code' => $user?->affiliate_code ?: uniqid('AFF-'),
            'upline_id' => null,
            'group_name' => 'Titan Group',
            'affiliate_type' => 'inhouse',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => $user?->email,
            'phone' => null,
            'status' => 'active',
        ], $attributes));
    }
}
