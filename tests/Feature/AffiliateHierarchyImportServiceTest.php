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

    public function test_amirul_self_marker_is_registered_before_hierarchy_resolution(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR', [
            'group_name' => 'Aurora Group',
        ]);
        $amirul = $this->affiliate('MOHAMAD AMIRUL JAMALUDIN', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);

        $results = [$this->resultFor($amirul)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertSame($azman->id, $amirul->fresh()->upline_id);
        $this->assertContains('amirul', $results[0]['registered_aliases']);
        $this->assertSame('Shifted to L2', $results[0]['upline_match']);
    }

    public function test_downline_uses_amirul_alias_even_when_downline_row_is_resolved_first(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR', [
            'group_name' => 'Aurora Group',
        ]);
        $amirul = $this->affiliate('MOHAMAD AMIRUL JAMALUDIN', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);
        $ummi = $this->affiliate('UMMI ATIKAH BINTI DZULKIFLY', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);

        $results = [
            $this->resultFor($ummi),
            $this->resultFor($amirul),
        ];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertSame($amirul->id, $ummi->fresh()->upline_id);
        $this->assertSame($azman->id, $ummi->fresh('upline')->upline->upline_id);
        $this->assertNotNull($ummi->fresh()->upline_id);
        $this->assertSame('Linked', $results[0]['upline_match']);
    }

    public function test_nabila_self_marker_is_registered_and_shafira_resolves_to_nabila(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR', [
            'group_name' => 'Aurora Group',
        ]);
        $nabila = $this->affiliate('NURUL NABILA KAMARUDDIN', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'NABILA',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);
        $shafira = $this->affiliate('NUR SHAFIRA ADIRA BINTI MOHD AZRI LISMEDOL', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'NABILA',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);

        $results = [
            $this->resultFor($shafira),
            $this->resultFor($nabila),
        ];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertSame($azman->id, $nabila->fresh()->upline_id);
        $this->assertContains('nabila', $results[1]['registered_aliases']);
        $this->assertSame($nabila->id, $shafira->fresh()->upline_id);
        $this->assertSame($azman->id, $shafira->fresh('upline')->upline->upline_id);
    }

    public function test_ambiguous_registered_alias_is_not_guessed(): void
    {
        $this->affiliate('MOHAMAD AMIRUL JAMALUDIN', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'Tiada',
        ]);
        $this->affiliate('AMIRUL BIN OTHMAN', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'Tiada',
        ]);
        $downline = $this->affiliate('UMMI ATIKAH BINTI DZULKIFLY', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'Tiada',
        ]);

        $results = [$this->resultFor($downline)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertNull($downline->fresh()->upline_id);
        $this->assertSame('Needs Mapping', $results[0]['upline_match']);
        $this->assertStringContainsString('Ambiguous upline alias', $results[0]['error']);
    }

    public function test_existing_self_marker_alias_in_group_is_available_during_partial_reimport(): void
    {
        $azman = $this->affiliate('AZMAN BIN MUHAMAD @ MUHAMAD NOR', [
            'group_name' => 'Aurora Group',
        ]);
        $amirul = $this->affiliate('MOHAMAD AMIRUL JAMALUDIN', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
            'upline_id' => $azman->id,
        ]);
        $ummi = $this->affiliate('UMMI ATIKAH BINTI DZULKIFLY', [
            'group_name' => 'Aurora Group',
            'raw_l1' => 'AMIRUL',
            'raw_l2' => 'En Azman',
            'raw_l3' => 'Tiada',
        ]);

        $results = [$this->resultFor($ummi)];

        app(AffiliateHierarchyImportService::class)->resolve($results);

        $this->assertSame($amirul->id, $ummi->fresh()->upline_id);
        $this->assertSame($azman->id, $ummi->fresh('upline')->upline->upline_id);
    }

    private function affiliate(string $name, array $attributes = []): Affiliate
    {
        return Affiliate::query()->create(array_merge([
            'user_id' => null,
            'upline_id' => null,
            'affiliate_code' => uniqid('AFF-'),
            'group_name' => 'Titan Group',
            'affiliate_type' => 'online',
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
