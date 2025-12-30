<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'procedure_type',
        'inventory_item_id',
        'quantity',
        'is_per_tooth',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'inventory_item_id' => 'integer',
            'quantity' => 'decimal:2',
            'is_per_tooth' => 'boolean',
            'is_active' => 'boolean',
            'created_by_user_id' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
