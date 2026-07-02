<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use App\Services\CommissionCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderImportNoUplineTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_import_keeps_no_upline_orders_for_monitoring_without_commission(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $affiliateUser = User::query()->create([
            'name' => 'Ali Seller',
            'email' => 'ali@example.com',
            'password' => bcrypt('password'),
            'role' => 'affiliate',
        ]);

        $affiliate = Affiliate::query()->create([
            'user_id' => $affiliateUser->id,
            'name' => 'Ali Seller',
            'email' => 'ali@example.com',
            'status' => 'active',
        ]);

        DB::table('tiktok_accounts')->insert([
            'affiliate_id' => $affiliate->id,
            'username' => 'ali_shop',
            'username_normalized' => 'ali_shop',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $csv = implode("\n", [
            'Order ID,Creator Username,Order Status,Est. Commission Base,Actual Commission Base,Actual Commission Payment,Payment Amount,Currency,Quantity,Time Created,Payment time,Time Commission Paid,Platform',
            'ORD-MAPPED,@ali_shop,Settled,100.00,100.00,10.00,100.00,MYR,1,2026-04-10 10:00:00,,,TikTok',
            'ORD-NO-UPLINE,@unknown_shop,Settled,250.00,250.00,25.00,250.00,MYR,1,2026-04-11 10:00:00,,,TikTok',
        ]);

        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.orders.import'), ['csv_file' => $file])
            ->assertOk()
            ->assertSee('No Upline Orders')
            ->assertSee('RM 250.00');

        $this->assertDatabaseHas('tiktok_orders', [
            'order_id' => 'ORD-MAPPED',
            'affiliate_id' => $affiliate->id,
            'sales_source' => 'mapped_affiliate',
        ]);

        $this->assertDatabaseHas('tiktok_orders', [
            'order_id' => 'ORD-NO-UPLINE',
            'affiliate_id' => null,
            'sales_source' => 'no_upline',
            'creator_username_normalized' => 'unknown_shop',
        ]);

        $this->assertSame(2, DB::table('tiktok_orders')->count());

        $this->actingAs($admin)
            ->post(route('admin.orders.import'), [
                'csv_file' => UploadedFile::fake()->createWithContent('orders.csv', $csv),
            ])
            ->assertOk();

        $this->assertSame(2, DB::table('tiktok_orders')->count());

        $run = app(CommissionCalculatorService::class)->calculate(4, 2026, 'final');

        $this->assertSame(350.0, (float) $run->total_sales);
        $this->assertSame(10.0, (float) $run->total_commission);
        $this->assertDatabaseCount('commission_entries', 1);
        $this->assertDatabaseHas('commission_entries', [
            'commission_run_id' => $run->id,
            'source_affiliate_id' => $affiliate->id,
            'commission_type' => 'personal',
        ]);
    }
}
