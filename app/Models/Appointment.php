<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference_code',
        'patient_id',
        'dentist_id',
        'service_id',
        'service_duration_minutes',
        'buffer_minutes',
        'is_override',
        'override_reason',
        'patient_name',
        'patient_email',
        'patient_phone',
        'start_at',
        'end_at',
        'status',
        'checked_in_at',
        'in_treatment_at',
        'completed_at',
        'cancelled_at',
        'no_show_at',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'in_treatment_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',
            'service_duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'is_override' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function checkin(): HasOne
    {
        return $this->hasOne(AppointmentCheckin::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(AppointmentStatusLog::class);
    }
}
