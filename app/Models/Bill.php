<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'dentist_id',
        'status',
        'currency',
        'subtotal_cents',
        'add_ons_cents',
        'discount_cents',
        'total_cents',
        'paid_cents',
        'balance_cents',
        'locked_at',
        'locked_by_user_id',
        'due_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'dentist_id' => 'integer',
            'subtotal_cents' => 'integer',
            'add_ons_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
            'balance_cents' => 'integer',
            'locked_at' => 'datetime',
            'locked_by_user_id' => 'integer',
            'due_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
