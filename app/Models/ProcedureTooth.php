<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureTooth extends Model
{
    use HasFactory;

    protected $table = 'procedure_teeth';

    protected $fillable = [
        'procedure_id',
        'tooth_code',
        'surfaces',
    ];

    protected function casts(): array
    {
        return [
            'procedure_id' => 'integer',
            'surfaces' => 'array',
        ];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}
