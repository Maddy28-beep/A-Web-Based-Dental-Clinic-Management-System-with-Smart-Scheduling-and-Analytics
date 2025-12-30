<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'visit_id',
        'procedure_id',
        'tooth_code',
        'original_name',
        'mime_type',
        'size_bytes',
        'encrypted_path',
        'recorded_at',
        'uploaded_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'procedure_id' => 'integer',
            'size_bytes' => 'integer',
            'recorded_at' => 'datetime',
            'uploaded_by_user_id' => 'integer',
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

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
