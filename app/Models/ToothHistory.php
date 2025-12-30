<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToothHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'tooth_id',
        'condition',
        'procedure',
        'notes',
        'recorded_at',
        'image_before_path',
        'image_after_path',
        'created_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'tooth_id' => 'integer',
            'recorded_at' => 'datetime',
            'created_by_user_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function tooth(): BelongsTo
    {
        return $this->belongsTo(Tooth::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
