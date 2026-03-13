<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * Service types.
     */
    public const array SERVICE_TYPES = [
        'certificate_request' => 'Certificate Request',
        'complaint' => 'Complaint/Blotter',
        'mediation' => 'Mediation/Settlement',
        'business_permit' => 'Business Permit',
        'building_permit' => 'Building Permit',
        'health_services' => 'Health Services',
        'legal_assistance' => 'Legal Assistance',
        'consultation' => 'Consultation',
        'other' => 'Other',
    ];

    /**
     * Appointment statuses.
     */
    public const array STATUSES = [
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resident_id',
        'handled_by',
        'reference_number',
        'service_type',
        'description',
        'appointment_date',
        'appointment_time',
        'duration_minutes',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'appointment_time' => 'datetime:H:i',
            'duration_minutes' => 'integer',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the service type label.
     */
    public function getServiceTypeLabelAttribute(): string
    {
        return self::SERVICE_TYPES[$this->service_type] ?? $this->service_type;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get the status color for badges.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'amber',
            'confirmed' => 'blue',
            'in_progress' => 'indigo',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'zinc',
            default => 'zinc',
        };
    }

    /**
     * Get the formatted appointment datetime.
     */
    public function getFormattedDatetimeAttribute(): string
    {
        return $this->appointment_date->format('M j, Y') . ' at ' . $this->appointment_time->format('g:i A');
    }

    /**
     * Get the resident that owns the appointment.
     *
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * Get the user who handled the appointment.
     *
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReferenceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastAppointment = static::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderByDesc('id')
            ->first();

        $sequence = $lastAppointment
            ? (int) substr($lastAppointment->reference_number, -4) + 1
            : 1;

        return sprintf('APT-%s%s-%04d', $year, $month, $sequence);
    }

    /**
     * Scope for upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'confirmed']);
    }

    /**
     * Scope for today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', now());
    }
}
