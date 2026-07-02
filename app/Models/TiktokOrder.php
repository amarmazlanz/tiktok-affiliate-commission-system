<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'affiliate_id',
        'sales_source',
        'creator_username',
        'creator_username_normalized',
        'order_status',
        'estimated_commission_base',
        'actual_commission_base',
        'actual_commission_payment',
        'payment_amount',
        'currency',
        'quantity',
        'time_created',
        'payment_time',
        'time_commission_paid',
        'platform',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'estimated_commission_base' => 'decimal:2',
            'actual_commission_base' => 'decimal:2',
            'actual_commission_payment' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'quantity' => 'integer',
            'time_created' => 'datetime',
            'payment_time' => 'datetime',
            'time_commission_paid' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
