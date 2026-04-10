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
        'barangay_certification' => 'Barangay Certification',
        'certificate_of_residency' => 'Certificate of Residency',
        'certificate_of_indigency' => 'Certificate of Indigency',
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
     * Common certificate purposes in the Philippines.
     */
    public const array PURPOSE_OPTIONS = [
        'Employment / Job Application',
        'Loan Application',
        'Bank Account Opening',
        'Scholarship / School Enrollment',
        'Travel / Passport Application',
        'Business Permit Application',
        'Government Benefits (SSS, PhilHealth, GSIS)',
        'Medical / Health Services',
        'Legal / Court Purposes',
        'Senior Citizen Benefits',
        'PWD (Person with Disability) Benefits',
        'Voter Registration',
        'Police Clearance / NBI Clearance',
        'TESDA / Training Requirements',
        'Housing / Real Estate Transaction',
        'Insurance Claim',
        'Postal ID / ID Application',
        'Transfer of School / Work Records',
        'Other',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resident_id',
        'is_walkin',
        'walkin_name',
        'walkin_purok',
        'walkin_street',
        'walkin_house_number',
        'walkin_contact',
        'processed_by',
        'certificate_number',
        'type',
        'purpose',
        'purpose_other',
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
            'is_walkin' => 'boolean',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'fee' => 'decimal:2',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * Get the display value for purpose.
     */
    public function getPurposeLabelAttribute(): string
    {
        if ($this->purpose === 'Other' && $this->purpose_other) {
            return $this->purpose_other;
        }

        return $this->purpose;
    }

    /**
     * Determine if this certificate was requested by a walk-in.
     */
    public function getIsWalkinAttribute(): bool
    {
        return (bool) ($this->attributes['is_walkin'] ?? false) || is_null($this->resident_id);
    }

    /**
     * Get requester display name.
     */
    public function getRequesterNameAttribute(): string
    {
        if ($this->is_walkin) {
            return $this->walkin_name ?: 'Walk-in Requester';
        }

        return $this->resident?->full_name ?? 'Unknown Resident';
    }

    /**
     * Get requester display address.
     */
    public function getRequesterAddressAttribute(): string
    {
        if (! $this->is_walkin) {
            return $this->resident?->address ?? '—';
        }

        return collect([
            $this->walkin_purok ? "Purok {$this->walkin_purok}" : null,
            $this->walkin_house_number,
            $this->walkin_street,
        ])->filter()->implode(', ') ?: '—';
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
