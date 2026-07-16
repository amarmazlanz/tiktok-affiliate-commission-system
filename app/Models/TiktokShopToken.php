<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiktokShopToken extends Model
{
    protected $fillable = [
        'open_id',
        'seller_name',
        'region',
        'access_token',
        'refresh_token',
        'expires_at',
        'refresh_token_expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public static function active(): ?self
    {
        return self::latest()->first();
    }
}
