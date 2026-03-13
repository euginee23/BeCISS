<?php

namespace App\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /**
     * Certificate types.
     */
    public const array TYPES = [
        'barangay_clearance' => 'Barangay Clearance',
        'certificate_of_residency' => 'Certificate of Residency',
        'certificate_of_indigency' => 'Certificate of Indigency',
        'business_permit' => 'Business Permit',
        'building_permit' => 'Building Permit',
        'cedula' => 'Cedula',
        'other' => 'Other',
    ];

    /**
     * Certificate statuses.
     */
    public const array STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'ready_for_pickup' => 'Ready for Pickup',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

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
        'status',
        'remarks',
        'processed_at',
        'completed_at',
        'rejected_at',
        'rejection_reason',
        'fee',
        'is_paid',
        'or_number',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'fee' => 'decimal:2',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
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
     * Get the user who processed the certificate.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Generate a unique certificate number.
     */
    public static function generateCertificateNumber(): string
    {
        $year = date('Y');
        $lastCertificate = static::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $sequence = $lastCertificate
            ? (int) substr($lastCertificate->certificate_number, -5) + 1
            : 1;

        return sprintf('CERT-%s-%05d', $year, $sequence);
    }
}
