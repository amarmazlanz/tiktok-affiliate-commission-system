<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRateSetting extends Model
{
    use HasFactory;

    public const DEFAULT_RATES = [
        'personal_rate' => 0.10,
        'manager_bonus_rate' => 0.01,
        'l1_rate' => 0.01,
        'l2_rate' => 0.003,
        'l3_rate' => 0.002,
    ];

    protected $fillable = [
        'month',
        'year',
        'personal_rate',
        'manager_bonus_rate',
        'l1_rate',
        'l2_rate',
        'l3_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'personal_rate' => 'decimal:4',
            'manager_bonus_rate' => 'decimal:4',
            'l1_rate' => 'decimal:4',
            'l2_rate' => 'decimal:4',
            'l3_rate' => 'decimal:4',
        ];
    }

    public static function ratesFor(int $month, int $year): array
    {
        $setting = self::query()
            ->where('month', $month)
            ->where('year', $year)
            ->where('status', 'active')
            ->first();

        if (! $setting) {
            return self::DEFAULT_RATES;
        }

        return [
            'personal_rate' => (float) $setting->personal_rate,
            'manager_bonus_rate' => (float) $setting->manager_bonus_rate,
            'l1_rate' => (float) $setting->l1_rate,
            'l2_rate' => (float) $setting->l2_rate,
            'l3_rate' => (float) $setting->l3_rate,
        ];
    }
}
