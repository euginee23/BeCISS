<?php

namespace App\Models;

use App\Concerns\HasActivityLogs;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasActivityLogs, HasFactory;

    /**
     * How far ahead an appointment may be booked.
     */
    public const int MAX_ADVANCE_DAYS = 7;

    /**
     * Appointment statuses.
     */
    public const array STATUSES = [
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
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
        'certificate_id',
        'handled_by',
        'reference_number',
        'service_type',
        'other_service',
        'description',
        'appointment_date',
        'appointment_time',
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
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The latest date an appointment may currently be booked for.
     */
    public static function maxBookingDate(): string
    {
        return now()->addDays(self::MAX_ADVANCE_DAYS)->toDateString();
    }

    /**
     * The bookable half-hour slots, 08:00 through 16:30.
     *
     * @return list<string>
     */
    public static function timeSlots(): array
    {
        $slots = [];

        for ($hour = 8; $hour < 17; $hour++) {
            $slots[] = sprintf('%02d:00', $hour);
            $slots[] = sprintf('%02d:30', $hour);
        }

        return $slots;
    }

    /**
     * Get the service type label, using the typed service for "Other" bookings.
     */
    public function getServiceTypeLabelAttribute(): string
    {
        if ($this->service_type === Service::OTHER && filled($this->other_service)) {
            return $this->other_service;
        }

        return $this->service?->name ?? Str::headline($this->service_type);
    }

    /**
     * Validation rules for the typed service shown when "Other" is picked.
     *
     * @return list<string>
     */
    public static function otherServiceRules(): array
    {
        return ['nullable', 'required_if:service_type,'.Service::OTHER, 'string', 'max:255'];
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
        return $this->appointment_date->format('M j, Y').' at '.$this->appointment_time->format('g:i A');
    }

    /**
     * Get the resident that owns the appointment, including deleted ones.
     *
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
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
     * The service this appointment was booked for, including retired ones.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_type', 'slug')->withTrashed();
    }

    /**
     * The certificate this visit is for, when booked to pay for or collect one.
     *
     * @return BelongsTo<Certificate, $this>
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
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
