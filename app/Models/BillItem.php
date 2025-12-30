<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'procedure_id',
        'procedure_type',
        'description',
        'tooth_count',
        'base_price_cents',
        'add_ons_cents',
        'discount_cents',
        'override_price_cents',
        'total_cents',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'bill_id' => 'integer',
            'procedure_id' => 'integer',
            'tooth_count' => 'integer',
            'base_price_cents' => 'integer',
            'add_ons_cents' => 'integer',
            'discount_cents' => 'integer',
            'override_price_cents' => 'integer',
            'total_cents' => 'integer',
            'meta' => 'array',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}
