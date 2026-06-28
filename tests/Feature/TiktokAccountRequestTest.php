<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateTiktokAccountRequest;
use App\Models\TiktokAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiktokAccountRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_can_submit_new_tiktok_username_as_pending(): void
    {
        $user = $this->affiliateUser('affiliate@example.com', 'AFF-0001');
        $affiliate = $this->affiliate($user, 'Submitting Affiliate');

        $this->actingAs($user)
            ->post(route('affiliate.tiktok-accounts.store'), ['username' => '@new_shop'])
            ->assertRedirect(route('affiliate.tiktok-accounts'));

        $request = AffiliateTiktokAccountRequest::query()->sole();
        $this->assertSame($affiliate->id, $request->affiliate_id);
        $this->assertSame('new_shop', $request->requested_username);
        $this->assertSame('new_shop', $request->normalized_username);
        $this->assertSame('pending_review', $request->status);
        $this->assertSame(0, TiktokAccount::count());
    }

    public function test_duplicate_active_username_is_blocked_at_submission(): void
    {
        $owner = $this->affiliate(null, 'Existing Owner');
        TiktokAccount::query()->create([
            'affiliate_id' => $owner->id,
            'username' => 'taken_shop',
            'username_normalized' => 'taken_shop',
            'status' => 'active',
        ]);
        $user = $this->affiliateUser('requester@example.com', 'AFF-0002');
        $this->affiliate($user, 'Requesting Affiliate');

        $this->actingAs($user)
            ->post(route('affiliate.tiktok-accounts.store'), ['username' => '@taken_shop'])
            ->assertSessionHasErrors('username');

        $this->assertSame(0, AffiliateTiktokAccountRequest::count());
    }

    public function test_admin_can_approve_pending_request_and_activates_account(): void
    {
        $admin = $this->admin();
        $user = $this->affiliateUser('approve@example.com', 'AFF-0003');
        $affiliate = $this->affiliate($user, 'Approve Me');
        $request = AffiliateTiktokAccountRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'requested_username' => 'approved_shop',
            'normalized_username' => 'approved_shop',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tiktok-account-requests.approve', $request))
            ->assertRedirect(route('admin.tiktok-account-requests.index'));

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);

        $account = TiktokAccount::query()->sole();
        $this->assertSame($affiliate->id, $account->affiliate_id);
        $this->assertSame('approved_shop', $account->username_normalized);
        $this->assertSame('active', $account->status);
    }

    public function test_admin_can_reject_pending_request_with_reason(): void
    {
        $admin = $this->admin();
        $user = $this->affiliateUser('reject@example.com', 'AFF-0004');
        $affiliate = $this->affiliate($user, 'Reject Me');
        $request = AffiliateTiktokAccountRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'requested_username' => 'rejected_shop',
            'normalized_username' => 'rejected_shop',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tiktok-account-requests.reject', $request), [
                'rejection_reason' => 'Username does not match TikTok profile.',
            ])
            ->assertRedirect(route('admin.tiktok-account-requests.index'));

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('Username does not match TikTok profile.', $request->rejection_reason);
        $this->assertSame(0, TiktokAccount::count());
    }

    public function test_approval_blocks_duplicate_username_conflict(): void
    {
        $admin = $this->admin();
        $owner = $this->affiliate(null, 'Conflict Owner');
        TiktokAccount::query()->create([
            'affiliate_id' => $owner->id,
            'username' => 'conflict_shop',
            'username_normalized' => 'conflict_shop',
            'status' => 'active',
        ]);
        $user = $this->affiliateUser('conflict@example.com', 'AFF-0005');
        $affiliate = $this->affiliate($user, 'Conflict Requester');
        $request = AffiliateTiktokAccountRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'requested_username' => 'conflict_shop',
            'normalized_username' => 'conflict_shop',
            'status' => 'pending_review',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tiktok-account-requests.approve', $request))
            ->assertSessionHasErrors('username');

        $this->assertSame('pending_review', $request->fresh()->status);
        $this->assertSame(1, TiktokAccount::count());
    }

    public function test_affiliate_cannot_access_admin_review_routes(): void
    {
        $user = $this->affiliateUser('notadmin@example.com', 'AFF-0006');
        $affiliate = $this->affiliate($user, 'Not Admin');
        $request = AffiliateTiktokAccountRequest::query()->create([
            'affiliate_id' => $affiliate->id,
            'requested_username' => 'self_approve',
            'normalized_username' => 'self_approve',
            'status' => 'pending_review',
        ]);

        $this->actingAs($user)
            ->post(route('admin.tiktok-account-requests.approve', $request))
            ->assertRedirect(route('affiliate.dashboard'));

        $this->assertSame('pending_review', $request->fresh()->status);
    }

    public function test_affiliate_can_update_own_tiktok_account_status(): void
    {
        $user = $this->affiliateUser('status@example.com', 'AFF-0007');
        $affiliate = $this->affiliate($user, 'Status Affiliate');
        $account = TiktokAccount::query()->create([
            'affiliate_id' => $affiliate->id,
            'username' => 'status_shop',
            'username_normalized' => 'status_shop',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->patch(route('affiliate.tiktok-accounts.status', $account), ['status' => 'inactive'])
            ->assertRedirect(route('affiliate.tiktok-accounts'));

        $this->assertSame('inactive', $account->fresh()->status);

        $this->actingAs($user)
            ->patch(route('affiliate.tiktok-accounts.status', $account), ['status' => 'active'])
            ->assertRedirect(route('affiliate.tiktok-accounts'));

        $this->assertSame('active', $account->fresh()->status);
    }

    public function test_affiliate_cannot_update_another_affiliates_tiktok_account_status(): void
    {
        $ownerUser = $this->affiliateUser('owner@example.com', 'AFF-0008');
        $owner = $this->affiliate($ownerUser, 'Owner Affiliate');
        $account = TiktokAccount::query()->create([
            'affiliate_id' => $owner->id,
            'username' => 'owner_shop',
            'username_normalized' => 'owner_shop',
            'status' => 'active',
        ]);

        $otherUser = $this->affiliateUser('other@example.com', 'AFF-0009');
        $this->affiliate($otherUser, 'Other Affiliate');

        $this->actingAs($otherUser)
            ->patch(route('affiliate.tiktok-accounts.status', $account), ['status' => 'inactive'])
            ->assertForbidden();

        $this->assertSame('active', $account->fresh()->status);
    }

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
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

    private function affiliate(?User $user, string $name): Affiliate
    {
        return Affiliate::query()->create([
            'user_id' => $user?->id,
            'affiliate_code' => $user?->affiliate_code ?: uniqid('AFF-'),
            'upline_id' => null,
            'group_name' => 'Titan Group',
            'affiliate_type' => 'inhouse',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => $user?->email,
            'status' => 'active',
        ]);
    }
}
