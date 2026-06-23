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

    public function test_affiliate_dashboard_has_scrollable_downline_and_recent_order_sections(): void
    {
        $user = $this->affiliateUser();
        $affiliate = $this->affiliate($user, 'Ali Seller');
        $downline = $this->affiliate(null, 'Very Long Downline Name That Should Wrap Properly', [
            'upline_id' => $affiliate->id,
            'email' => 'downline@example.com',
        ]);

        DB::table('tiktok_accounts')->insert([
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
            ->assertSee('Change Password')
            ->assertSee('max-h-[460px]', false)
            ->assertSee('sticky top-0', false)
            ->assertSee('downline_shop')
            ->assertSee('+1 more')
            ->assertSee('LONG-ORDER-ID-0000001');
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

        $response = $this->actingAs($user)->get(route('affiliate.dashboard', [
            'commission_type' => 'l1_overriding',
            'source_affiliate' => $source->id,
            'commission_period' => '2026-07',
        ]));

        $response
            ->assertOk()
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
            ->assertRedirect(route('affiliate.password.edit'))
            ->assertSessionHas('success');

        Auth::logout();

        $this->assertTrue(Auth::attempt([
            'affiliate_code' => $user->affiliate_code,
            'password' => 'new-password-123',
        ]));
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
