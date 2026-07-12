<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\TiktokAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AffiliateLoginReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_print_a_group_filtered_login_report(): void
    {
        $admin = $this->admin();
        $titan = $this->affiliate('Titan Affiliate', 'TIT-0001', 'Titan Group');
        $aurora = $this->affiliate('Aurora Affiliate', 'AUR-0001', 'Aurora Group');
        $external = Affiliate::create([
            'affiliate_code' => 'TIT-0002',
            'group_name' => 'Titan Group',
            'affiliate_type' => 'online',
            'name' => 'External Titan',
            'status' => 'active',
        ]);

        TiktokAccount::create([
            'affiliate_id' => $titan->id,
            'username' => 'titan_shop',
            'username_normalized' => 'titan_shop',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.affiliates.login-report', [
            'group' => 'Titan Group',
        ]));

        $response
            ->assertOk()
            ->assertSee('Affiliate Login Access Report')
            ->assertSee($titan->name)
            ->assertSee($external->name)
            ->assertSee('titan_shop')
            ->assertSee('No login access')
            ->assertSee('Not generated')
            ->assertDontSee($aurora->name);
    }

    public function test_affiliate_cannot_access_admin_login_report(): void
    {
        $affiliate = $this->affiliate('Regular Affiliate', 'REG-0001', 'SWG');

        $this->actingAs($affiliate->user)
            ->get(route('admin.affiliates.login-report'))
            ->assertRedirect(route('affiliate.dashboard'));
    }

    public function test_admin_generates_one_time_temporary_password_and_forces_change(): void
    {
        $admin = $this->admin();
        $affiliate = $this->affiliate('Titan Affiliate', 'TIT-0001', 'Titan Group');
        $oldPasswordHash = $affiliate->user->password;

        $confirmation = $this->actingAs($admin)->post(route('admin.affiliates.login-report.generate'), [
            'generation_scope' => 'selected',
            'selected' => [$affiliate->id],
        ]);

        $confirmation
            ->assertOk()
            ->assertSee('Confirm Temporary Password Generation')
            ->assertSee($affiliate->name)
            ->assertSee('Existing Password Replaced');

        $response = $this->post(route('admin.affiliates.login-report.generate.confirm'), [
            'confirmation_token' => $confirmation->viewData('confirmationToken'),
        ]);

        $response->assertRedirect();
        $affiliate->user->refresh();

        $this->assertTrue($affiliate->user->must_change_password);
        $this->assertNotSame($oldPasswordHash, $affiliate->user->password);

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $batch = $query['credential_batch'];
        $temporaryPassword = session('affiliate_login_report.'.$batch)[$affiliate->id];

        $this->assertMatchesRegularExpression('/[A-Z]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[a-z]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[0-9]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $temporaryPassword);
        $this->assertGreaterThanOrEqual(10, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $affiliate->user->password));

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($temporaryPassword)
            ->assertDontSee($affiliate->user->password);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertDontSee($temporaryPassword)
            ->assertSee('Not generated');
    }

    public function test_affiliate_must_change_generated_password_before_dashboard_access(): void
    {
        $affiliate = $this->affiliate('Forced Change Affiliate', 'FOR-0001', 'Kaizen Group');
        $affiliate->user->forceFill([
            'password' => Hash::make('TempPass!123'),
            'must_change_password' => true,
        ])->save();

        $this->post(route('login.store'), [
            'login' => 'FOR-0001',
            'password' => 'TempPass!123',
        ])->assertRedirect(route('affiliate.password.edit'));

        $this->get(route('affiliate.dashboard'))
            ->assertRedirect(route('affiliate.password.edit'));

        $this->post(route('affiliate.password.update'), [
            'current_password' => 'TempPass!123',
            'password' => 'NewSecure!456',
            'password_confirmation' => 'NewSecure!456',
        ])->assertRedirect(route('affiliate.dashboard'));

        $this->assertFalse($affiliate->user->fresh()->must_change_password);
        $this->assertNotNull($affiliate->user->fresh()->password_changed_at);
        $this->get(route('affiliate.dashboard'))->assertOk();
    }

    public function test_never_logged_in_scope_excludes_accounts_that_have_logged_in_or_changed_password(): void
    {
        $admin = $this->admin();
        $neverLoggedIn = $this->affiliate('Never Logged In', 'NEW-0001', 'Titan Group');
        $loggedIn = $this->affiliate('Already Logged In', 'OLD-0001', 'Titan Group');
        $changedPassword = $this->affiliate('Changed Password', 'OLD-0002', 'Titan Group');

        $loggedIn->user->forceFill(['last_login_at' => now()->subDay()])->save();
        $changedPassword->user->forceFill([
            'last_login_at' => now()->subDays(2),
            'password_changed_at' => now()->subDay(),
            'must_change_password' => false,
        ])->save();

        $response = $this->actingAs($admin)->post(route('admin.affiliates.login-report.generate'), [
            'generation_scope' => 'never_logged_in',
            'group' => 'Titan Group',
        ]);

        $response
            ->assertOk()
            ->assertSee($neverLoggedIn->name)
            ->assertDontSee($loggedIn->name)
            ->assertDontSee($changedPassword->name)
            ->assertViewHas('affiliates', fn ($affiliates) => $affiliates->pluck('id')->all() === [$neverLoggedIn->id]);
    }

    public function test_password_generation_confirmation_uses_summary_and_limited_preview(): void
    {
        $admin = $this->admin();

        for ($index = 1; $index <= 25; $index++) {
            $this->affiliate('Preview Affiliate '.$index, sprintf('PRE-%04d', $index), 'Titan Group');
        }

        $response = $this->actingAs($admin)->post(route('admin.affiliates.login-report.generate'), [
            'generation_scope' => 'never_logged_in',
            'group' => 'Titan Group',
        ]);

        $response
            ->assertOk()
            ->assertSee('Total Affected Accounts')
            ->assertSee('Showing first 20 of 25 affected accounts.')
            ->assertViewHas('affectedCounts', fn (array $counts) => $counts['total'] === 25)
            ->assertViewHas('affiliates', fn ($affiliates) => $affiliates->count() === 20);
    }

    public function test_selected_scope_explicitly_allows_resetting_a_previously_used_account(): void
    {
        $admin = $this->admin();
        $affiliate = $this->affiliate('Previously Used', 'USED-0001', 'SWG');
        $affiliate->user->forceFill([
            'last_login_at' => now()->subDay(),
            'password_changed_at' => now()->subHours(12),
            'must_change_password' => false,
        ])->save();
        $previousPasswordChangedAt = $affiliate->user->password_changed_at;

        $confirmation = $this->actingAs($admin)->post(route('admin.affiliates.login-report.generate'), [
            'generation_scope' => 'selected',
            'selected' => [$affiliate->id],
        ]);

        $confirmation
            ->assertOk()
            ->assertSee('explicit override')
            ->assertSee($affiliate->name);

        $this->post(route('admin.affiliates.login-report.generate.confirm'), [
            'confirmation_token' => $confirmation->viewData('confirmationToken'),
        ])->assertRedirect();

        $affiliate->user->refresh();
        $this->assertTrue($affiliate->user->must_change_password);
        $this->assertTrue($affiliate->user->password_changed_at->equalTo($previousPasswordChangedAt));
    }

    public function test_successful_login_records_last_login_time(): void
    {
        $affiliate = $this->affiliate('Login Tracking', 'LOG-0001', 'Kaizen Group');
        $affiliate->user->forceFill([
            'password' => Hash::make('SecurePass!123'),
            'must_change_password' => false,
            'last_login_at' => null,
        ])->save();

        $this->post(route('login.store'), [
            'login' => 'LOG-0001',
            'password' => 'SecurePass!123',
        ])->assertRedirect(route('affiliate.dashboard'));

        $this->assertNotNull($affiliate->user->fresh()->last_login_at);
    }

    public function test_admin_reset_password_is_secure_audited_and_shown_once_on_detail_page(): void
    {
        $admin = $this->admin();
        $affiliate = $this->affiliate('Detail Affiliate', 'DET-0001', 'Titan Group');
        $oldPasswordHash = $affiliate->user->password;

        $response = $this->actingAs($admin)->post(route('admin.affiliates.reset-password', $affiliate));

        $response->assertRedirect(route('admin.affiliates.show', $affiliate));
        $affiliate->refresh();
        $affiliate->user->refresh();

        $temporaryPassword = session('reset_password.temporary_password');

        $this->assertMatchesRegularExpression('/[A-Z]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[a-z]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[0-9]/', $temporaryPassword);
        $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $temporaryPassword);
        $this->assertGreaterThanOrEqual(10, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $affiliate->user->password));
        $this->assertNotSame($oldPasswordHash, $affiliate->user->password);
        $this->assertTrue($affiliate->user->must_change_password);
        $this->assertNotNull($affiliate->password_reset_at);
        $this->assertSame($admin->id, $affiliate->password_reset_by);

        $this->get(route('admin.affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Password reset successful')
            ->assertSee('Copy Password')
            ->assertSee($temporaryPassword)
            ->assertSee('Must Change Password')
            ->assertDontSee($affiliate->user->password);

        $this->get(route('admin.affiliates.show', $affiliate))
            ->assertOk()
            ->assertDontSee($temporaryPassword)
            ->assertDontSee('Password reset successful');
    }

    public function test_external_affiliate_with_existing_login_has_reset_button_and_can_be_reset(): void
    {
        $admin = $this->admin();
        $legacyUser = User::factory()->create([
            'affiliate_code' => 'EXT-0001',
            'role' => 'affiliate',
        ]);
        $external = Affiliate::create([
            'user_id' => $legacyUser->id,
            'affiliate_code' => 'EXT-0001',
            'group_name' => 'Aurora Group',
            'affiliate_type' => 'online',
            'name' => 'External Affiliate',
            'status' => 'active',
        ]);
        $originalHash = $legacyUser->password;

        $this->actingAs($admin)
            ->get(route('admin.affiliates.show', $external))
            ->assertOk()
            ->assertDontSee('No login access')
            ->assertSee('action="'.route('admin.affiliates.reset-password', $external).'"', false);

        $this->actingAs($admin)
            ->post(route('admin.affiliates.reset-password', $external))
            ->assertRedirect(route('admin.affiliates.show', $external));

        $this->assertNotSame($originalHash, $legacyUser->fresh()->password);
    }

    public function test_external_affiliate_without_login_gets_one_generated_on_demand(): void
    {
        $admin = $this->admin();
        $external = Affiliate::create([
            'affiliate_code' => 'EXT-0002',
            'group_name' => 'Aurora Group',
            'affiliate_type' => 'online',
            'name' => 'New External Affiliate',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.affiliates.show', $external))
            ->assertOk()
            ->assertSee('No login access')
            ->assertSee('Generate Login');

        $this->actingAs($admin)
            ->post(route('admin.affiliates.reset-password', $external))
            ->assertRedirect(route('admin.affiliates.show', $external));

        $external->refresh();
        $this->assertNotNull($external->user_id);
        $this->assertSame('EXT-0002', $external->user->affiliate_code);
        $this->assertTrue($external->user->must_change_password);
        $this->assertNull($external->user->email);
    }

    public function test_bulk_selected_scope_generates_login_for_external_affiliate_without_account(): void
    {
        $admin = $this->admin();
        $external = Affiliate::create([
            'affiliate_code' => 'EXT-0003',
            'group_name' => 'Aurora Group',
            'affiliate_type' => 'online',
            'name' => 'Bulk External Affiliate',
            'status' => 'active',
        ]);

        $confirmation = $this->actingAs($admin)->post(route('admin.affiliates.login-report.generate'), [
            'generation_scope' => 'selected',
            'selected' => [$external->id],
        ]);

        $confirmation->assertOk()->assertSee('Bulk External Affiliate');

        $this->post(route('admin.affiliates.login-report.generate.confirm'), [
            'confirmation_token' => $confirmation->viewData('confirmationToken'),
        ])->assertRedirect();

        $external->refresh();
        $this->assertNotNull($external->user_id);
        $this->assertSame('EXT-0003', $external->user->affiliate_code);
        $this->assertTrue($external->user->must_change_password);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
    }

    private function affiliate(string $name, string $code, string $group): Affiliate
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower($code).'@inhouse.local',
            'affiliate_code' => $code,
            'role' => 'affiliate',
        ]);

        return Affiliate::create([
            'user_id' => $user->id,
            'affiliate_code' => $code,
            'group_name' => $group,
            'affiliate_type' => 'inhouse',
            'name' => $name,
            'status' => 'active',
        ]);
    }
}
