<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'status',
        'total_sales',
        'total_commission',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'total_sales' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
