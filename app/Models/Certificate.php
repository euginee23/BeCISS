<?php

namespace App\Models;

use App\Concerns\HasActivityLogs;
use App\Concerns\HasPayments;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasActivityLogs, HasFactory, HasPayments;

    /**
     * Certificate statuses.
     */
    public const array STATUSES = [
        'pending' => 'Pending',
        'awaiting_payment' => 'Awaiting Payment',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    /**
     * The statuses each status may move to.
     *
     * @var array<string, list<string>>
     */
    public const array TRANSITIONS = [
        'pending' => ['awaiting_payment', 'processing', 'rejected', 'cancelled'],
        'awaiting_payment' => ['processing', 'rejected', 'cancelled'],
        'processing' => ['ready_for_pickup', 'rejected'],
        'ready_for_pickup' => ['completed', 'rejected'],
        'completed' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    /**
     * Statuses in which the request is still open.
     *
     * @var list<string>
     */
    public const array OPEN_STATUSES = ['pending', 'awaiting_payment', 'processing', 'ready_for_pickup'];

    /**
     * Statuses in which the resident should visit the barangay hall.
     *
     * @var list<string>
     */
    public const array VISIT_STATUSES = ['awaiting_payment', 'processing', 'ready_for_pickup'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resident_id',
        'processed_by',
        'certificate_number',
        'type',
        'purpose',
        'purpose_other',
        'status',
        'remarks',
        'approved_at',
        'processed_at',
        'completed_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'issued_at',
        'fee',
        'is_paid',
        'or_number',
        'ctc_number',
        'ctc_place_issued',
        'ctc_date_issued',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'issued_at' => 'date',
            'ctc_date_issued' => 'date',
            'fee' => 'decimal:2',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * Get the display value for purpose.
     */
    public function getPurposeLabelAttribute(): string
    {
        if ($this->purpose === CertificatePurpose::OTHER && $this->purpose_other) {
            return $this->purpose_other;
        }

        return $this->purpose;
    }

    /**
     * Get requester display name.
     */
    public function getRequesterNameAttribute(): string
    {
        return $this->resident?->full_name ?? 'Unknown Resident';
    }

    /**
     * Get requester display address.
     */
    public function getRequesterAddressAttribute(): string
    {
        return $this->resident?->address ?: '—';
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->certificateType?->name ?? Str::headline($this->type);
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
            'pending' => 'amber',
            'awaiting_payment' => 'orange',
            'processing' => 'blue',
            'ready_for_pickup' => 'emerald',
            'completed' => 'green',
            'rejected' => 'red',
            'cancelled' => 'zinc',
            default => 'zinc',
        };
    }

    /**
     * Get the resident that owns the certificate.
     *
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * Get the type this certificate was requested under, including retired types.
     *
     * @return BelongsTo<CertificateType, $this>
     */
    public function certificateType(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'type', 'slug')->withTrashed();
    }

    /**
     * Get the user who processed the certificate.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Appointments booked to pay for or pick up this certificate.
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * The scheduled or confirmed visit for this certificate, if any.
     */
    public function activeAppointment(): ?Appointment
    {
        return $this->appointments()->whereIn('status', ['scheduled', 'confirmed'])->latest('appointment_date')->first();
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Whether staff may still change the request details.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['pending', 'awaiting_payment', 'processing'], true);
    }

    /**
     * Whether the resident can cancel the request themselves.
     */
    public function isCancellable(): bool
    {
        return $this->canTransitionTo('cancelled');
    }

    /**
     * Whether the resident should book a visit to pay for or collect it.
     */
    public function needsVisit(): bool
    {
        return in_array($this->status, self::VISIT_STATUSES, true);
    }

    /**
     * Whether a cashier can record the fee now: approved, unpaid and still open.
     */
    public function canTakePayment(): bool
    {
        return in_array($this->status, self::VISIT_STATUSES, true) && $this->requiresPayment();
    }

    /**
     * Whether the certificate may be printed: paid (or free) and past approval.
     */
    public function isPrintable(): bool
    {
        return in_array($this->status, ['processing', 'ready_for_pickup', 'completed'], true)
            && ! $this->requiresPayment();
    }

    /**
     * Create a certificate with the next free number, retrying if a
     * concurrent request claimed the same number first.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithNumber(array $attributes): self
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(fn (): self => static::create([
                    ...$attributes,
                    'certificate_number' => static::generateCertificateNumber(),
                ]), attempts: 3);
            } catch (UniqueConstraintViolationException $exception) {
                if (++$attempts >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Generate the next certificate number for the current year.
     */
    public static function generateCertificateNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "CERT-{$year}-";

        $lastNumber = static::query()
            ->where('certificate_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max('certificate_number');

        $sequence = $lastNumber ? (int) substr($lastNumber, strlen($prefix)) + 1 : 1;

        return sprintf('%s%05d', $prefix, $sequence);
    }
}
