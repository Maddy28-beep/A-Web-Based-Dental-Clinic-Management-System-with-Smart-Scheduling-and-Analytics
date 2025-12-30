<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'quantity_change',
        'reason',
        'adjusted_at',
        'adjusted_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'inventory_item_id' => 'integer',
            'quantity_change' => 'decimal:2',
            'adjusted_at' => 'datetime',
            'adjusted_by_user_id' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \RuntimeException('Stock adjustments cannot be deleted.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }
}
