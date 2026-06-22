<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Services\AffiliateHierarchyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateHierarchyImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_reference_l1_shifts_direct_upline_to_l2(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR');
        $nabila = $this->affiliate('NURUL NABILA KAMARUDDIN', [
            'raw_l1' => 'Puan NABILA',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);

        $results = [$this->resultFor($nabila)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertSame($azman->id, $nabila->fresh()->upline_id);
        $this->assertSame('Shifted to L2', $results[0]['upline_match']);
        $this->assertTrue($results[0]['self_reference_detected']);
        $this->assertTrue($results[0]['shifted_to_l2']);
    }

    public function test_downline_l1_alias_links_to_matched_affiliate_and_l2_validates_chain(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR');
        $nabila = $this->affiliate('NURUL NABILA KAMARUDDIN', [
            'upline_id' => $azman->id,
        ]);
        $shafira = $this->affiliate('NUR SHAFIRA ADIRA BINTI MOHD AZRI LISMEDOL', [
            'raw_l1' => 'Puan NABILA',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);

        $results = [$this->resultFor($shafira)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertSame($nabila->id, $shafira->fresh()->upline_id);
        $this->assertSame($azman->id, $shafira->fresh('upline')->upline->upline_id);
        $this->assertSame('Linked', $results[0]['upline_match']);
    }

    public function test_self_reference_with_no_higher_upline_results_in_no_upline(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR', [
            'raw_l1' => 'En Azman',
            'raw_l2' => 'Tiada',
            'raw_l3' => 'Tiada',
        ]);

        $results = [$this->resultFor($azman)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertNull($azman->fresh()->upline_id);
        $this->assertSame('No Upline', $results[0]['upline_match']);
        $this->assertTrue($results[0]['self_reference_detected']);
    }

    public function test_cycle_is_prevented_when_proposed_upline_already_reports_to_affiliate(): void
    {
        $affiliateA = $this->affiliate('AFFILIATE A');
        $affiliateB = $this->affiliate('AFFILIATE B', [
            'upline_id' => $affiliateA->id,
        ]);

        $affiliateA->update(['raw_l1' => 'Affiliate B']);

        $results = [$this->resultFor($affiliateA)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertNull($affiliateA->fresh()->upline_id);
        $this->assertSame('Hierarchy Conflict', $results[0]['upline_match']);
        $this->assertTrue($results[0]['cycle_prevented']);
    }

    private function affiliate(string $name, array $attributes = []): Affiliate
    {
        return Affiliate::query()->create(array_merge([
            'user_id' => null,
            'upline_id' => null,
            'affiliate_code' => uniqid('AFF-'),
            'group_name' => 'Titan Group',
            'affiliate_type' => 'external',
            'name' => $name,
            'name_normalized' => strtolower($name),
            'email' => null,
            'phone' => null,
            'status' => 'active',
        ], $attributes));
    }

    private function resultFor(Affiliate $affiliate): array
    {
        return [
            'affiliate_id' => $affiliate->id,
            'sheet' => $affiliate->group_name,
            'section' => $affiliate->affiliate_type,
            'name' => $affiliate->name,
            'affiliate_code' => $affiliate->affiliate_code,
            'tiktok_username' => '-',
            'raw_l1' => $affiliate->raw_l1 ?: '-',
            'upline_match' => '-',
            'status' => 'Profile Updated',
            'tiktok_status' => '-',
            'temporary_password' => '-',
            'error' => null,
        ];
    }
}
