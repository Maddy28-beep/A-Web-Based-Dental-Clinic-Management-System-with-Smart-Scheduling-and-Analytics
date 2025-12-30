<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tooth extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'tooth_code',
        'dentition',
        'condition',
        'procedure',
        'notes',
        'severity',
        'last_recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'last_recorded_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ToothHistory::class);
    }
}
