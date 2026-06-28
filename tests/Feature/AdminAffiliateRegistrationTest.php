<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateApplication;
use App\Models\User;
use App\Services\AffiliateApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAffiliateRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_pending_registration_index_without_full_nric(): void
    {
        $admin = $this->admin();
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($referrer, [
            'full_name' => 'Nur Aisyah Binti Ahmad',
            'nric' => '020311031153',
            'phone' => '0123456789',
            'email' => 'aisyah@example.com',
            'tiktok_username' => '@aisyah_shop',
            'tiktok_username_confirmation' => '@aisyah_shop',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index'))
            ->assertOk()
            ->assertSee('Pending Registrations')
            ->assertSee($application->application_reference)
            ->assertSee('Nur Aisyah Binti Ahmad')
            ->assertSee('02****-**-1153')
            ->assertSee('0123456789')
            ->assertSee('aisyah@example.com')
            ->assertSee('aisyah_shop')
            ->assertSee('Inviting Manager')
            ->assertSee('Titan Group')
            ->assertSee('Pending')
            ->assertSee('View')
            ->assertDontSee('020311031153')
            ->assertDontSee('nric_encrypted');
    }

    public function test_admin_can_filter_registration_applications_by_search_status_group_and_referrer(): void
    {
        $admin = $this->admin();
        $titanReferrer = $this->affiliate('Titan Manager', 'Titan Group', 'TIT-0001');
        $auroraReferrer = $this->affiliate('Aurora Manager', 'Aurora Group', 'AUR-0001');
        $target = $this->application($titanReferrer, [
            'full_name' => 'Target Applicant',
            'nric' => '020311031153',
            'phone' => '0123456789',
            'email' => 'target@example.com',
            'tiktok_username' => '@target_shop',
            'tiktok_username_confirmation' => '@target_shop',
        ]);
        $target->update(['status' => 'duplicate_review']);
        $other = $this->application($auroraReferrer, [
            'full_name' => 'Other Applicant',
            'nric' => '030411041154',
            'phone' => '0133456789',
            'email' => 'other@example.com',
            'tiktok_username' => '@other_shop',
            'tiktok_username_confirmation' => '@other_shop',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index', [
                'search' => 'target',
                'status' => 'duplicate_review',
                'group' => 'Titan Group',
                'referrer' => $titanReferrer->id,
            ]))
            ->assertOk()
            ->assertSee($target->application_reference)
            ->assertSee('Duplicate Review')
            ->assertDontSee($other->application_reference)
            ->assertDontSee('Other Applicant');
    }

    public function test_admin_can_view_duplicate_review_detail_without_full_nric(): void
    {
        $admin = $this->admin();
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        DB::table('tiktok_accounts')->insert([
            'affiliate_id' => $referrer->id,
            'username' => 'existing_shop',
            'username_normalized' => 'existing_shop',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $application = $this->application($referrer, [
            'full_name' => 'Duplicate Applicant',
            'nric' => '020311031153',
            'phone' => '0123456789',
            'email' => 'duplicate@example.com',
            'tiktok_username' => '@existing_shop',
            'tiktok_username_confirmation' => '@existing_shop',
            'notes' => 'Please review my application.',
        ]);

        $this->assertSame('duplicate_review', $application->status);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.show', $application->application_reference))
            ->assertOk()
            ->assertSee('Registration Details')
            ->assertSee($application->application_reference)
            ->assertSee('Duplicate Applicant')
            ->assertSee('02****-**-1153')
            ->assertSee('This application contains information that may already exist in the system and requires administrator review.')
            ->assertSee('TikTok username matches an existing record.')
            ->assertSee('Please review my application.')
            ->assertDontSee('020311031153')
            ->assertDontSee('nric_encrypted');
    }

    public function test_affiliate_user_cannot_access_admin_registration_pages(): void
    {
        $affiliateUser = User::query()->create([
            'name' => 'Affiliate User',
            'email' => 'affiliate@example.com',
            'affiliate_code' => 'TIT-0099',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($referrer);

        $this->actingAs($affiliateUser)
            ->get(route('admin.affiliate-registrations.index'))
            ->assertRedirect(route('affiliate.dashboard'));

        $this->actingAs($affiliateUser)
            ->get(route('admin.affiliate-registrations.show', $application->application_reference))
            ->assertRedirect(route('affiliate.dashboard'));
    }

    private function application(Affiliate $referrer, array $overrides = []): AffiliateApplication
    {
        return app(AffiliateApplicationService::class)->create($referrer->fresh()->referral, array_merge([
            'full_name' => 'Pending Applicant',
            'nric' => '900101011234',
            'phone' => '0123456789',
            'email' => 'pending@example.com',
            'tiktok_username' => '@pending_shop',
            'tiktok_username_confirmation' => '@pending_shop',
            'additional_tiktok_username' => '',
            'notes' => '',
            'consent' => '1',
        ], $overrides));
    }

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function affiliate(string $name, string $group, string $code): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => null,
            'affiliate_code' => $code,
            'upline_id' => null,
            'group_name' => $group,
            'affiliate_type' => 'external',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => null,
            'phone' => null,
            'status' => 'active',
        ]);
    }
}
