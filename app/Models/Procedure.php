<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'dentist_id',
        'procedure_type',
        'description',
        'cost_cents',
        'performed_at',
        'requires_allergy_tags',
        'allergy_conflicts',
        'confirmed_by_user_id',
        'confirmed_at',
        'created_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'dentist_id' => 'integer',
            'cost_cents' => 'integer',
            'performed_at' => 'datetime',
            'requires_allergy_tags' => 'array',
            'allergy_conflicts' => 'array',
            'confirmed_by_user_id' => 'integer',
            'confirmed_at' => 'datetime',
            'created_by_user_id' => 'integer',
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

    public function teeth(): HasMany
    {
        return $this->hasMany(ProcedureTooth::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function xrays(): HasMany
    {
        return $this->hasMany(XrayFile::class);
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
