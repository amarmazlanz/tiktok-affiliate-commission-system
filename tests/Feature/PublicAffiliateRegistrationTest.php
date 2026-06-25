<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateApplication;
use App\Models\User;
use App\Services\AffiliateApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicAffiliateRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_referral_opens_professional_registration_form(): void
    {
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');

        $this->get(route('public.affiliate-registration.show', $referrer->referral->referral_code))
            ->assertOk()
            ->assertSee('Affiliate Registration')
            ->assertSee('Inviting Manager')
            ->assertSee('TIT-0001')
            ->assertSee('Titan Group')
            ->assertSee('Your application will be reviewed by the administrator before activation.')
            ->assertSee('Full Legal Name')
            ->assertSee('NRIC / No. IC')
            ->assertDontSee($referrer->email);
    }

    public function test_valid_application_creates_pending_record_with_secure_nric_and_referrer_integrity(): void
    {
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $other = $this->affiliate('Wrong Upline', 'Aurora Group', 'AUR-0001');
        $affiliateCount = Affiliate::count();
        $userCount = User::count();
        $data = $this->validData([
            'proposed_upline_id' => $other->id,
            'referral_code' => $referrer->referral->referral_code,
        ]);

        $response = $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $data);
        $application = AffiliateApplication::query()->sole();

        $response->assertRedirect(route('public.affiliate-registration.submitted', $application->application_reference));
        $this->assertSame('pending', $application->status);
        $this->assertSame('clear', $application->duplicate_status);
        $this->assertSame($referrer->id, $application->referrer_affiliate_id);
        $this->assertSame($referrer->id, $application->proposed_upline_id);
        $this->assertSame('Titan Group', $application->proposed_group_name);
        $this->assertSame('900101011234', $application->nric_encrypted);
        $this->assertSame('90****-**-1234', $application->masked_nric);
        $this->assertSame(
            app(AffiliateApplicationService::class)->nricHash('900101011234'),
            $application->nric_hash,
        );
        $this->assertNotSame('900101011234', $application->getRawOriginal('nric_encrypted'));
        $this->assertSame($affiliateCount, Affiliate::count());
        $this->assertSame($userCount, User::count());
        $this->assertNull($application->reviewed_at);

        $this->get(route('public.affiliate-registration.submitted', $application->application_reference))
            ->assertOk()
            ->assertSee('Application submitted successfully.')
            ->assertSee($application->application_reference)
            ->assertSee('Inviting Manager')
            ->assertDontSee('900101011234')
            ->assertDontSee('900101-01-1234');
    }

    public function test_tampered_hidden_referral_code_is_rejected(): void
    {
        $referrer = $this->affiliate('Inviting Manager', 'Titan Group', 'TIT-0001');
        $other = $this->affiliate('Other Manager', 'Aurora Group', 'AUR-0001');

        $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $this->validData([
            'referral_code' => $other->referral->referral_code,
        ]))
            ->assertRedirect(route('public.affiliate-registration.show', $referrer->referral->referral_code))
            ->assertSessionHasErrors('referral_code');

        $this->assertSame(0, AffiliateApplication::count());
    }

    public function test_duplicate_nric_creates_duplicate_review_without_revealing_owner(): void
    {
        $referrer = $this->affiliate('Inviter', 'Titan Group', 'TIT-0001');
        $this->submit($referrer, $this->validData());
        $this->submit($referrer, $this->validData([
            'email' => 'second@example.com',
            'phone' => '013-8889999',
            'tiktok_username' => '@second_shop',
            'tiktok_username_confirmation' => '@second_shop',
        ]));

        $application = AffiliateApplication::query()->latest('id')->firstOrFail();

        $this->assertSame('duplicate_review', $application->status);
        $this->assertStringContainsString('NRIC matches an existing application.', $application->duplicate_notes);

        $this->get(route('public.affiliate-registration.submitted', $application->application_reference))
            ->assertOk()
            ->assertSee('An application using similar information may already exist.')
            ->assertDontSee('Inviter owns');
    }

    public function test_existing_tiktok_email_and_phone_are_flagged_for_duplicate_review(): void
    {
        $referrer = $this->affiliate('Inviter', 'Titan Group', 'TIT-0001');
        $existingUser = User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);
        $existing = $this->affiliate('Existing Affiliate', 'Titan Group', 'TIT-0002', $existingUser, '012-3456789');
        DB::table('tiktok_accounts')->insert([
            'affiliate_id' => $existing->id,
            'username' => 'existing.shop',
            'username_normalized' => 'existing.shop',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->submit($referrer, $this->validData([
            'email' => 'EXISTING@example.com',
            'phone' => '+60 12-345 6789',
            'tiktok_username' => '@Existing.Shop',
            'tiktok_username_confirmation' => '@Existing.Shop',
        ]));
        $application = AffiliateApplication::query()->sole();

        $this->assertSame('duplicate_review', $application->status);
        $this->assertStringContainsString('TikTok username matches an existing record.', $application->duplicate_notes);
        $this->assertStringContainsString('Phone number matches an existing record.', $application->duplicate_notes);
        $this->assertStringContainsString('Email address matches an existing record.', $application->duplicate_notes);
    }

    public function test_similar_name_alone_remains_pending_with_name_warning(): void
    {
        $referrer = $this->affiliate('Inviter', 'Titan Group', 'TIT-0001');
        $this->affiliate('Nur Aisyah Binti Ahmad', 'Titan Group', 'TIT-0002');

        $this->submit($referrer, $this->validData([
            'full_name' => '  NUR   AISYAH BINTI AHMAD ',
        ]));
        $application = AffiliateApplication::query()->latest('id')->firstOrFail();

        $this->assertSame('pending', $application->status);
        $this->assertSame('name_warning', $application->duplicate_status);
        $this->assertStringContainsString('similar normalized name', strtolower($application->duplicate_notes));
    }

    public function test_repeated_identical_submission_returns_same_application_without_duplicate_row(): void
    {
        $referrer = $this->affiliate('Inviter', 'Titan Group', 'TIT-0001');
        $data = $this->validData();

        $first = $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $data);
        $application = AffiliateApplication::query()->sole();
        $second = $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $data);

        $first->assertRedirect(route('public.affiliate-registration.submitted', $application->application_reference));
        $second->assertRedirect(route('public.affiliate-registration.submitted', $application->application_reference));
        $this->assertSame(1, AffiliateApplication::count());
    }

    public function test_invalid_disabled_or_inactive_referral_rejects_submission(): void
    {
        $referrer = $this->affiliate('Inviter', 'Titan Group', 'TIT-0001');
        $data = $this->validData();

        $this->post(route('public.affiliate-registration.store', 'TIT-NOTREAL'), $data)->assertNotFound();

        $referrer->referral->update(['is_active' => false]);
        $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $data)->assertNotFound();

        $referrer->referral->update(['is_active' => true]);
        $referrer->update(['status' => 'inactive']);
        $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $data)->assertNotFound();

        $this->assertSame(0, AffiliateApplication::count());
    }

    public function test_validation_does_not_flash_plain_nric_and_honeypot_blocks_submission(): void
    {
        $referrer = $this->affiliate('Inviter', 'Titan Group', 'TIT-0001');

        $response = $this->from(route('public.affiliate-registration.show', $referrer->referral->referral_code))
            ->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $this->validData([
                'email' => 'invalid-email',
                'website' => 'bot-filled',
            ]));

        $response->assertRedirect(route('public.affiliate-registration.show', $referrer->referral->referral_code));
        $response->assertSessionHasErrors(['email', 'website']);
        $this->assertArrayNotHasKey('nric', session()->getOldInput());
        $this->assertSame(0, AffiliateApplication::count());
    }

    private function submit(Affiliate $referrer, array $data): void
    {
        $this->post(route('public.affiliate-registration.store', $referrer->referral->referral_code), $data)
            ->assertRedirect();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Nur Aisyah Binti Ahmad',
            'nric' => '900101-01-1234',
            'phone' => '012-3456789',
            'email' => 'aisyah@example.com',
            'tiktok_username' => '@aisyah.shop',
            'tiktok_username_confirmation' => '@aisyah.shop',
            'additional_tiktok_username' => '',
            'notes' => 'Interested in joining.',
            'consent' => '1',
            'website' => '',
        ], $overrides);
    }

    private function affiliate(
        string $name,
        string $group,
        string $code,
        ?User $user = null,
        ?string $phone = null,
    ): Affiliate {
        return Affiliate::query()->create([
            'user_id' => $user?->id,
            'affiliate_code' => $code,
            'upline_id' => null,
            'group_name' => $group,
            'affiliate_type' => $user ? 'inhouse' : 'external',
            'name' => $name,
            'name_normalized' => strtolower(preg_replace('/\s+/', ' ', trim($name))),
            'email' => $user?->email,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }
}
