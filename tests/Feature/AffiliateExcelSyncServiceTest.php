<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\CommissionEntry;
use App\Models\CommissionRun;
use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use App\Models\User;
use App\Services\AffiliateExcelSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AffiliateExcelSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_import_and_same_file_reupload_are_idempotent(): void
    {
        $rows = [
            $this->row('ALI BIN AHMAD', 'Titan Group', 'inhouse', 'ali_shop'),
            $this->row('ALI BIN AHMAD', 'Titan Group', 'inhouse', 'ali_backup'),
        ];

        $first = $this->sync($rows);
        $affiliate = Affiliate::query()->sole();
        $code = $affiliate->affiliate_code;

        $this->assertSame(1, Affiliate::count());
        $this->assertSame(2, TiktokAccount::count());
        $this->assertSame(1, $first['summary']['new_created']);

        $second = $this->sync($rows);

        $this->assertSame(1, Affiliate::count());
        $this->assertSame(2, TiktokAccount::count());
        $this->assertSame($code, $affiliate->fresh()->affiliate_code);
        $this->assertSame(0, $second['summary']['new_created']);
        $this->assertSame(2, $second['summary']['tiktok_unchanged']);
    }

    public function test_sync_updates_name_group_type_and_keeps_immutable_code(): void
    {
        $affiliate = $this->externalAffiliate('OLD NAME', 'TIT-0042', 'Titan Group');
        TiktokAccount::create([
            'affiliate_id' => $affiliate->id,
            'username' => 'stable_username',
            'username_normalized' => 'stable_username',
            'status' => 'active',
        ]);

        $result = $this->sync([
            $this->row('UPDATED NAME', 'Aurora Group', 'external', 'stable_username', [
                'affiliate_code' => 'TIT-0042',
                'status' => 'inactive',
            ]),
        ]);

        $affiliate->refresh();
        $this->assertSame('TIT-0042', $affiliate->affiliate_code);
        $this->assertSame('UPDATED NAME', $affiliate->name);
        $this->assertSame('Aurora Group', $affiliate->group_name);
        $this->assertSame('external', $affiliate->affiliate_type);
        $this->assertSame('inactive', $affiliate->status);
        $this->assertSame('Profile Updated', $result['results'][0]['status']);
    }

    public function test_existing_tiktok_ownership_supports_match_when_name_and_group_change_without_code(): void
    {
        $affiliate = $this->externalAffiliate('ORIGINAL NAME', 'TIT-0077', 'Titan Group');
        TiktokAccount::create([
            'affiliate_id' => $affiliate->id,
            'username' => 'stable_identity',
            'username_normalized' => 'stable_identity',
            'status' => 'active',
        ]);

        $this->sync([
            $this->row('NEW DISPLAY NAME', 'Aurora Group', 'external', 'stable_identity'),
        ]);

        $affiliate->refresh();
        $this->assertSame(1, Affiliate::count());
        $this->assertSame('TIT-0077', $affiliate->affiliate_code);
        $this->assertSame('NEW DISPLAY NAME', $affiliate->name);
        $this->assertSame('Aurora Group', $affiliate->group_name);
    }

    public function test_unique_name_and_group_allow_type_change_without_creating_duplicate(): void
    {
        $user = User::factory()->create([
            'affiliate_code' => 'TIT-0088',
            'role' => 'affiliate',
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'affiliate_code' => 'TIT-0088',
            'group_name' => 'Titan Group',
            'affiliate_type' => 'inhouse',
            'name' => 'TYPE CHANGE USER',
            'name_normalized' => 'type change user',
            'email' => $user->email,
            'status' => 'active',
        ]);

        $this->sync([
            $this->row('TYPE CHANGE USER', 'Titan Group', 'external', ''),
        ]);

        $affiliate->refresh();
        $this->assertSame(1, Affiliate::count());
        $this->assertSame('TIT-0088', $affiliate->affiliate_code);
        $this->assertSame('external', $affiliate->affiliate_type);
        $this->assertNull($affiliate->user_id);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_updated_l1_replaces_previous_or_null_upline_without_database_reset(): void
    {
        $azman = $this->externalAffiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR', 'AUR-0001', 'Aurora Group');
        $amirul = $this->externalAffiliate('MOHAMAD AMIRUL JAMALUDIN', 'AUR-0002', 'Aurora Group', [
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'En Azman',
            'upline_id' => $azman->id,
        ]);
        $ummi = $this->externalAffiliate('UMMI ATIKAH BINTI DZULKIFLY', 'AUR-0003', 'Aurora Group');

        $result = $this->sync([
            $this->row($azman->name, 'Aurora Group', 'external', '', ['affiliate_code' => $azman->affiliate_code]),
            $this->row($amirul->name, 'Aurora Group', 'external', '', [
                'affiliate_code' => $amirul->affiliate_code,
                'raw_l1' => 'AMIRUL',
                'raw_l2' => 'En Azman',
            ]),
            $this->row($ummi->name, 'Aurora Group', 'external', '', [
                'affiliate_code' => $ummi->affiliate_code,
                'raw_l1' => 'AMIRUL',
                'raw_l2' => 'En Azman',
            ]),
        ]);

        $this->assertSame($amirul->id, $ummi->fresh()->upline_id);
        $this->assertSame($azman->id, $ummi->fresh('upline')->upline->upline_id);
        $ummiResult = collect($result['results'])->firstWhere('affiliate_id', $ummi->id);
        $this->assertTrue($ummiResult['hierarchy_changed']);
        $this->assertSame('Linked', $ummiResult['upline_match']);
    }

    public function test_existing_tiktok_username_is_not_duplicated_and_conflict_is_not_overwritten(): void
    {
        $owner = $this->externalAffiliate('OWNER', 'SWG-0001', 'SWG');
        $target = $this->externalAffiliate('TARGET', 'SWG-0002', 'SWG');
        TiktokAccount::create([
            'affiliate_id' => $owner->id,
            'username' => 'owned_name',
            'username_normalized' => 'owned_name',
            'status' => 'active',
        ]);
        TiktokAccount::create([
            'affiliate_id' => $target->id,
            'username' => 'target_name',
            'username_normalized' => 'target_name',
            'status' => 'active',
        ]);

        $result = $this->sync([
            $this->row('TARGET', 'SWG', 'external', 'target_name', ['affiliate_code' => 'SWG-0002']),
            $this->row('TARGET', 'SWG', 'external', 'owned_name', ['affiliate_code' => 'SWG-0002']),
        ]);

        $this->assertSame(2, TiktokAccount::count());
        $this->assertSame($owner->id, TiktokAccount::where('username_normalized', 'owned_name')->value('affiliate_id'));
        $this->assertSame(1, $result['summary']['username_conflicts']);
    }

    public function test_mirror_deactivates_missing_username_but_safe_mode_keeps_it(): void
    {
        $affiliate = $this->externalAffiliate('MIRROR USER', 'KAI-0001', 'Kaizen Group');
        foreach (['keep_me', 'old_name'] as $username) {
            TiktokAccount::create([
                'affiliate_id' => $affiliate->id,
                'username' => $username,
                'username_normalized' => $username,
                'status' => 'active',
            ]);
        }

        $rows = [$this->row($affiliate->name, 'Kaizen Group', 'external', 'keep_me', [
            'affiliate_code' => $affiliate->affiliate_code,
        ])];

        $this->sync($rows, ['tiktok_sync' => 'safe']);
        $this->assertSame('active', TiktokAccount::where('username_normalized', 'old_name')->value('status'));

        $result = $this->sync($rows, ['tiktok_sync' => 'mirror']);
        $this->assertSame('inactive', TiktokAccount::where('username_normalized', 'old_name')->value('status'));
        $this->assertSame(1, $result['summary']['tiktok_marked_inactive']);
    }

    public function test_missing_affiliate_is_kept_by_default_and_append_mode_skips_updates(): void
    {
        $present = $this->externalAffiliate('PRESENT', 'TIT-0001', 'Titan Group');
        $missing = $this->externalAffiliate('MISSING', 'TIT-0002', 'Titan Group');

        $sync = $this->sync([
            $this->row('PRESENT UPDATED', 'Titan Group', 'external', '', ['affiliate_code' => 'TIT-0001']),
        ]);

        $this->assertSame('active', $missing->fresh()->status);
        $this->assertSame(1, $sync['summary']['missing_affiliates']);

        $append = $this->sync([
            $this->row('SHOULD NOT UPDATE', 'Titan Group', 'external', '', ['affiliate_code' => 'TIT-0001']),
        ], ['import_mode' => 'append']);

        $this->assertSame('PRESENT UPDATED', $present->fresh()->name);
        $this->assertSame('Skipped Existing', $append['results'][0]['status']);
    }

    public function test_sync_does_not_change_historical_orders_or_commission_entries(): void
    {
        $affiliate = $this->externalAffiliate('HISTORICAL', 'TIT-0099', 'Titan Group');
        $order = TiktokOrder::create([
            'order_id' => 'ORDER-HISTORY-1',
            'affiliate_id' => $affiliate->id,
            'creator_username' => 'history_shop',
            'creator_username_normalized' => 'history_shop',
            'order_status' => 'Settled',
            'estimated_commission_base' => 100,
        ]);
        $run = CommissionRun::create([
            'month' => 6,
            'year' => 2026,
            'status' => 'final',
            'total_sales' => 100,
            'total_commission' => 10,
        ]);
        $entry = CommissionEntry::create([
            'commission_run_id' => $run->id,
            'receiver_affiliate_id' => $affiliate->id,
            'source_affiliate_id' => $affiliate->id,
            'tiktok_order_id' => $order->id,
            'commission_type' => 'personal',
            'rate' => 0.10,
            'base_amount' => 100,
            'commission_amount' => 10,
        ]);

        $this->sync([
            $this->row('HISTORICAL UPDATED', 'Titan Group', 'external', '', ['affiliate_code' => 'TIT-0099']),
        ]);

        $this->assertDatabaseHas('tiktok_orders', ['id' => $order->id, 'affiliate_id' => $affiliate->id]);
        $this->assertDatabaseHas('commission_entries', ['id' => $entry->id, 'commission_amount' => 10]);
    }

    public function test_admin_import_endpoint_accepts_sync_options_and_parses_code_and_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $csv = implode("\n", [
            'affiliate_code,group_name,affiliate_type,name,status,tiktok_username,manager (l1),senior manager (l2),general manager (l3)',
            'TIT-0123,Titan Group,external,CSV AFFILIATE,inactive,csv_shop,,,',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.affiliates.import.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('affiliates.csv', $csv),
        ]);

        $response
            ->assertOk()
            ->assertViewIs('admin.affiliates.import-result')
            ->assertSee('Sync / Update Existing Data');
        $this->assertDatabaseHas('affiliates', [
            'affiliate_code' => 'TIT-0123',
            'name' => 'CSV AFFILIATE',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('tiktok_accounts', ['username_normalized' => 'csv_shop']);
    }

    private function sync(array $rows, array $options = []): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return app(AffiliateExcelSyncService::class)->import(
            $rows,
            array_merge([
                'import_mode' => 'sync',
                'tiktok_sync' => 'safe',
                'missing_affiliates' => 'keep',
            ], $options),
            $admin,
            'management.xlsx',
        );
    }

    private function row(string $name, string $group, string $type, string $username, array $overrides = []): array
    {
        return array_merge([
            'affiliate_code' => '',
            'group_name' => $group,
            'affiliate_type' => $type,
            'name' => $name,
            'phone' => '',
            'status' => '',
            'tiktok_username' => $username,
            'raw_l1' => '',
            'raw_l2' => '',
            'raw_l3' => '',
            'raw' => [],
        ], $overrides);
    }

    private function externalAffiliate(string $name, string $code, string $group, array $overrides = []): Affiliate
    {
        return Affiliate::create(array_merge([
            'affiliate_code' => $code,
            'group_name' => $group,
            'affiliate_type' => 'external',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'status' => 'active',
        ], $overrides));
    }
}
