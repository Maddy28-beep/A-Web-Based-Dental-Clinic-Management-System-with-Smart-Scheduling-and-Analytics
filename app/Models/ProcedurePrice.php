<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedurePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'procedure_type',
        'dentist_id',
        'base_price_cents',
        'per_tooth_cents',
        'duration_minutes',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'dentist_id' => 'integer',
            'base_price_cents' => 'integer',
            'per_tooth_cents' => 'integer',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'created_by_user_id' => 'integer',
        ];
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
