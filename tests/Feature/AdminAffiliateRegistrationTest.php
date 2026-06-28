<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateApplication;
use App\Models\TiktokAccount;
use App\Models\User;
use App\Services\AffiliateApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function test_default_registration_index_only_shows_applications_needing_action(): void
    {
        $admin = $this->admin();
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $pending = $this->application($referrer, [
            'full_name' => 'Pending Applicant',
            'email' => 'pending-filter@example.com',
            'tiktok_username' => '@pending_filter',
            'tiktok_username_confirmation' => '@pending_filter',
        ]);
        $duplicate = $this->application($referrer, [
            'full_name' => 'Duplicate Applicant',
            'email' => 'duplicate-filter@example.com',
            'phone' => '0133456789',
            'tiktok_username' => '@duplicate_filter',
            'tiktok_username_confirmation' => '@duplicate_filter',
        ]);
        $duplicate->update(['status' => 'duplicate_review']);
        $approved = $this->application($referrer, [
            'full_name' => 'Approved Applicant',
            'email' => 'approved-filter@example.com',
            'phone' => '0143456789',
            'tiktok_username' => '@approved_filter',
            'tiktok_username_confirmation' => '@approved_filter',
        ]);
        $approved->update(['status' => 'approved']);
        $rejected = $this->application($referrer, [
            'full_name' => 'Rejected Applicant',
            'email' => 'rejected-filter@example.com',
            'phone' => '0163456789',
            'tiktok_username' => '@rejected_filter',
            'tiktok_username_confirmation' => '@rejected_filter',
        ]);
        $rejected->update(['status' => 'rejected']);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index'))
            ->assertOk()
            ->assertSee($pending->application_reference)
            ->assertSee($duplicate->application_reference)
            ->assertDontSee($approved->application_reference)
            ->assertDontSee($rejected->application_reference)
            ->assertSee('Needs Action');

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee($approved->application_reference)
            ->assertDontSee($pending->application_reference);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee($rejected->application_reference)
            ->assertDontSee($pending->application_reference);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee($pending->application_reference)
            ->assertSee($duplicate->application_reference)
            ->assertSee($approved->application_reference)
            ->assertSee($rejected->application_reference);
    }

    public function test_action_column_is_rendered_first_on_registration_index(): void
    {
        $admin = $this->admin();
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $this->application($referrer);

        $response = $this->actingAs($admin)->get(route('admin.affiliate-registrations.index'));
        $html = $response->getContent();

        $this->assertLessThan(
            strpos($html, 'Application Reference'),
            strpos($html, 'Action'),
        );
        $response->assertSee('sticky left-0', false);
    }

    public function test_summary_cards_are_clickable_status_filter_shortcuts(): void
    {
        $admin = $this->admin();
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $approved = $this->application($referrer, [
            'full_name' => 'Approved Shortcut',
            'email' => 'approved-shortcut@example.com',
            'tiktok_username' => '@approved_shortcut',
            'tiktok_username_confirmation' => '@approved_shortcut',
        ]);
        $approved->update(['status' => 'approved']);

        $response = $this->actingAs($admin)->get(route('admin.affiliate-registrations.index', [
            'search' => 'Shortcut',
            'group' => 'Titan Group',
            'status' => 'needs_action',
            'per_page' => 50,
        ]));

        $response
            ->assertOk()
            ->assertSee('Click a card to filter applications.')
            ->assertSee('status=approved', false)
            ->assertSee('search=Shortcut', false)
            ->assertSee('group=Titan+Group', false)
            ->assertSee('per_page=50', false);

        $this->actingAs($admin)
            ->get(route('admin.affiliate-registrations.index', [
                'search' => 'Shortcut',
                'group' => 'Titan Group',
                'status' => 'approved',
                'per_page' => 50,
            ]))
            ->assertOk()
            ->assertSee($approved->application_reference)
            ->assertSee('value="approved" selected', false);
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

    public function test_admin_approves_pending_application_and_creates_affiliate_login_tiktok_and_referral(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($upline, [
            'full_name' => 'Approved Applicant',
            'nric' => '020311031153',
            'phone' => '0123456789',
            'email' => 'approved@example.com',
            'tiktok_username' => '@approved_shop',
            'tiktok_username_confirmation' => '@approved_shop',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.affiliate-registrations.approve', $application->application_reference), [
            'affiliate_type' => 'inhouse',
            'group_name' => 'Titan Group',
            'upline_id' => $upline->id,
            'position' => 'affiliate',
            'status' => 'active',
            'approval_notes' => 'Looks good.',
        ]);

        $response
            ->assertRedirect(route('admin.affiliate-registrations.index'))
            ->assertSessionHas('approved_login');

        $application->refresh();
        $affiliate = Affiliate::query()->where('email', 'approved@example.com')->firstOrFail();
        $login = session('approved_login');

        $this->assertSame('approved', $application->status);
        $this->assertSame($affiliate->id, $application->approved_affiliate_id);
        $this->assertSame($upline->id, $affiliate->upline_id);
        $this->assertSame('Approved Applicant', $affiliate->name);
        $this->assertSame('Titan Group', $affiliate->group_name);
        $this->assertSame('inhouse', $affiliate->affiliate_type);
        $this->assertSame('active', $affiliate->status);
        $this->assertNotNull($affiliate->affiliate_code);
        $this->assertSame($affiliate->affiliate_code, $login['login_id']);
        $this->assertSame($affiliate->affiliate_code, $affiliate->user->affiliate_code);
        $this->assertSame('affiliate', $affiliate->user->role);
        $this->assertTrue($affiliate->user->must_change_password);
        $this->assertTrue(Hash::check($login['temporary_password'], $affiliate->user->password));
        $this->assertDatabaseHas('tiktok_accounts', [
            'affiliate_id' => $affiliate->id,
            'username_normalized' => 'approved_shop',
        ]);
        $this->assertNotNull($affiliate->fresh()->referral);

        Auth::logout();
        $this->post(route('login.store'), [
            'login' => $affiliate->affiliate_code,
            'password' => $login['temporary_password'],
        ])->assertRedirect(route('affiliate.password.edit'));
    }

    public function test_approved_application_cannot_be_approved_twice(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($upline, [
            'email' => 'twice@example.com',
            'tiktok_username' => '@twice_shop',
            'tiktok_username_confirmation' => '@twice_shop',
        ]);

        $this->actingAs($admin)->post(route('admin.affiliate-registrations.approve', $application->application_reference), $this->approvalData($upline));
        $affiliateCount = Affiliate::count();

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.approve', $application->application_reference), $this->approvalData($upline))
            ->assertRedirect(route('admin.affiliate-registrations.index'))
            ->assertSessionHas('error');

        $this->assertSame($affiliateCount, Affiliate::count());
    }

    public function test_admin_rejects_pending_application_without_creating_affiliate(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($upline, [
            'email' => 'reject@example.com',
            'tiktok_username' => '@reject_shop',
            'tiktok_username_confirmation' => '@reject_shop',
        ]);
        $affiliateCount = Affiliate::count();

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.reject', $application->application_reference), [
                'rejection_reason' => 'Incomplete information.',
            ])
            ->assertRedirect(route('admin.affiliate-registrations.index'))
            ->assertSessionHas('success', 'Application rejected successfully.');

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('Incomplete information.', $application->rejection_reason);
        $this->assertNull($application->approved_affiliate_id);
        $this->assertSame($affiliateCount, Affiliate::count());
        $this->assertDatabaseMissing('users', ['email' => 'reject@example.com']);
    }

    public function test_rejection_reason_is_required(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($upline);

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.reject', $application->application_reference), [
                'rejection_reason' => '',
            ])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_upline_change_requires_reason(): void
    {
        $admin = $this->admin();
        $proposed = $this->affiliate('Original Upline', 'Titan Group', 'TIT-0001');
        $newUpline = $this->affiliate('New Upline', 'Titan Group', 'TIT-0002');
        $application = $this->application($proposed, [
            'email' => 'upline-change@example.com',
            'tiktok_username' => '@upline_change',
            'tiktok_username_confirmation' => '@upline_change',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.approve', $application->application_reference), $this->approvalData($newUpline, [
                'upline_change_reason' => '',
            ]))
            ->assertSessionHasErrors('upline_change_reason');

        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_duplicate_nric_approval_is_blocked(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $first = $this->application($upline, [
            'nric' => '020311031153',
            'email' => 'first-nric@example.com',
            'phone' => '0123456789',
            'tiktok_username' => '@first_nric',
            'tiktok_username_confirmation' => '@first_nric',
        ]);
        $second = $this->application($upline, [
            'nric' => '020311031153',
            'email' => 'second-nric@example.com',
            'phone' => '0133456789',
            'tiktok_username' => '@second_nric',
            'tiktok_username_confirmation' => '@second_nric',
        ]);

        $this->actingAs($admin)->post(route('admin.affiliate-registrations.approve', $first->application_reference), $this->approvalData($upline));
        $affiliateCount = Affiliate::count();

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.approve', $second->application_reference), $this->approvalData($upline))
            ->assertSessionHasErrors('approval');

        $this->assertSame('duplicate_review', $second->fresh()->status);
        $this->assertSame($affiliateCount, Affiliate::count());
    }

    public function test_duplicate_tiktok_approval_is_blocked(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        TiktokAccount::query()->create([
            'affiliate_id' => $upline->id,
            'username' => 'existing_shop',
            'username_normalized' => 'existing_shop',
            'status' => 'active',
        ]);
        $application = $this->application($upline, [
            'email' => 'tiktok-duplicate@example.com',
            'tiktok_username' => '@existing_shop',
            'tiktok_username_confirmation' => '@existing_shop',
        ]);
        $affiliateCount = Affiliate::count();

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.approve', $application->application_reference), $this->approvalData($upline))
            ->assertSessionHasErrors('approval');

        $this->assertSame($affiliateCount, Affiliate::count());
        $this->assertSame('duplicate_review', $application->fresh()->status);
    }

    public function test_affiliate_luar_approval_also_receives_login_access(): void
    {
        $admin = $this->admin();
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($upline, [
            'email' => 'external-approved@example.com',
            'tiktok_username' => '@external_approved',
            'tiktok_username_confirmation' => '@external_approved',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.affiliate-registrations.approve', $application->application_reference), $this->approvalData($upline, [
                'affiliate_type' => 'external',
            ]))
            ->assertRedirect(route('admin.affiliate-registrations.index'))
            ->assertSessionHas('approved_login');

        $affiliate = Affiliate::query()->where('email', 'external-approved@example.com')->firstOrFail();

        $this->assertSame('external', $affiliate->affiliate_type);
        $this->assertNotNull($affiliate->user);
        $this->assertSame('affiliate', $affiliate->user->role);
        $this->assertTrue($affiliate->user->must_change_password);
    }

    public function test_non_admin_cannot_approve_or_reject_registration(): void
    {
        $affiliateUser = User::query()->create([
            'name' => 'Affiliate User',
            'email' => 'member@example.com',
            'affiliate_code' => 'TIT-0099',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
        $upline = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $application = $this->application($upline);

        $this->actingAs($affiliateUser)
            ->post(route('admin.affiliate-registrations.approve', $application->application_reference), $this->approvalData($upline))
            ->assertRedirect(route('affiliate.dashboard'));

        $this->actingAs($affiliateUser)
            ->post(route('admin.affiliate-registrations.reject', $application->application_reference), [
                'rejection_reason' => 'No.',
            ])
            ->assertRedirect(route('affiliate.dashboard'));

        $this->assertSame('pending', $application->fresh()->status);
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

    private function approvalData(Affiliate $upline, array $overrides = []): array
    {
        return array_merge([
            'affiliate_type' => 'inhouse',
            'group_name' => $upline->group_name,
            'upline_id' => $upline->id,
            'upline_change_reason' => '',
            'position' => 'affiliate',
            'status' => 'active',
            'approval_notes' => '',
        ], $overrides);
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
