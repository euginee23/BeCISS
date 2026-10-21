<?php

namespace App\Models;

use App\Concerns\HasActivityLogs;
use App\Concerns\HasPayments;
use Database\Factories\BlotterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blotter extends Model
{
    /** @use HasFactory<BlotterFactory> */
    use HasActivityLogs, HasFactory, HasPayments;

    /**
     * Incident types.
     */
    public const array TYPES = [
        'theft' => 'Theft',
        'assault' => 'Assault',
        'trespassing' => 'Trespassing',
        'noise_complaint' => 'Noise Complaint',
        'property_damage' => 'Property Damage',
        'domestic_dispute' => 'Domestic Dispute',
        'vandalism' => 'Vandalism',
        'harassment' => 'Harassment',
        'other' => 'Other',
    ];

    /**
     * Blotter statuses.
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
        'is_walk_in',
        'processed_by',
        'blotter_number',
        'incident_type',
        'incident_type_other',
        'incident_datetime',
        'incident_location',
        'owner_name',
        'respondent_name',
        'narrative',
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
            'incident_datetime' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'fee' => 'decimal:2',
            'is_paid' => 'boolean',
            'is_walk_in' => 'boolean',
        ];
    }

    /**
     * Get the incident type label.
     */
    public function getTypeLabelAttribute(): string
    {
        if ($this->incident_type === 'other' && $this->incident_type_other) {
            return $this->incident_type_other;
        }

        return self::TYPES[$this->incident_type] ?? $this->incident_type;
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
     * Whether the fee can be paid now: it is owed and the report is being
     * worked on, so the cashier can take payment before the release.
     */
    public function canTakePayment(): bool
    {
        return $this->requiresPayment() && in_array($this->status, ['processing', 'ready_for_pickup'], true);
    }

    /**
     * Whether staff may still reject the report. Walk-in reports were taken
     * at the counter, so they are never rejected.
     */
    public function canBeRejected(): bool
    {
        return ! $this->is_walk_in && in_array($this->status, ['pending', 'processing'], true);
    }

    /**
     * Get the resident that filed the blotter, including deleted ones.
     *
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /**
     * Get the user who processed the blotter.
     *
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Generate a unique blotter number.
     */
    public static function generateBlotterNumber(): string
    {
        $year = date('Y');
        $lastBlotter = static::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $sequence = $lastBlotter
            ? (int) substr($lastBlotter->blotter_number, -5) + 1
            : 1;

        return sprintf('BLT-%s-%05d', $year, $sequence);
    }
}
