<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('referral_code', 20)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        DB::table('affiliates')
            ->select(['id', 'group_name'])
            ->orderBy('id')
            ->chunkById(500, function ($affiliates): void {
                $now = now();
                $rows = [];

                foreach ($affiliates as $affiliate) {
                    $rows[] = [
                        'affiliate_id' => $affiliate->id,
                        'referral_code' => $this->uniqueCode((string) $affiliate->group_name, $rows),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('affiliate_referrals')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }

    private function uniqueCode(string $groupName, array $pendingRows): string
    {
        do {
            $code = $this->prefix($groupName).'-'.$this->randomToken();
            $pendingCodes = array_column($pendingRows, 'referral_code');
        } while (
            in_array($code, $pendingCodes, true)
            || DB::table('affiliate_referrals')->where('referral_code', $code)->exists()
        );

        return $code;
    }

    private function prefix(string $groupName): string
    {
        $normalized = strtolower(trim($groupName));

        return match (true) {
            str_contains($normalized, 'titan') => 'TIT',
            str_contains($normalized, 'aurora') => 'AUR',
            str_contains($normalized, 'kaizen') => 'KAI',
            str_contains($normalized, 'swg') => 'SWG',
            default => 'AFF',
        };
    }

    private function randomToken(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $token = '';

        for ($index = 0; $index < 6; $index++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $token;
    }
};
