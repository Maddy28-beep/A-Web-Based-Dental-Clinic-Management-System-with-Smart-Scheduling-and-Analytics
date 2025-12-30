<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'method',
        'amount_cents',
        'paid_at',
        'recorded_by_user_id',
        'reference',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'bill_id' => 'integer',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'recorded_by_user_id' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \RuntimeException('Payments cannot be deleted. Use refunds instead.');
        });
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
