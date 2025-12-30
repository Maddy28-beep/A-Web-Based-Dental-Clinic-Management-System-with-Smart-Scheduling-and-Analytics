<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'amount_cents',
        'reason',
        'refunded_at',
        'refunded_by_user_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'amount_cents' => 'integer',
            'refunded_at' => 'datetime',
            'refunded_by_user_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by_user_id');
    }
}
