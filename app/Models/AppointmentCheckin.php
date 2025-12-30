<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentCheckin extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'checked_in_at',
        'checked_in_by_user_id',
        'method',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'appointment_id' => 'integer',
            'checked_in_by_user_id' => 'integer',
            'checked_in_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \RuntimeException('Appointment check-ins cannot be deleted.');
        });
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }
}
