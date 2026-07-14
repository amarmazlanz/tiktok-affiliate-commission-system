<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'imported_by',
    'file_name',
    'total_rows',
    'inserted_orders',
    'updated_orders',
    'skipped_rows',
    'matched_orders',
    'no_upline_orders',
    'total_sales',
])]
class OrderImport extends Model
{
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TiktokOrder::class);
    }
}
