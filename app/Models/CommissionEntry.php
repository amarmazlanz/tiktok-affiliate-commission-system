<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_run_id',
        'receiver_affiliate_id',
        'source_affiliate_id',
        'tiktok_order_id',
        'commission_type',
        'level',
        'rate',
        'base_amount',
        'commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'rate' => 'decimal:4',
            'base_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function commissionRun(): BelongsTo
    {
        return $this->belongsTo(CommissionRun::class);
    }

    public function receiverAffiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'receiver_affiliate_id');
    }

    public function sourceAffiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'source_affiliate_id');
    }

    public function tiktokOrder(): BelongsTo
    {
        return $this->belongsTo(TiktokOrder::class);
    }
}
