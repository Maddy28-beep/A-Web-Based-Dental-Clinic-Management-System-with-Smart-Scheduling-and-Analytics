<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'action',
        'quantity_change',
        'unit',
        'unit_cost_cents',
        'patient_id',
        'dentist_id',
        'procedure_id',
        'stock_before',
        'stock_after',
        'occurred_at',
        'recorded_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'inventory_item_id' => 'integer',
            'quantity_change' => 'decimal:2',
            'unit_cost_cents' => 'integer',
            'patient_id' => 'integer',
            'dentist_id' => 'integer',
            'procedure_id' => 'integer',
            'stock_before' => 'decimal:2',
            'stock_after' => 'decimal:2',
            'occurred_at' => 'datetime',
            'recorded_by_user_id' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \RuntimeException('Inventory logs cannot be deleted.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class, 'dentist_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
