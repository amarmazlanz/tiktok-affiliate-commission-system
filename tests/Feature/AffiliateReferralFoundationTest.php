<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\AffiliateReferralService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateReferralFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_inhouse_and_external_affiliates_receive_unique_group_referral_codes(): void
    {
        $inhouse = $this->affiliate('Inhouse Member', 'Titan Group', 'inhouse');
        $external = $this->affiliate('External Member', 'Aurora Group', 'external');

        $this->assertNotNull($inhouse->fresh()->referral);
        $this->assertNotNull($external->fresh()->referral);
        $this->assertMatchesRegularExpression('/^TIT-[A-Z2-9]{6}$/', $inhouse->fresh()->referral->referral_code);
        $this->assertMatchesRegularExpression('/^AUR-[A-Z2-9]{6}$/', $external->fresh()->referral->referral_code);
        $this->assertNotSame($inhouse->fresh()->referral->referral_code, $external->fresh()->referral->referral_code);
        $this->assertStringContainsString(
            '/register/ref/'.$inhouse->fresh()->referral->referral_code,
            $inhouse->fresh()->referralUrl(),
        );
    }

    public function test_existing_affiliate_without_referral_can_be_backfilled_without_overwriting_codes(): void
    {
        $affiliate = Affiliate::withoutEvents(fn () => $this->affiliateAttributesCreate('Existing Member', 'SWG', 'external'));
        $service = app(AffiliateReferralService::class);
        $first = $service->ensureFor($affiliate);
        $second = $service->ensureFor($affiliate->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->referral_code, $second->referral_code);
        $this->assertSame(1, AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->count());
    }

    public function test_affiliate_invite_page_only_displays_authenticated_affiliate_referral(): void
    {
        $user = $this->affiliateUser('Owner', 'OWN-0001');
        $owner = $this->affiliate('Owner', 'Titan Group', 'inhouse', $user);
        $other = $this->affiliate('Other Member', 'Titan Group', 'inhouse');

        $this->actingAs($user)
            ->get(route('affiliate.invite', ['affiliate' => $other->id]))
            ->assertOk()
            ->assertSee($owner->fresh()->referral->referral_code)
            ->assertSee($owner->fresh()->referralUrl())
            ->assertDontSee($other->fresh()->referral->referral_code)
            ->assertSee('Referral link copied.');
    }

    public function test_admin_can_view_disable_and_reenable_an_affiliate_referral(): void
    {
        $admin = $this->admin();
        $affiliate = $this->affiliate('Managed Affiliate', 'Kaizen Group', 'external');
        $referral = $affiliate->fresh()->referral;

        $this->actingAs($admin)
            ->get(route('admin.affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Referral Code')
            ->assertSee($referral->referral_code)
            ->assertSee($referral->url())
            ->assertSee('Disable Link');

        $this->actingAs($admin)
            ->patch(route('admin.affiliates.referral.update', $affiliate), ['is_active' => 0])
            ->assertRedirect(route('admin.affiliates.show', $affiliate));
        $this->assertFalse($referral->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.affiliates.referral.update', $affiliate), ['is_active' => 1])
            ->assertRedirect(route('admin.affiliates.show', $affiliate));
        $this->assertTrue($referral->fresh()->is_active);
    }

    public function test_public_placeholder_accepts_only_active_referral_and_active_affiliate(): void
    {
        $affiliate = $this->affiliate('Public Referrer', 'Titan Group', 'external');
        $referral = $affiliate->fresh()->referral;

        $this->get(route('public.affiliate-registration.show', strtolower($referral->referral_code)))
            ->assertOk()
            ->assertSee('Affiliate Registration')
            ->assertSee('You were invited by Public Referrer.')
            ->assertSee('Online registration will be available soon.')
            ->assertDontSee('Titan Group');

        $referral->update(['is_active' => false]);
        $this->get(route('public.affiliate-registration.show', $referral->referral_code))
            ->assertNotFound()
            ->assertSee('This referral link is invalid or no longer active.');

        $referral->update(['is_active' => true]);
        $affiliate->update(['status' => 'inactive']);
        $this->get(route('public.affiliate-registration.show', $referral->referral_code))
            ->assertNotFound()
            ->assertSee('This referral link is invalid or no longer active.');

        $this->get(route('public.affiliate-registration.show', 'TIT-NOTREAL'))
            ->assertNotFound()
            ->assertSee('This referral link is invalid or no longer active.');
    }

    public function test_database_prevents_duplicate_referral_codes(): void
    {
        $first = $this->affiliate('First Member', 'Titan Group');
        $second = $this->affiliate('Second Member', 'Titan Group');

        $this->expectException(QueryException::class);

        $second->referral->update([
            'referral_code' => $first->referral->referral_code,
        ]);
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

    private function affiliateUser(string $name, string $code): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => strtolower($code).'@example.com',
            'affiliate_code' => $code,
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
    }

    private function affiliate(
        string $name,
        string $groupName,
        string $type = 'external',
        ?User $user = null,
    ): Affiliate {
        return $this->affiliateAttributesCreate($name, $groupName, $type, $user);
    }

    private function affiliateAttributesCreate(
        string $name,
        string $groupName,
        string $type = 'external',
        ?User $user = null,
    ): Affiliate {
        return Affiliate::query()->create([
            'user_id' => $user?->id,
            'affiliate_code' => $user?->affiliate_code ?: uniqid('AFF-'),
            'upline_id' => null,
            'group_name' => $groupName,
            'affiliate_type' => $type,
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => $user?->email,
            'phone' => null,
            'status' => 'active',
        ]);
    }
}
